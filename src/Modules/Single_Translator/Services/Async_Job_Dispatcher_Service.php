<?php
/**
 * Async_Job_Dispatcher_Service class file.
 *
 * @package Polylang AI Automatic Translation
 * @subpackage Single_Translator
 */

declare(strict_types=1);

namespace PLLAT\Single_Translator\Services;

use PLLAT\Translator\Models\Run;
use PLLAT\Translator\Repositories\Job_Repository;

/**
 * Service for managing async action dispatching for translation jobs.
 *
 * Responsibilities:
 * - Enqueue jobs for async processing via Action Scheduler
 * - Cancel pending async actions for a run
 * - Query status of async actions
 *
 * This service is reusable across different modules that need
 * async job processing (single translation, bulk, etc.).
 */
class Async_Job_Dispatcher_Service {
    /**
     * Hook name for async job processing.
     *
     * @var string
     */
    private const HOOK_PROCESS_JOB = 'pllat_process_single_job';

    /**
     * Group prefix for run-specific action grouping.
     *
     * @var string
     */
    private const GROUP_PREFIX = 'pllat-single-run-';

    /**
     * Constructor.
     *
     * @param Job_Repository $job_repository Job repository.
     */
    public function __construct(
        private Job_Repository $job_repository,
    ) {
    }

    /**
     * Enqueue all jobs for a run for async processing.
     *
     * Each job is enqueued as a separate async action, which will be
     * processed by the Job_Processor_Handler.
     *
     * Uses run-specific groups for easy querying and cancellation.
     *
     * @param Run $run The run whose jobs should be enqueued.
     * @return void
     */
    public function enqueue_jobs_for_run( Run $run ): void {
        $jobs  = $this->job_repository->find_all_by_run_id( $run->get_id() );
        $group = $this->get_group_for_run( $run->get_id() );

        foreach ( $jobs as $job ) {
            \as_enqueue_async_action(
                self::HOOK_PROCESS_JOB,
                array( 'job_id' => $job->get_id() ),
                $group,
            );
        }
    }

    /**
     * Cancel all pending async actions for a run.
     *
     * This is used when a user cancels a translation run.
     * Already running or completed actions cannot be cancelled.
     *
     * @param int $run_id The run ID whose actions should be cancelled.
     * @return void
     */
    public function cancel_actions_for_run( int $run_id ): void {
        $group = $this->get_group_for_run( $run_id );

        // Cancel all actions in this group (hook='', args=[] = match all).
        \as_unschedule_all_actions( '', array(), $group );
    }

    /**
     * Check if a run has any active (pending or running) async actions.
     *
     * @param int $run_id The run ID to check.
     * @return bool True if run has active actions, false otherwise.
     */
    public function has_active_actions( int $run_id ): bool {
        $group = $this->get_group_for_run( $run_id );

        return \as_has_scheduled_action(
            self::HOOK_PROCESS_JOB,
            null, // Match any args.
            $group,
        );
    }

    /**
     * Get the count of pending async actions for a run.
     *
     * @param int $run_id The run ID to check.
     * @return int Number of pending actions.
     */
    public function get_pending_count( int $run_id ): int {
        $group = $this->get_group_for_run( $run_id );

        $action_ids = \as_get_scheduled_actions(
            array(
                'group'    => $group,
                'hook'     => self::HOOK_PROCESS_JOB,
                'per_page' => 999,
                'status'   => \ActionScheduler_Store::STATUS_PENDING,
            ),
            'ids',
        );

        return \count( $action_ids );
    }

    /**
     * Get the count of running async actions for a run.
     *
     * @param int $run_id The run ID to check.
     * @return int Number of running actions.
     */
    public function get_running_count( int $run_id ): int {
        $group = $this->get_group_for_run( $run_id );

        $action_ids = \as_get_scheduled_actions(
            array(
                'group'    => $group,
                'hook'     => self::HOOK_PROCESS_JOB,
                'per_page' => 999,
                'status'   => \ActionScheduler_Store::STATUS_RUNNING,
            ),
            'ids',
        );

        return \count( $action_ids );
    }

    /**
     * Get run-specific action group name.
     *
     * Using run-specific groups allows:
     * - Easy bulk cancellation per run
     * - Isolated queries per run
     * - No cross-run interference
     *
     * @param int $run_id The run ID.
     * @return string Group name for this run.
     */
    private function get_group_for_run( int $run_id ): string {
        return self::GROUP_PREFIX . $run_id;
    }
}
