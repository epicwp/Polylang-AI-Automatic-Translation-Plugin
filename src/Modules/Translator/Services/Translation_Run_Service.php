<?php
namespace PLLAT\Translator\Services;

use PLLAT\Common\Utils\Memory_Manager;
use PLLAT\Sync\Services\Sync_Service;
use PLLAT\Translator\Enums\RunStatus;
use PLLAT\Translator\Models\Run;
use PLLAT\Translator\Repositories\Run_Repository;
use PLLAT\Translator\Services\Bulk\Post_Query_Service;
use PLLAT\Translator\Services\Bulk\Term_Query_Service;

/**
 * Translation process service.
 */
class Translation_Run_Service {
    // Action hooks.
    const CLEANUP_ACTION = 'pllat_cleanup_stale_jobs_hourly';

    // Batch processing constants.
    private const BATCH_SIZE_DEFAULT = 500;  // Optimal balance between memory and performance.
    private const BATCH_SIZE_MINIMUM = 100;  // Minimum batch size when memory constrained.
    private const GC_INTERVAL        = 1000; // Trigger garbage collection every N jobs.

    // Memory management constants.
    private const MEMORY_THRESHOLD_PERCENT = 0.8; // Trigger warning at 80% memory usage.

    /**
     * Constructor.
     *
     * @param Post_Query_Service         $post_query_service        The post query service.
     * @param Term_Query_Service         $term_query_service        The term query service.
     * @param Run_Repository             $run_repository            The run repository.
     * @param Sync_Service               $sync_service              The sync service.
     * @param Memory_Manager             $memory_manager            The memory manager utility.
     * @param External_Processor_Service $external_processor_service The external processor service.
     */
    public function __construct(
        protected Post_Query_Service $post_query_service,
        protected Term_Query_Service $term_query_service,
        protected Run_Repository $run_repository,
        protected Sync_Service $sync_service,
        protected Memory_Manager $memory_manager,
    ) {
    }

    /**
     * Create a translation run and connect jobs.
     *
     * @param Run $run The run to create.
     * @return void
     * @throws \Exception If system not ready for translation.
     */
    public function create_translation_run( Run $run ): void {
        $this->connect_jobs_to_run( $run );
        $this->run_repository->save( $run );
        \do_action( 'pllat_run_created', $run );
    }

    /**
     * Cancel a run.
     * Notifies external processor to stop processing.
     *
     * @param Run $run The run to cancel.
     * @return void
     */
    public function cancel_run( Run $run ): void {
        $run->set_status( RunStatus::Cancelled );
        $this->run_repository->save( $run );

        \do_action( 'pllat_run_cancelled', $run );
    }

    /**
     * Delete a run.
     *
     * @param Run $run The run to delete.
     * @return void
     */
    public function delete_run( Run $run ): void {
        $this->run_repository->delete( $run );
    }

    /**
     * Complete a run atomically.
     * Delegates to repository for atomic database operation, then updates model and fires hooks.
     *
     * @param Run $run The run to complete.
     * @return bool True if run was completed, false if already terminal or has active jobs.
     */
    public function complete_run( Run $run ): bool {
        // Delegate atomic completion to repository layer.
        $completed = $this->run_repository->attempt_atomic_completion( $run->get_id() );

        // Guard: Completion failed (already terminal or has active jobs).
        if ( ! $completed ) {
            return false;
        }

        // Update local model to reflect database state.
        $run->complete();

        // Dispatch action for logging and other observers.
        \do_action( 'pllat_run_completed', $run );

        return true;
    }

    /**
     * Ensure hourly cleanup action is scheduled.
     * Called by Job_Recovery_Handler.
     *
     * @return void
     */
    public function ensure_cleanup_scheduled(): void {
        if ( \as_has_scheduled_action( self::CLEANUP_ACTION, array(), 'pllat' ) ) {
            return;
        }
        \as_schedule_recurring_action(
            \time(),
            HOUR_IN_SECONDS,
            self::CLEANUP_ACTION,
            array(),
            'pllat',
        );
    }

