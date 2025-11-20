<?php
declare(strict_types=1);

namespace PLLAT\Translator\Repositories;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
use PLLAT\Translator\Enums\JobStatus;
use PLLAT\Translator\Models\Job;
use PLLAT\Translator\Repositories\Query\Job_Query;

/**
 * Repository for Job entity.
 * Handles all database operations for Jobs.
 */
class Job_Repository {
    private const TABLE_NAME = 'pllat_jobs';

    /**
     * Create a new job.
     * Now with duplicate prevention - will not create if pending/in_progress job exists.
     *
     * @param string $type The type of the job (post or term).
     * @param int    $id_from The ID of the post or term.
     * @param string $lang_from The language code from which the job is.
     * @param string $lang_to The language code to which the job is.
     * @param string $content_type The content type (post_type or taxonomy).
     * @return Job The created or existing job.
     */
    public function create( string $type, int $id_from, string $lang_from, string $lang_to, string $content_type ): Job {
        // DUPLICATE PREVENTION: Check if active job already exists.
        $existing = $this->find_active_job_for_content( $type, $id_from, $lang_from, $lang_to );
        if ( null !== $existing ) {
            return $existing; // Return existing job instead of creating duplicate.
        }

        global $wpdb;

        $wpdb->insert(
            $this->get_table_name(),
            array(
                'content_type' => $content_type,
                'created_at'   => \time(),
                'id_from'      => $id_from,
                'lang_from'    => $lang_from,
                'lang_to'      => $lang_to,
                'run_id'       => null,
                'started_at'   => 0,
                'status'       => JobStatus::Pending->value,
                'type'         => $type,
            ),
        );

        $job_id = $wpdb->insert_id;
        return $this->find( $job_id );
    }

