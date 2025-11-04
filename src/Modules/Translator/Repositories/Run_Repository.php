<?php
declare(strict_types=1);

namespace PLLAT\Translator\Repositories;

use PLLAT\Translator\Enums\RunStatus;
use PLLAT\Translator\Models\Run;
use PLLAT\Translator\Models\Translation_Config;
use ReflectionClass;
use ReflectionProperty;

/**
 * Repository for Run entity.
 * Handles all database operations for translation runs.
 */
class Run_Repository {
    /**
     * Create a new run.
     *
     * @param Translation_Config $config The config of the run.
     * @return Run The created run.
     */
    public function create( Translation_Config $config ): Run {
        global $wpdb;

        $wpdb->insert(
            $this->get_table_name(),
            array( 'config' => \json_encode( $config ) ),
        );

        return $this->find( (int) $wpdb->insert_id );
    }

    /**
     * Find a run by ID.
     *
     * @param int $id The ID of the run.
     * @return Run The run.
     * @throws \Exception If run not found.
     */
    public function find( int $id ): Run {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM %i WHERE id = %d',
                $this->get_table_name(),
                $id,
            ),
        );

        if ( ! $row ) {
            throw new \Exception( 'Run not found' );
        }

        return $this->hydrate_run( (array) $row );
    }

    /**
     * Find all runs.
     *
     * @return array<int, Run> Array of all runs.
     */
    public function find_all(): array {
        global $wpdb;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM %i',
                $this->get_table_name(),
            ),
        );

        $runs = array();
        foreach ( $results as $row ) {
            $runs[] = $this->hydrate_run( (array) $row );
        }

        return $runs;
    }

    /**
     * Find runs by status.
     *
     * @param array<int, string> $statuses The statuses to filter by.
     * @return array<int, Run> Array of runs matching the statuses.
     */
    public function find_by_status( array $statuses ): array {
        if ( 0 === \count( $statuses ) ) {
            return array();
        }

        global $wpdb;

        $placeholders = \implode( ',', \array_fill( 0, \count( $statuses ), '%s' ) );
        $query        = "SELECT * FROM {$this->get_table_name()} WHERE status IN ({$placeholders})";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $query is a prepared statement template.
        $results = $wpdb->get_results( $wpdb->prepare( $query, ...$statuses ) );

        $runs = array();
        foreach ( $results as $row ) {
            $runs[] = $this->hydrate_run( (array) $row );
        }

        return $runs;
    }

    /**
     * Find all active runs (pending or running).
     *
     * @return array<int, Run> Array of active runs.
     */
    public function find_active_runs(): array {
        return $this->find_by_status(
            array(
                RunStatus::Pending->value,
                RunStatus::Running->value,
            )
        );
    }

    /**
     * Save a run.
     *
     * @param Run $run The run to save.
     * @return void
     */
    public function save( Run $run ): void {
        global $wpdb;

        $wpdb->update(
            $this->get_table_name(),
            array(
                'config'         => \json_encode( $run->get_config() ),
                'status'         => $run->get_status()->value,
                'started_at'     => $run->get_started_at(),
                'last_heartbeat' => $run->get_last_heartbeat(),
            ),
            array( 'id' => $run->get_id() ),
        );
    }

    /**
     * Attempt to atomically complete a run.
     * Uses SELECT FOR UPDATE to prevent race conditions when multiple workers
     * try to complete the same run simultaneously.
     *
     * @param int $run_id The run ID to complete.
     * @return bool True if run was completed, false if already terminal or has active jobs.
     */
    public function attempt_atomic_completion( int $run_id ): bool {
        global $wpdb;

        $runs_table = $this->get_table_name();
        $jobs_table = $wpdb->prefix . 'pllat_jobs';

        // Start transaction for atomicity.
        $wpdb->query( 'START TRANSACTION' );

        try {
            // Lock the run row for update.
            $current_status = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT status FROM {$runs_table} WHERE id = %d FOR UPDATE",
                    $run_id
                )
            );

            // Guard: Already in terminal state.
            if ( $this->is_terminal_status( $current_status ) ) {
                $wpdb->query( 'ROLLBACK' );
                return false;
            }

            // Guard: Run still has non-terminal jobs.
            if ( $this->has_active_jobs( $run_id, $jobs_table ) ) {
                $wpdb->query( 'ROLLBACK' );
                return false;
            }

            // Atomically update status to completed.
            $wpdb->update(
                $runs_table,
                array( 'status' => RunStatus::Completed->value ),
                array( 'id' => $run_id ),
                array( '%s' ),
                array( '%d' )
            );

            $wpdb->query( 'COMMIT' );
            return true;

        } catch ( \Exception $e ) {
            $wpdb->query( 'ROLLBACK' );
            throw $e;
        }
    }

    /**
     * Delete a run.
     *
     * @param Run $run The run to delete.
     * @return void
     */
    public function delete( Run $run ): void {
        global $wpdb;

        $wpdb->delete(
            $this->get_table_name(),
            array( 'id' => $run->get_id() ),
        );
    }

    /**
     * Get the table name for runs.
     *
     * @return string The table name.
     */
    public function get_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . Run::TABLE_NAME;
    }

    /**
     * Hydrate a Run entity from database row.
     *
     * @param array<string, mixed> $row The database row.
     * @return Run The hydrated run.
     */
    private function hydrate_run( array $row ): Run {
        // Create empty Run instance.
        $run        = new Run( (int) $row['id'] );
        $reflection = new ReflectionClass( Run::class );

        // Hydrate config.
        $config_data = \json_decode( $row['config'], false );

        $config = new Translation_Config(
            lang_from: $config_data->lang_from,
            langs_to: (array) $config_data->langs_to,
            post_types: (array) $config_data->post_types,
            taxonomies: (array) $config_data->taxonomies,
            string_groups: (array) $config_data->string_groups,
            terms: (array) $config_data->terms,
            specific_posts: (array) ( $config_data->specific_posts ?? array() ),
            specific_terms: (array) ( $config_data->specific_terms ?? array() ),
            instructions: $config_data->instructions ?? '',
            forced: $config_data->forced ?? false,
            limit: $config_data->limit ?? null,
        );

        // Set properties using reflection
        $this->set_property( $reflection, $run, 'id', (int) $row['id'] );
        $this->set_property( $reflection, $run, 'status', RunStatus::from( $row['status'] ) );
        $this->set_property( $reflection, $run, 'config', $config );
        $this->set_property( $reflection, $run, 'started_at', (int) ( $row['started_at'] ?? 0 ) );
        $this->set_property( $reflection, $run, 'last_heartbeat', (int) ( $row['last_heartbeat'] ?? 0 ) );

        return $run;
    }

    /**
     * Set a protected/private property using reflection.
     *
     * @param ReflectionClass $reflection The reflection class.
     * @param Run             $run The run instance.
     * @param string          $property_name The property name.
     * @param mixed           $value The value to set.
     * @return void
     */
    private function set_property( $reflection, Run $run, string $property_name, mixed $value ): void {
        $property = $reflection->getProperty( $property_name );
        $property->setAccessible( true );
        $property->setValue( $run, $value );
    }

    /**
     * Check if status is terminal (completed, cancelled, or failed).
     *
     * @param string|null $status The status to check.
     * @return bool True if status is terminal.
     */
    private function is_terminal_status( ?string $status ): bool {
        if ( null === $status ) {
            return false;
        }

        return \in_array(
            $status,
            array(
                RunStatus::Completed->value,
                RunStatus::Cancelled->value,
                RunStatus::Failed->value,
            ),
            true
        );
    }

    /**
     * Check if run has active (non-terminal) jobs.
     *
     * @param int    $run_id The run ID.
     * @param string $jobs_table The jobs table name.
     * @return bool True if run has active jobs.
     */
    private function has_active_jobs( int $run_id, string $jobs_table ): bool {
        global $wpdb;

        $active_count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$jobs_table}
                WHERE run_id = %d
                AND status NOT IN ('completed', 'cancelled', 'failed')",
                $run_id
            )
        );

        return $active_count > 0;
    }
}