    /**
     * Connect jobs to a run in batches with memory management.
     * Optimized for large runs to prevent memory exhaustion.
     *
     * @param Run $run The run to connect jobs to.
     * @return void
     */
    public function connect_jobs_to_run( Run $run ): void {
        $offset          = 0;
        $batch_size      = self::BATCH_SIZE_DEFAULT;
        $config_limit    = $run->get_config()->get_limit();
        $total_connected = 0;

        while ( true ) {
            // Check memory usage before each batch.
            if ( $this->memory_manager->is_approaching_limit( self::MEMORY_THRESHOLD_PERCENT ) ) {
                // Fire warning action for monitoring.
                \do_action(
                    'pllat_memory_warning',
                    array(
                        'connected' => $total_connected,
                        'memory'    => $this->memory_manager->get_usage_bytes(),
                        'run_id'    => $run->get_id(),
                    ),
                );

                // Reduce batch size to conserve memory.
                $batch_size = \max( self::BATCH_SIZE_MINIMUM, (int) ( $batch_size / 2 ) );
            }

            $result = $this->process_batch( $run, $offset, $batch_size, $config_limit, $total_connected );

            if ( $result['should_stop'] ) {
                break;
            }

            $total_connected += $result['connected'];
            $offset          += $batch_size;

            // Periodic garbage collection for large runs.
            if ( 0 !== $total_connected % self::GC_INTERVAL ) {
                continue;
            }

            $this->memory_manager->collect_garbage();
        }

        // Final garbage collection.
        $this->memory_manager->collect_garbage();
    }

    /**
     * Returns the stats of the run.
     *
     * @param Run $run The run to get stats for.
     * @return array<string, array<string, array<string, int>>>
     */
    public function get_stats( Run $run ): array {
        $stats = $this->calculate_stats_for_run( $run );
        return $stats;
    }

    /**
     * Process a single batch of jobs.
     *
     * @param Run      $run The run to connect jobs to.
     * @param int      $offset Current offset.
     * @param int      $batch_size Batch size.
     * @param int|null $config_limit Configured limit.
     * @param int      $total_connected Total jobs connected so far.
     * @return array{should_stop: bool, connected: int}
     */
    private function process_batch( Run $run, int $offset, int $batch_size, ?int $config_limit, int $total_connected ): array {
        if ( $this->has_reached_limit( $config_limit, $total_connected ) ) {
            return array(
                'connected'   => 0,
                'should_stop' => true,
            );
        }

        $current_limit = $this->calculate_batch_limit( $batch_size, $config_limit, $total_connected );
        $post_count    = $this->connect_post_jobs( $run, $offset, $current_limit );
        $term_count    = $this->connect_term_jobs( $run, $offset, $current_limit );
        $connected     = $post_count + $term_count;

        $should_stop = $this->has_reached_limit(
            $config_limit,
            $total_connected + $connected,
        ) || 0 === $connected;

        return array(
            'connected'   => $connected,
            'should_stop' => $should_stop,
        );
    }

    /**
     * Check if the connection limit has been reached.
     *
     * @param int|null $limit The configured limit.
     * @param int      $connected Number of jobs connected.
     * @return bool
     */
    private function has_reached_limit( ?int $limit, int $connected ): bool {
        return null !== $limit && $connected >= $limit;
    }

    /**
     * Calculate the batch limit for the current iteration.
     *
     * @param int      $batch_size The standard batch size.
     * @param int|null $config_limit The configured limit.
     * @param int      $total_connected Total jobs connected so far.
     * @return int
     */
    private function calculate_batch_limit( int $batch_size, ?int $config_limit, int $total_connected ): int {
        $remaining = null !== $config_limit ? $config_limit - $total_connected : $batch_size;
        return \min( $batch_size, $remaining );
    }

    /**
     * Connect post jobs to the run.
     *
     * @param Run $run The run to connect to.
     * @param int $offset The offset for pagination.
     * @param int $limit The limit for this batch.
     * @return int Number of jobs connected.
     */
    private function connect_post_jobs( Run $run, int $offset, int $limit ): int {
        $config = $run->get_config();

        // Skip if no post types configured (and not in specific post mode).
        if ( 0 === \count( $config->get_post_types() ) && 0 === \count( $config->get_specific_posts() ) ) {
            return 0;
        }

        $post_jobs = $this->post_query_service->get_jobs_for_run( $run, $offset, $limit );
        $run->connect_jobs( \array_map( static fn( $job ) => $job->get_id(), $post_jobs ) );
        return \count( $post_jobs );
    }