    /**
     * Find an active (pending or in_progress) job for specific content and language.
     * Used for duplicate prevention.
     *
     * @param string $type Job type (post or term).
     * @param int    $id_from Source content ID.
     * @param string $lang_from Source language.
     * @param string $lang_to Target language.
     * @return Job|null The active job or null if none found.
     */
    public function find_active_job_for_content( string $type, int $id_from, string $lang_from, string $lang_to ): ?Job {
        global $wpdb;
        $table = $this->get_table_name();

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                WHERE type = %s
                  AND id_from = %d
                  AND lang_from = %s
                  AND lang_to = %s
                  AND status IN (%s, %s)
                ORDER BY created_at DESC
                LIMIT 1",
                $type,
                $id_from,
                $lang_from,
                $lang_to,
                JobStatus::Pending->value,
                JobStatus::InProgress->value,
            ),
            ARRAY_A,
        );

        if ( null === $row ) {
            return null;
        }

        return $this->hydrate_job( $row );
    }

    /**
     * Create a completed job (for importing existing translations).
     *
     * @param string   $type Job type (post or term).
     * @param int      $id_from Source ID.
     * @param string   $lang_from Source language.
     * @param string   $lang_to Target language.
     * @param string   $content_type Content type (post_type or taxonomy).
     * @param int|null $id_to Target ID (optional, for bidirectional cleanup).
     * @return Job The created job.
     */
    public function create_completed( string $type, int $id_from, string $lang_from, string $lang_to, string $content_type, ?int $id_to = null ): Job {
        global $wpdb;

        $now = \time();

        $wpdb->insert(
            $this->get_table_name(),
            array(
                'content_type' => $content_type,
                'created_at'   => $now,
                'id_from'      => $id_from,
                'id_to'        => $id_to,
                'lang_from'    => $lang_from,
                'lang_to'      => $lang_to,
                'run_id'       => null, // No run = manual translation.
                'started_at'   => $now,
                'status'       => JobStatus::Completed->value,
                'type'         => $type,
            ),
        );

        $job_id = $wpdb->insert_id;
        return $this->find( $job_id );
    }

    /**
     * Find a job by ID.
     *
     * @param int $id The job ID.
     * @return Job The job instance.
     * @throws \Exception If job not found.
     */
    public function find( int $id ): Job {
        global $wpdb;

        $table_name = $this->get_table_name();
        $row        = $wpdb->get_row(
            $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table_name, $id ),
            ARRAY_A,
        );

        if ( null === $row ) {
            throw new \Exception( \sprintf( 'Job not found with ID: %d', \esc_html( $id ) ) );
        }

        return $this->hydrate_job( $row );
    }

    /**
     * Check if a job exists with given criteria.
     *
     * @param string    $type The type of the job (post or term).
     * @param int       $id_from The ID of the post or term.
     * @param string    $lang_from The language code from which the job is.
     * @param string    $lang_to The language code to which the job is.
     * @param JobStatus $status The status of the job.
     * @return Job|false The job or false if it does not exist.
     */
    public function exists( string $type, int $id_from, string $lang_from, string $lang_to, JobStatus $status = JobStatus::Pending ): Job|false {
        global $wpdb;

        $table_name = $this->get_table_name();

        $job = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT id FROM %i WHERE type = %s AND id_from = %d AND lang_from = %s AND lang_to = %s AND status = %s',
                $table_name,
                $type,
                $id_from,
                $lang_from,
                $lang_to,
                $status->value,
            ),
        );

        if ( null === $job ) {
            return false;
        }

        return $this->find( (int) $job->id );
    }

    /**
     * Find jobs by query builder.
     *
     * @param Job_Query $query The query builder.
     * @return array<int, Job> Array of hydrated Job entities.
     */
    public function find_by( Job_Query $query ): array {
        $results = $query->fetch();

        $jobs = array();
        foreach ( $results as $row ) {
            // Convert stdClass to array for hydration
            $row_array = (array) $row;
            $jobs[]    = $this->hydrate_job( $row_array );
        }

        return $jobs;
    }

    /**
     * Check if any job exists for content and language (any status or specific statuses).
     * Optimized single query to replace N+1 status checks.
     *
     * @param string             $type The type of the job (post or term).
     * @param int                $id_from The ID of the post or term.
     * @param string             $lang_from The language code from which the job is.
     * @param string             $lang_to The language code to which the job is.
     * @param array<string>|null $statuses Optional array of status values to filter by.
     * @return bool True if at least one job exists (matching criteria), false otherwise.
     */
    public function has_jobs_for_content_language(
        string $type,
        int $id_from,
        string $lang_from,
        string $lang_to,
        ?array $statuses = null,
    ): bool {
        global $wpdb;

        $table_name = $this->get_table_name();

        // Build query with optional status filter
        if ( null !== $statuses && \count( $statuses ) > 0 ) {
            $placeholders = \implode( ',', \array_fill( 0, \count( $statuses ), '%s' ) );
            $query        = "SELECT COUNT(*) FROM %i WHERE type = %s AND id_from = %d AND lang_from = %s AND lang_to = %s AND status IN ({$placeholders}) LIMIT 1";
            $params       = \array_merge(
                array( $table_name, $type, $id_from, $lang_from, $lang_to ),
                $statuses,
            );
        } else {
            $query  = 'SELECT COUNT(*) FROM %i WHERE type = %s AND id_from = %d AND lang_from = %s AND lang_to = %s LIMIT 1';
            $params = array( $table_name, $type, $id_from, $lang_from, $lang_to );
        }

        $count = $wpdb->get_var(
            $wpdb->prepare( $query, ...$params ),
        );

        return $count > 0;
    }

    /**
     * Save a job to the database.
     *
     * @param Job $job The job to save.
     */
    public function save( Job $job ): void {
        global $wpdb;

        $wpdb->update(
            $this->get_table_name(),
            array(
                'completed_at' => $job->get_completed_at(),
                'id_from'      => $job->get_id_from(),
                'id_to'        => $job->get_id_to(),
                'lang_from'    => $job->get_lang_from(),
                'lang_to'      => $job->get_lang_to(),
                'run_id'       => $job->get_run_id(),
                'started_at'   => $job->get_started_at(),
                'status'       => $job->get_status()->value,
                'type'         => $job->get_type(),
            ),
            array( 'id' => $job->get_id() ),
        );

        // Trigger cascade to update parent run status.
        \do_action( 'pllat_job_saved', $job );
    }

    /**
     * Delete a job from the database.
     *
     * @param Job $job The job to delete.
     */
    public function delete( Job $job ): void {
        global $wpdb;

        $wpdb->delete(
            $this->get_table_name(),
            array( 'id' => $job->get_id() ),
        );
    }

    /**
     * Get the table name for jobs.
     *
     * @return string The table name with prefix.
     */
    public function get_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * Find all jobs regardless of type.
     *
     * @return array<int, Job> Array of all jobs.
     */
    public function find_all(): array {
        global $wpdb;

        $table_name = $this->get_table_name();
        $results    = $wpdb->get_results(
            $wpdb->prepare( 'SELECT * FROM %i', $table_name ),
            ARRAY_A,
        );

        $jobs = array();
        foreach ( $results as $row ) {
            $jobs[] = $this->hydrate_job( $row );
        }

        return $jobs;
    }

    /**
     * Create a new query builder for jobs.
     * Factory method for convenience.
     *
     * @param string $type The type (post or term).
     * @return Job_Query The query builder.
     */
    public function query( string $type ): Job_Query {
        return new Job_Query( $type );
    }

    /**
     * Get map of completed jobs for batch existence checking.
     * Returns nested array: [source_id][lang_to] => true
     *
     * @param string             $type Type (post or term).
     * @param array<int>         $source_ids Source content IDs.
     * @param string             $lang_from Source language.
     * @param array<int, string> $langs_to Target languages.
     * @return array<int, array<string, bool>> Nested map.
     */
    public function get_completed_job_map( string $type, array $source_ids, string $lang_from, array $langs_to ): array {
        if ( 0 === \count( $source_ids ) || 0 === \count( $langs_to ) ) {
            return array();
        }

        global $wpdb;
        $table = $this->get_table_name();

        // Build placeholders.
        $placeholders_ids   = \implode( ',', \array_fill( 0, \count( $source_ids ), '%d' ) );
        $placeholders_langs = \implode( ',', \array_fill( 0, \count( $langs_to ), '%s' ) );

        // Single query instead of N×M queries.
        $query = $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "SELECT id_from, lang_to FROM {$table}
			WHERE type = %s
			  AND id_from IN ({$placeholders_ids})
			  AND lang_from = %s
			  AND lang_to IN ({$placeholders_langs})
			  AND status = %s",
            \array_merge(
                array( $type ),
                $source_ids,
                array( $lang_from ),
                $langs_to,
                array( JobStatus::Completed->value ),
            ),
        );

        $results = $wpdb->get_results( $query, ARRAY_A );

        // Build nested map.
        $map = array();
        foreach ( $results as $row ) {
            $map[ (int) $row['id_from'] ][ $row['lang_to'] ] = true;
        }

        return $map;
    }

    /**
     * Check if a completed job exists for specific translation.
     * Used by Translation_Sync_Handler to avoid duplicate imports.
     *
     * @param string $type      Type (post or term).
     * @param int    $id_from   Source content ID.
     * @param string $lang_from Source language.
     * @param string $lang_to   Target language.
     * @return bool True if completed job exists.
     */
    public function has_completed_job( string $type, int $id_from, string $lang_from, string $lang_to ): bool {
        global $wpdb;

        $count = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM %i
                WHERE type = %s
                  AND id_from = %d
                  AND lang_from = %s
                  AND lang_to = %s
                  AND status = %s',
                $this->get_table_name(),
                $type,
                $id_from,
                $lang_from,
                $lang_to,
                JobStatus::Completed->value,
            ),
        );

        return $count > 0;
    }

    /**
     * Get count of jobs per source ID using SQL GROUP BY.
     * Returns: [source_id => count]
     *
     * @param string             $type Type (post or term).
     * @param string             $lang_from Source language.
     * @param array<int, string> $langs_to Target languages.
     * @return array<int, int> Map of source_id => count.
     */
    public function get_job_counts_by_source( string $type, string $lang_from, array $langs_to ): array {
        if ( 0 === \count( $langs_to ) ) {
            return array();
        }

        global $wpdb;
        $table = $this->get_table_name();

        $placeholders = \implode( ',', \array_fill( 0, \count( $langs_to ), '%s' ) );

        $query = $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "SELECT id_from, COUNT(*) as count
			FROM {$table}
			WHERE type = %s
			  AND lang_from = %s
			  AND lang_to IN ({$placeholders})
			GROUP BY id_from",
            \array_merge( array( $type, $lang_from ), $langs_to ),
        );

        $results = $wpdb->get_results( $query, ARRAY_A );

        $counts = array();
        foreach ( $results as $row ) {
            $counts[ (int) $row['id_from'] ] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Find the latest job for specific content and language pair.
     * Uses ORDER BY created_at DESC to handle multiple jobs per language.
     *
     * @param string $type Job type (post or term).
     * @param int    $id_from Source content ID.
     * @param string $lang_from Source language.
     * @param string $lang_to Target language.
     * @return Job|null The latest job or null if not found.
     */
    public function find_latest_by_content_and_language( string $type, int $id_from, string $lang_from, string $lang_to ): ?Job {
        global $wpdb;
        $table = $this->get_table_name();

        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM %i
				WHERE type = %s
				  AND id_from = %d
				  AND lang_from = %s
				  AND lang_to = %s
				ORDER BY created_at DESC
				LIMIT 1',
                $table,
                $type,
                $id_from,
                $lang_from,
                $lang_to,
            ),
            ARRAY_A,
        );

        if ( null === $row ) {
            return null;
        }

        return $this->hydrate_job( $row );
    }

    /**
     * Find the latest COMPLETED job for content and language.
     * Used to show "Last translated" date even when newer pending job exists.
     *
     * @param string $type Content type (post or term).
     * @param int    $id_from Source content ID.
     * @param string $lang_from Source language.
     * @param string $lang_to Target language.
     * @return Job|null The latest completed job or null.
     */
    public function find_latest_completed_by_content_and_language( string $type, int $id_from, string $lang_from, string $lang_to ): ?Job {
        global $wpdb;
        $table = $this->get_table_name();

        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM %i
				WHERE type = %s
				  AND id_from = %d
				  AND lang_from = %s
				  AND lang_to = %s
				  AND status = %s
				ORDER BY completed_at DESC
				LIMIT 1',
                $table,
                $type,
                $id_from,
                $lang_from,
                $lang_to,
                JobStatus::Completed->value,
            ),
            ARRAY_A,
        );

        if ( null === $row ) {
            return null;
        }

        return $this->hydrate_job( $row );
    }

    /**
     * Find all jobs for specific content (all languages).
     * Used to check if content has been discovered.
     *
     * @param string $type Job type (post or term).
     * @param int    $id_from Source content ID.
     * @return array<int, Job> Array of jobs.
     */
    public function find_all_by_content( string $type, int $id_from ): array {
        global $wpdb;
        $table = $this->get_table_name();

        $results = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM %i
				WHERE type = %s
				  AND id_from = %d
				ORDER BY created_at DESC',
                $table,
                $type,
                $id_from,
            ),
            ARRAY_A,
        );

        $jobs = array();
        foreach ( $results as $row ) {
            $jobs[] = $this->hydrate_job( $row );
        }

        return $jobs;
    }

    /**
     * Find all jobs for a run.
     *
     * @param int $run_id The run ID.
     * @return array<int, Job> Array of jobs.
     */
    public function find_all_by_run_id( int $run_id ): array {
        global $wpdb;
        $table = $this->get_table_name();

        $results = $wpdb->get_results(
            $wpdb->prepare( 'SELECT * FROM %i WHERE run_id = %d', $table, $run_id ),
            ARRAY_A,
        );

        $jobs = array();
        foreach ( $results as $row ) {
            $jobs[] = $this->hydrate_job( $row );
        }

        return $jobs;
    }

    /**
     * Find all jobs targeting specific content (all languages).
     * Used for bidirectional job cleanup when content is deleted.
     *
     * @param string $type Job type (post or term).
     * @param int    $id_to Target content ID.
     * @return array<int, Job> Array of jobs.
     */
    public function find_all_by_target_id( string $type, int $id_to ): array {
        global $wpdb;
        $table = $this->get_table_name();

        $results = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM %i
				WHERE type = %s
				  AND id_to = %d
				ORDER BY created_at DESC',
                $table,
                $type,
                $id_to,
            ),
            ARRAY_A,
        );

        $jobs = array();
        foreach ( $results as $row ) {
            $jobs[] = $this->hydrate_job( $row );
        }

        return $jobs;
    }

    /**
     * Atomically claim the next pending job for a run.
     * Uses MySQL row locking to prevent race conditions in concurrent processing.
     *
     * @param int $run_id The run ID to claim a job for.
     * @return Job|null The claimed job or null if no jobs available.
     */
    public function claim_next_job_for_run( int $run_id ): ?Job {
        global $wpdb;
        $table = $this->get_table_name();

        // Start transaction for atomic claim operation
        $wpdb->query( 'START TRANSACTION' );

        try {
            // SELECT FOR UPDATE locks the row atomically
            // Only one worker can claim this job even with concurrent requests
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$table}
                    WHERE run_id = %d
                      AND status = %s
                    ORDER BY id ASC
                    LIMIT 1
                    FOR UPDATE",
                    $run_id,
                    JobStatus::Pending->value,
                ),
                ARRAY_A,
            );

            if ( null === $row ) {
                $wpdb->query( 'COMMIT' );
                return null;
            }

            $job_id = (int) $row['id'];

            // Immediately mark as in_progress to claim it
            $wpdb->update(
                $table,
                array(
                    'started_at' => \time(),
                    'status'     => JobStatus::InProgress->value,
                ),
                array( 'id' => $job_id ),
                array( '%s', '%d' ),
                array( '%d' ),
            );

            $wpdb->query( 'COMMIT' );

            // Return the claimed job
            return $this->find( $job_id );

        } catch ( \Exception $e ) {
            $wpdb->query( 'ROLLBACK' );
            throw $e;
        }
    }

    /**
     * Check if any in-progress jobs exist for a run that started longer than timeout.
     * Used to detect stale jobs that need recovery.
     *
     * @param int $run_id The run ID to check.
     * @param int $timeout Timeout in seconds (default: 600 = 10 minutes).
     * @return array<int> Array of stale job IDs.
     */
    public function find_stale_job_ids_for_run( int $run_id, int $timeout = 600 ): array {
        global $wpdb;
        $table            = $this->get_table_name();
        $cutoff_timestamp = \time() - $timeout;

        $results = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM {$table}
                WHERE run_id = %d
                  AND status = %s
                  AND started_at > 0
                  AND started_at < %d
                ORDER BY id ASC",
                $run_id,
                JobStatus::InProgress->value,
                $cutoff_timestamp,
            ),
        );

        return \array_map( 'intval', $results );
    }

    /**
     * Reset a stale job back to pending status for retry.
     * Used in smart recovery to give jobs another chance.
     *
     * @param int $job_id The job ID to reset.
     * @return bool True on success, false on failure.
     */
    public function reset_to_pending( int $job_id ): bool {
        global $wpdb;

        $result = $wpdb->update(
            $this->get_table_name(),
            array(
                'started_at' => 0,
                'status'     => JobStatus::Pending->value,
            ),
            array( 'id' => $job_id ),
            array( '%s', '%d' ),
            array( '%d' ),
        );

        return false !== $result;
    }

    /**
     * Find jobs with specific statuses for a run.
     * Used for analytics and cleanup operations.
     *
     * @param int              $run_id The run ID.
     * @param array<JobStatus> $statuses Array of statuses to match.
     * @param int|null         $limit Optional limit.
     * @return array<int, Job> Array of jobs.
     */
    public function find_by_run_and_statuses( int $run_id, array $statuses, ?int $limit = null ): array {
        if ( 0 === \count( $statuses ) ) {
            return array();
        }

        global $wpdb;
        $table = $this->get_table_name();

        $status_placeholders = \implode( ',', \array_fill( 0, \count( $statuses ), '%s' ) );
        $status_values       = \array_map( static fn( $s ) => $s->value, $statuses );

        $query = "SELECT * FROM {$table}
            WHERE run_id = %d
              AND status IN ({$status_placeholders})
            ORDER BY id ASC";

        if ( null !== $limit ) {
            $query .= $wpdb->prepare( ' LIMIT %d', $limit );
        }

        $results = $wpdb->get_results(
            $wpdb->prepare(
                $query,
                \array_merge( array( $run_id ), $status_values ),
            ),
            ARRAY_A,
        );

        $jobs = array();
        foreach ( $results as $row ) {
            $jobs[] = $this->hydrate_job( $row );
        }

        return $jobs;
    }

    /**
     * Find jobs marked completed but still have incomplete tasks.
     * Used for consistency validation and auto-repair.
     *
     * @param int $run_id The run ID to check.
     * @return array<array{id: int, incomplete_tasks: int}> Array of job IDs with incomplete task counts.
     */
    public function find_inconsistent_completed_jobs( int $run_id ): array {
        global $wpdb;

        $jobs_table  = $this->get_table_name();
        $tasks_table = $wpdb->prefix . 'pllat_tasks';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT j.id,
                    SUM(CASE WHEN t.status NOT IN ('completed', 'exhausted') THEN 1 ELSE 0 END) as incomplete_tasks
                FROM {$jobs_table} j
                LEFT JOIN {$tasks_table} t ON j.id = t.job_id
                WHERE j.run_id = %d AND j.status = 'completed'
                GROUP BY j.id
                HAVING incomplete_tasks > 0",
                $run_id,
            ),
            ARRAY_A,
        );

        // Convert to simple array of job info.
        return \array_map(
            static fn( $row ) => array(
                'id'               => (int) $row['id'],
                'incomplete_tasks' => (int) $row['incomplete_tasks'],
            ),
            $results,
        );
    }

    /**
     * Find jobs that have all tasks completed but are not marked as completed.
     * Used for consistency validation and auto-repair.
     *
     * @param int $run_id The run ID to check.
     * @return array<array{id: int, task_count: int}> Array of job IDs ready to complete.
     */
    public function find_jobs_ready_to_complete( int $run_id ): array {
        global $wpdb;

        $jobs_table  = $this->get_table_name();
        $tasks_table = $wpdb->prefix . 'pllat_tasks';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT j.id, COUNT(t.id) as task_count,
                    SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks
                FROM {$jobs_table} j
                LEFT JOIN {$tasks_table} t ON j.id = t.job_id
                WHERE j.run_id = %d AND j.status IN ('pending', 'in_progress')
                GROUP BY j.id
                HAVING task_count > 0 AND completed_tasks = task_count",
                $run_id,
            ),
            ARRAY_A,
        );

        // Convert to simple array of job info.
        return \array_map(
            static fn( $row ) => array(
                'id'         => (int) $row['id'],
                'task_count' => (int) $row['task_count'],
            ),
            $results,
        );
    }

    /**
     * Reset job to pending status for reprocessing.
     * Used by consistency validation to fix jobs marked completed prematurely.
     *
     * @param int $job_id The job ID to reset.
     * @return void
     */
    public function reset_job_to_pending( int $job_id ): void {
        global $wpdb;

        $wpdb->update(
            $this->get_table_name(),
            array(
                'started_at' => 0,
                'status'     => JobStatus::Pending->value,
            ),
            array( 'id' => $job_id ),
            array( '%s', '%d' ),
            array( '%d' ),
        );
    }

    /**
     * Mark job as completed with timestamp.
     * Used by consistency validation for jobs that have all tasks done.
     *
     * @param int $job_id The job ID to complete.
     * @return void
     */
    public function mark_job_completed( int $job_id ): void {
        global $wpdb;

        $wpdb->update(
            $this->get_table_name(),
            array(
                'completed_at' => \time(),
                'status'       => JobStatus::Completed->value,
            ),
            array( 'id' => $job_id ),
            array( '%s', '%d' ),
            array( '%d' ),
        );
    }

    /**
     * Bulk assign jobs to a run using single UPDATE query.
     * More efficient than individual updates for large batches.
     *
     * @param array<int> $job_ids The job IDs to assign.
     * @param int        $run_id The run ID to assign to.
     * @return void
     */
    public function bulk_assign_to_run( array $job_ids, int $run_id ): void {
        // Guard: Empty array.
        if ( empty( $job_ids ) ) {
            return;
        }

        global $wpdb;
        $table = $this->get_table_name();

        // Use single UPDATE with IN clause for performance.
        $placeholders = \implode( ',', \array_fill( 0, \count( $job_ids ), '%d' ) );

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET run_id = %d WHERE id IN ({$placeholders})",
                \array_merge( array( $run_id ), $job_ids ),
            ),
        );
    }

    /**
     * Bulk update job statuses using single UPDATE query.
     * Used for atomic job claiming - marks multiple jobs as in_progress at once.
     *
     * @param array<int> $job_ids The job IDs to update.
     * @param JobStatus  $status The new status.
     * @param int        $started_at The started_at timestamp (for in_progress status).
     * @return void
     */
    public function bulk_update_status( array $job_ids, JobStatus $status, int $started_at = 0 ): void {
        // Guard: Empty array.
        if ( empty( $job_ids ) ) {
            return;
        }

        global $wpdb;
        $table = $this->get_table_name();

        // Use single UPDATE with IN clause for performance.
        $placeholders = \implode( ',', \array_fill( 0, \count( $job_ids ), '%d' ) );

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET status = %s, started_at = %d WHERE id IN ({$placeholders})",
                \array_merge( array( $status->value, $started_at ), $job_ids ),
            ),
        );
    }

    /**
     * Hydrate a Job entity from database row.
     *
     * @param array<string, mixed> $row Database row data.
     * @return Job The hydrated job.
     */
    private function hydrate_job( array $row ): Job {
        // Create job without calling load().
        $job = new Job( (int) $row['id'] );

        // Set properties using setters where available
        $job->set_run_id( $row['run_id'] ? (int) $row['run_id'] : null );
        $job->set_id_to( isset( $row['id_to'] ) && $row['id_to'] ? (int) $row['id_to'] : null );
        $job->set_status( $row['status'] ? JobStatus::from( $row['status'] ) : JobStatus::Pending );

        // For protected properties without setters, use reflection.
        $reflection = new \ReflectionClass( $job );

        $this->set_property( $reflection, $job, 'created_at', (int) $row['created_at'] );
        $this->set_property( $reflection, $job, 'started_at', (int) ( $row['started_at'] ?? 0 ) );
        $this->set_property( $reflection, $job, 'completed_at', (int) ( $row['completed_at'] ?? 0 ) );
        $this->set_property( $reflection, $job, 'type', $row['type'] );
        $this->set_property( $reflection, $job, 'content_type', $row['content_type'] );
        $this->set_property( $reflection, $job, 'id_from', (int) $row['id_from'] );
        $this->set_property( $reflection, $job, 'lang_from', $row['lang_from'] );
        $this->set_property( $reflection, $job, 'lang_to', $row['lang_to'] );

        return $job;
    }

    /**
     * Set a protected property on an object using reflection.
     *
     * @param \ReflectionClass $reflection The reflection class.
     * @param Job              $job The job object.
     * @param string           $property The property name.
     * @param mixed            $value The value to set.
     */
    private function set_property( \ReflectionClass $reflection, Job $job, string $property, mixed $value ): void {
        $prop = $reflection->getProperty( $property );
        $prop->setAccessible( true );
        $prop->setValue( $job, $value );
    }
}
