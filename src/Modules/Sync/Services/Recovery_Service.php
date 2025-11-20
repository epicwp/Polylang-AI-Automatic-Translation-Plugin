<?php
/**
 * Recovery_Service class file.
 *
 * @package Polylang AI Automatic Translation
 * @subpackage Sync
 */

declare(strict_types=1);

namespace PLLAT\Sync\Services;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
use PLLAT\Sync\Constants\Sync_Constants;
use PLLAT\Sync\Enums\Recovery_Strategy;
use PLLAT\Sync\Models\Recovery_Result;
use PLLAT\Translator\Enums\JobStatus;
use PLLAT\Translator\Repositories\Job_Repository;
use PLLAT\Translator\Repositories\Task_Repository;

/**
 * Handles recovery of stale and failed jobs.
 *
 * Determines best recovery strategy and executes it:
 * - Finish: All tasks done, apply translations
 * - Reset: Partial progress, retry from current state
 * - Fail: Exhausted tasks or too many failures
 */
class Recovery_Service {
    /**
     * Constructor.
     *
     * @param Job_Repository     $job_repository     Job repository.
     * @param Task_Repository    $task_repository    Task repository.
     * @param Completion_Service $completion_service Completion service for finishing jobs.
     */
    public function __construct(
        private Job_Repository $job_repository,
        private Task_Repository $task_repository,
    ) {
    }

    /**
     * Recover all stale jobs for a run.
     *
     * Analyzes each stale job and applies appropriate recovery strategy.
     *
     * @param int $run_id The run ID to recover jobs for.
     * @return Recovery_Result Result summary with counts per strategy.
     */
    public function recover_stale_jobs_for_run( int $run_id ): Recovery_Result {
        $stale_job_ids = $this->find_stale_job_ids( $run_id );
        $result        = new Recovery_Result();

        foreach ( $stale_job_ids as $job_id ) {
            $strategy = $this->determine_recovery_strategy( $job_id );
            $this->execute_recovery( $job_id, $strategy );
            $result->record( $strategy );
        }

        return $result;
    }

    /**
     * Find all stale job IDs for a run.
     *
     * Stale = in_progress status but no updates for STALE_JOB_TIMEOUT seconds.
     *
     * @param int $run_id The run ID.
     * @return array<int> Array of stale job IDs.
     */
    private function find_stale_job_ids( int $run_id ): array {
        return $this->job_repository->find_stale_job_ids_for_run( $run_id, Sync_Constants::STALE_JOB_TIMEOUT );
    }

    /**
     * Determine best recovery strategy for a job.
     *
     * Strategy decision tree:
     * 1. Has exhausted tasks? → Fail
     * 2. All tasks completed? → Finish
     * 3. Otherwise → Reset (partial progress or no progress)
     *
     * @param int $job_id The job ID to analyze.
     * @return Recovery_Strategy The recovery strategy.
     */
    private function determine_recovery_strategy( int $job_id ): Recovery_Strategy {
        $stats = $this->get_task_statistics( $job_id );

        if ( $stats['has_exhausted_tasks'] ) {
            return Recovery_Strategy::Fail;
        }

        if ( $stats['all_tasks_completed'] ) {
            return Recovery_Strategy::Finish;
        }

        return Recovery_Strategy::Reset;
    }

    /**
     * Get task statistics for a job to inform recovery decision.
     *
     * @param int $job_id The job ID.
     * @return array{total: int, completed: int, exhausted: int, all_tasks_completed: bool, has_exhausted_tasks: bool} Statistics.
     */
    private function get_task_statistics( int $job_id ): array {
        global $wpdb;

        $task_table = $this->task_repository->get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $stats = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
					COUNT(*) as total,
					SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
					SUM(CASE WHEN attempts >= %d THEN 1 ELSE 0 END) as exhausted
				FROM {$task_table}
				WHERE job_id = %d",
                Sync_Constants::MAX_TASK_ATTEMPTS,
                $job_id,
            ),
        );

        if ( ! $stats || 0 === (int) $stats->total ) {
            return array(
                'all_tasks_completed' => false,
                'completed'           => 0,
                'exhausted'           => 0,
                'has_exhausted_tasks' => false,
                'total'               => 0,
            );
        }

        return array(
            'all_tasks_completed' => (int) $stats->completed === (int) $stats->total,
            'completed'           => (int) $stats->completed,
            'exhausted'           => (int) $stats->exhausted,
            'has_exhausted_tasks' => (int) $stats->exhausted > 0,
            'total'               => (int) $stats->total,
        );
    }

    /**
     * Execute the determined recovery strategy.
     *
     * @param int               $job_id   The job ID to recover.
     * @param Recovery_Strategy $strategy The strategy to execute.
     * @return void
     */
    private function execute_recovery( int $job_id, Recovery_Strategy $strategy ): void {
        match ( $strategy ) {
            Recovery_Strategy::Finish => $this->finish_stale_job( $job_id ),
            Recovery_Strategy::Reset => $this->reset_job_to_pending( $job_id ),
            Recovery_Strategy::Fail => $this->fail_job( $job_id ),
        };
    }

    /**
     * Finish a stale job that has all tasks completed.
     *
     * Uses Completion_Service to apply translations.
     * If completion fails, marks job as failed instead.
     *
     * @param int $job_id The job ID to finish.
     * @return void
     */
    private function finish_stale_job( int $job_id ): void {
        try {
            $job = $this->job_repository->find( $job_id );
            \do_action( 'pllat_before_job_completion', $job );
            $job->complete();
            \do_action( 'pllat_after_job_completion', $job );
        } catch ( \Exception $e ) {
            // Completion failed - mark as failed instead.
            $this->fail_job( $job_id );
            $this->log_recovery_error( $job_id, 'finish', $e );
        }
    }

    /**
     * Reset job to pending status for retry.
     *
     * Used when job has partial progress or got stuck.
     * Cascade system will pick it up and retry.
     *
     * @param int $job_id The job ID to reset.
     * @return void
     */
    private function reset_job_to_pending( int $job_id ): void {
        $this->job_repository->reset_to_pending( $job_id );
    }

    /**
     * Mark job as permanently failed.
     *
     * Used when job has exhausted tasks or failed too many times.
     *
     * @param int $job_id The job ID to fail.
     * @return void
     */
    private function fail_job( int $job_id ): void {
        global $wpdb;

        // Direct DB update for failed jobs (no cascade needed).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->update(
            $this->job_repository->get_table_name(),
            array(
                'completed_at' => \time(),
                'status'       => JobStatus::Failed->value,
            ),
            array( 'id' => $job_id ),
            array( '%d', '%s' ),
            array( '%d' ),
        );
    }

    /**
     * Log recovery error without breaking execution.
     *
     * @param int        $job_id   The job ID.
     * @param string     $strategy The strategy that failed.
     * @param \Exception $e        The exception.
     * @return void
     */
    private function log_recovery_error( int $job_id, string $strategy, \Exception $e ): void {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        \error_log(
            \sprintf(
                'Recovery error (job %d, strategy %s): %s',
                $job_id,
                $strategy,
                $e->getMessage(),
            ),
        );
    }
}