    /**
     * Connect term jobs to the run.
     *
     * @param Run $run The run to connect to.
     * @param int $offset The offset for pagination.
     * @param int $limit The limit for this batch.
     * @return int Number of jobs connected.
     */
    private function connect_term_jobs( Run $run, int $offset, int $limit ): int {
        $config = $run->get_config();

        // Skip if no taxonomies configured (and not in specific term mode).
        if ( 0 === \count( $config->get_taxonomies() ) && 0 === \count( $config->get_specific_terms() ) ) {
            return 0;
        }

        $term_jobs = $this->term_query_service->get_jobs_for_run( $run, $offset, $limit );
        $run->connect_jobs( \array_map( static fn( $job ) => $job->get_id(), $term_jobs ) );
        return \count( $term_jobs );
    }

    /**
     * Calculate the stats for a run.
     *
     * @param Run $run The run to calculate the stats for.
     * @return array<string, array<string, array<string, int>>> The stats.
     */
    private function calculate_stats_for_run( Run $run ): array {
        $offset = 0;
        $limit  = 1000;

        $stats = array(
            'posts' => array(),
            'terms' => array(),
        );

        while ( true ) {
            $post_jobs = $this->post_query_service->get_jobs_for_run( $run, $offset, $limit );
            $term_jobs = $this->term_query_service->get_jobs_for_run( $run, $offset, $limit );

            $this->process_jobs_into_stats( $post_jobs, 'posts', $stats );
            $this->process_jobs_into_stats( $term_jobs, 'terms', $stats );

            if ( 0 === \count( $post_jobs ) && 0 === \count( $term_jobs ) ) {
                break;
            }

            $offset += $limit;
        }
        return $stats;
    }

    /**
     * Process content results into stats format.
     *
     * @param array  $jobs Array of content items with job data.
     * @param string $type The type of content to process.
     * @param array  &$stats The stats to process into.
     * @return void
     */
    private function process_jobs_into_stats( array $jobs, string $type, array &$stats ): void {
        foreach ( $jobs as $row ) {
            $lang         = $this->extract_field( $row, 'lang_to' );
            $content_type = $this->extract_field( $row, 'content_type' );
            $status       = $this->extract_field( $row, 'job_status' );

            $this->ensure_stats_structure( $stats, $type, $lang, $content_type );
            $this->increment_stats( $stats, $type, $lang, $content_type, $status );
        }
    }

    /**
     * Extract field from row (supports both array and object).
     *
     * @param array|object $row The row data.
     * @param string       $field The field name.
     * @return mixed
     */
    private function extract_field( $row, string $field ) {
        return \is_array( $row ) ? $row[ $field ] : $row->$field;
    }

    /**
     * Ensure stats structure exists for given keys.
     *
     * @param array  &$stats The stats array.
     * @param string $type The type key.
     * @param string $lang The language key.
     * @param string $content_type The content type key.
     * @return void
     */
    private function ensure_stats_structure( array &$stats, string $type, string $lang, string $content_type ): void {
        if ( isset( $stats[ $type ][ $lang ][ $content_type ] ) ) {
            return;
        }

        $stats[ $type ][ $lang ][ $content_type ] = array(
            'completed'   => 0,
            'failed'      => 0,
            'in_progress' => 0,
            'pending'     => 0,
            'total'       => 0,
        );
    }

    /**
     * Increment stats counters.
     *
     * @param array  &$stats The stats array.
     * @param string $type The type key.
     * @param string $lang The language key.
     * @param string $content_type The content type key.
     * @param string $status The job status.
     * @return void
     */
    private function increment_stats( array &$stats, string $type, string $lang, string $content_type, string $status ): void {
        ++$stats[ $type ][ $lang ][ $content_type ]['total'];
        ++$stats[ $type ][ $lang ][ $content_type ][ $status ];
    }
}
