<?php
declare(strict_types=1);

namespace PLLAT\Translator\Models;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
use PLLAT\Translator\Enums\JobStatus;
use PLLAT\Translator\Enums\RunStatus;
use PLLAT\Translator\Models\Translation_Config;
use PLLAT\Translator\Repositories\Job_Repository;

/**
 * A run of the bulk translator.
 * Pure entity - no database operations.
 */
class Run {
    /**
     * The name of the table in the database.
     *
     * @var string
     */
    const TABLE_NAME = 'pllat_bulk_runs';

    /**
     * The status of the run.
     *
     * @var RunStatus
     */
    protected RunStatus $status;

    /**
     * The config of the run.
     *
     * @var Translation_Config
     */
    protected Translation_Config $config;

    /**
     * When the run was started (status changed to 'running').
     *
     * @var int
     */
    protected int $started_at = 0;

    /**
     * Last heartbeat timestamp (updated during processing).
     *
     * @var int
     */
    protected int $last_heartbeat = 0;

    /**
     * Constructor.
     * Note: This constructor does NOT load from database.
     * Use Run_Repository::find() to load an existing run.
     *
     * @param int            $id The ID of the run.
     * @param Job_Repository $job_repository The job repository (optional for backwards compatibility).
     */
    public function __construct(
        protected int $id,
        protected ?Job_Repository $job_repository = null,
    ) {
        // Temporary backwards compatibility: inject repository if not provided.
        if ( null !== $this->job_repository ) {
            return;
        }

        $this->job_repository = \xwp_app( 'pllat' )->get( Job_Repository::class );
    }

    /**
     * Get the ID of the run.
     *
     * @return int The ID of the run.
     */
    public function get_id(): int {
        return $this->id;
    }

    /**
     * Get the config of the run.
     *
     * @return Translation_Config The config of the run.
     */
    public function get_config(): Translation_Config {
        return $this->config;
    }

    /**
     * Update the config of the run.
     *
     * @param Translation_Config $config The config of the run.
     */
    public function set_config( Translation_Config $config ): void {
        $this->config = $config;
    }

    /**
     * Get the status of the run.
     *
     * @return RunStatus The status of the run.
     */
    public function get_status(): RunStatus {
        return $this->status;
    }

    /**
     * Set the status of the run.
     *
     * @param RunStatus $status The status of the run.
     */
    public function set_status( RunStatus $status ): void {
        $this->status = $status;
    }

    /**
     * Get the IDs of the jobs for the run.
     *
     * @return array<int> The IDs of the jobs.
     */
    public function get_job_ids(): array {
        global $wpdb;
        $table_name = $this->job_repository->get_table_name();
        $jobs       = $wpdb->get_results(
            $wpdb->prepare( 'SELECT id FROM %i WHERE run_id = %d', $table_name, $this->id ),
        );
        return \array_map( static fn( $row ) => (int) $row->id, $jobs );
    }

    /**
     * Connect jobs to the run.
     * Delegates to repository for database operation.
     *
     * @param array<int> $job_ids The IDs of the jobs to connect.
     */
    public function connect_jobs( array $job_ids ): void {
        $this->job_repository->bulk_assign_to_run( $job_ids, $this->id );
    }

    /**
     * Get the next job to process with atomic locking.
     * Uses atomic claim to prevent race conditions in concurrent processing.
     *
     * @return Job|null The next job to process.
     */
    public function get_next_job(): ?Job {
        // Use atomic job claiming to prevent race conditions
        // This is critical for concurrent processing by multiple Action Scheduler workers
        return $this->job_repository->claim_next_job_for_run( $this->id );
    }

    /**
     * Check if run has any non-terminal jobs (pending or in_progress).
     * Used to verify if a run is truly complete before marking it as such.
     * Note: Failed jobs are now considered terminal (no automatic retry).
     *
     * @return bool True if run has non-terminal jobs.
     */
    public function has_non_terminal_jobs(): bool {
        global $wpdb;
        $job_table_name = $this->job_repository->get_table_name();

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM %i WHERE run_id = %d AND status NOT IN (%s, %s, %s)",
                $job_table_name,
                $this->id,
                JobStatus::Completed->value,
                JobStatus::Cancelled->value,
                JobStatus::Failed->value,
            ),
        );

        return (int) $count > 0;
    }

    /**
     * Complete a run.
     */
    public function complete(): void {
        $this->set_status( RunStatus::Completed );
    }

    /**
     * Get when the run was started.
     *
     * @return int Timestamp when run started.
     */
    public function get_started_at(): int {
        return $this->started_at;
    }

    /**
     * Set when the run was started.
     *
     * @param int $timestamp When the run started.
     */
    public function set_started_at( int $timestamp ): void {
        $this->started_at = $timestamp;
    }

    /**
     * Get the last heartbeat timestamp.
     *
     * @return int Last heartbeat timestamp.
     */
    public function get_last_heartbeat(): int {
        return $this->last_heartbeat;
    }

    /**
     * Update the heartbeat to current time.
     */
    public function update_heartbeat(): void {
        $this->last_heartbeat = \time();
    }

    /**
     * Check if run is stale (no heartbeat for too long).
     *
     * @param int $timeout Timeout in seconds (default: 600 = 10 minutes).
     * @return bool True if run is stale.
     */
    public function is_stale( int $timeout = 600 ): bool {
        if ( ! $this->status->isRunning() ) {
            return false;
        }

        // If never had a heartbeat but started_at is set, use started_at.
        $reference_time = $this->last_heartbeat > 0 ? $this->last_heartbeat : $this->started_at;

        if ( 0 === $reference_time ) {
            return false; // No reference time, not stale.
        }

        $time_elapsed = \time() - $reference_time;
        return $time_elapsed > $timeout;
    }

    /**
     * Cleanup stale jobs.
     *
     * @deprecated Removed - Job_Processor no longer exists. Use Job_Recovery_Service instead.
     */
    public function cleanup_due_jobs(): void {
        // This method is deprecated and no longer functional.
        // Job recovery is now handled by Job_Recovery_Handler automatically.
    }
}
