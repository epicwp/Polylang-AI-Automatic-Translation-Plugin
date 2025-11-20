<?php
/**
 * Cascade_Handler class file.
 *
 * @package Polylang AI Automatic Translation
 * @subpackage Sync
 */

declare(strict_types=1);

namespace PLLAT\Sync\Handlers;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
use PLLAT\Sync\Constants\Sync_Constants;
use PLLAT\Translator\Enums\JobStatus;
use PLLAT\Translator\Enums\RunStatus;
use PLLAT\Translator\Enums\TaskStatus;
use PLLAT\Translator\Models\Job;
use PLLAT\Translator\Models\Run;
use PLLAT\Translator\Models\Task;
use PLLAT\Translator\Repositories\Job_Repository;
use PLLAT\Translator\Repositories\Run_Repository;
use PLLAT\Translator\Repositories\Task_Repository;
use XWP\DI\Decorators\Action;
use XWP\DI\Decorators\Handler;

/**
 * Handles automatic status cascade from tasks to jobs to runs.
 *
 * This is the foundation of the sync system. All status updates flow through here.
 *
 * Cascade rules:
 * - Task changes → Evaluate all tasks → Update job status
 * - Job changes → Evaluate all jobs → Update run status
 *
 * Priority 5: Must run early, before Completion_Handler (priority 10).
 */
#[Handler(
    tag: 'init',
    priority: 16,
    context: Handler::CTX_CRON | Handler::CTX_ADMIN | Handler::CTX_AJAX | Handler::CTX_CLI | Handler::CTX_REST,
)]
class Cascade_Handler {
    /**
     * Prevent infinite loops - track cascaded entities in this request.
     *
     * @var array<string, array<int>>
     */
    private array $cascaded = array(
        'jobs' => array(),
        'runs' => array(),
    );

    /**
     * Constructor.
     *
     * @param Task_Repository $task_repository Task repository.
     * @param Job_Repository  $job_repository  Job repository.
     * @param Run_Repository  $run_repository  Run repository.
     */
    public function __construct(
        private Task_Repository $task_repository,
        private Job_Repository $job_repository,
        private Run_Repository $run_repository,
    ) {
    }

    /**
     * Handle task saved event - cascade to parent job.
     *
     * @param Task $task The task that was saved.
     * @return void
     */
    #[Action( tag: 'pllat_task_saved' )]
    public function handle_task_saved( Task $task ): void {
        $this->cascade_task_to_job( $task );
    }

    /**
     * Handle job saved event - cascade to parent run.
     *
     * @param Job $job The job that was saved.
     * @return void
     */
    #[Action( tag: 'pllat_job_saved' )]
    public function handle_job_saved( Job $job ): void {
        $this->cascade_job_to_run( $job );
    }

    /**
     * Cascade task status to parent job.
     *
     * Rules:
     * 1. ANY task exhausted (failed + max attempts) → Job FAILED
     * 2. ALL tasks completed → Job COMPLETED
     * 3. At least one task terminal (completed/failed) → Job IN_PROGRESS
     *
     * @param Task $task The task that triggered cascade.
     * @return void
     */
    private function cascade_task_to_job( Task $task ): void {
        $job_id = $task->get_job_id();

        if ( null === $job_id ) {
            return;
        }

        try {
            $this->process_job_cascade( $job_id );
        } catch ( \Exception $e ) {
            $this->log_cascade_error( 'task->job', $e );
        }
    }

    /**
     * Process cascade for a job based on its tasks.
     *
     * @param int $job_id The job ID to process.
     * @return void
     */
    private function process_job_cascade( int $job_id ): void {
        $job       = $this->job_repository->find( $job_id );
        $all_tasks = $this->task_repository->find_by_job_id( $job_id );

        if ( 0 === \count( $all_tasks ) ) {
            return;
        }

        $new_status = $this->determine_job_status( $all_tasks );

        if ( $new_status === $job->get_status() ) {
            return;
        }

        if ( JobStatus::Completed === $new_status ) {
            $this->complete_job( $job );
        }
        if ( JobStatus::InProgress === $new_status ) {
            $job->start();
        }
        if ( JobStatus::Failed === $new_status ) {
            $job->fail();
        }
        if ( JobStatus::Cancelled === $new_status ) {
            $job->cancel();
        }
        $this->job_repository->save( $job );
    }

    /**
     * Complete a job.
     *
     * @param Job $job The job to complete.
     * @return void
     */
    private function complete_job( Job $job ): void {
        \do_action( 'pllat_before_job_completion', $job );
        $job->complete();
        \do_action( 'pllat_after_job_completion', $job );
    }

    /**
     * Determine job status based on task states.
     *
     * @param array<Task> $tasks All tasks for the job.
     * @return JobStatus The determined status.
     */
    private function determine_job_status( array $tasks ): JobStatus {
        $total         = \count( $tasks );
        $completed     = 0;
        $has_exhausted = false;
        $has_terminal  = false;

        foreach ( $tasks as $task ) {
            if ( TaskStatus::Completed === $task->get_status() ) {
                ++$completed;
                $has_terminal = true;
            } elseif ( $this->is_task_exhausted( $task ) ) {
                $has_exhausted = true;
                $has_terminal  = true;
            } elseif ( TaskStatus::Failed === $task->get_status() ) {
                $has_terminal = true;
            }
        }

        // Rule 1: ANY exhausted task → FAILED.
        if ( $has_exhausted ) {
            return JobStatus::Failed;
        }

        // Rule 2: ALL completed → COMPLETED.
        if ( $completed === $total ) {
            return JobStatus::Completed;
        }

        // Rule 3: Has terminal tasks → IN_PROGRESS.
        if ( $has_terminal ) {
            return JobStatus::InProgress;
        }

        return JobStatus::Pending;
    }

    /**
     * Check if task is exhausted (failed + max attempts reached).
     *
     * @param Task $task The task to check.
     * @return bool True if exhausted.
     */
    private function is_task_exhausted( Task $task ): bool {
        return TaskStatus::Failed === $task->get_status()
            && $task->get_attempts() >= Sync_Constants::MAX_TASK_ATTEMPTS;
    }

    /**
     * Cascade job status to parent run.
     *
     * Rules:
     * 1. ALL jobs terminal (completed OR failed) → Run COMPLETED
     * 2. At least one job in_progress → Run RUNNING
     *
     * @param Job $job The job that triggered cascade.
     * @return void
     */
    private function cascade_job_to_run( Job $job ): void {
        $run_id = $job->get_run_id();

        if ( null === $run_id || $this->already_cascaded( 'runs', $run_id ) ) {
            return;
        }

        $this->mark_cascaded( 'runs', $run_id );

        try {
            $this->process_run_cascade( $run_id );
        } catch ( \Exception $e ) {
            $this->log_cascade_error( 'job->run', $e );
        }
    }

    /**
     * Process cascade for a run based on its jobs.
     *
     * @param int $run_id The run ID to process.
     * @return void
     */
    private function process_run_cascade( int $run_id ): void {
        $run      = $this->run_repository->find( $run_id );
        $all_jobs = $this->find_all_jobs_for_run( $run_id );

        if ( 0 === \count( $all_jobs ) ) {
            return;
        }

        $new_status = $this->determine_run_status( $all_jobs );

        if ( $new_status === $run->get_status() ) {
            return; // No change needed.
        }

        $this->update_run_status( $run, $new_status );
    }

    /**
     * Find all jobs for a run (all statuses).
     *
     * @param int $run_id The run ID.
     * @return array<Job> All jobs.
     */
    private function find_all_jobs_for_run( int $run_id ): array {
        return $this->job_repository->find_by_run_and_statuses(
            $run_id,
            array(
                JobStatus::Pending,
                JobStatus::InProgress,
                JobStatus::Completed,
                JobStatus::Failed,
                JobStatus::Cancelled,
            ),
        );
    }

    /**
     * Determine run status based on job states.
     *
     * @param array<Job> $jobs All jobs for the run.
     * @return RunStatus The determined status.
     */
    private function determine_run_status( array $jobs ): RunStatus {
        $total       = \count( $jobs );
        $terminal    = 0;
        $in_progress = 0;

        foreach ( $jobs as $job ) {
            $status = $job->get_status();

            if ( JobStatus::Completed === $status || JobStatus::Failed === $status ) {
                ++$terminal;
            } elseif ( JobStatus::InProgress === $status ) {
                ++$in_progress;
            }
        }

        // Rule 1: ALL terminal → COMPLETED.
        if ( $terminal === $total ) {
            return RunStatus::Completed;
        }

        // Rule 2: Has in_progress → RUNNING.
        if ( $in_progress > 0 ) {
            return RunStatus::Running;
        }

        return RunStatus::Pending;
    }

    /**
     * Update run status with appropriate timestamps.
     *
     * @param Run       $run        The run to update.
     * @param RunStatus $new_status The new status.
     * @return void
     */
    private function update_run_status( Run $run, RunStatus $new_status ): void {
        $old_status = $run->get_status();
        $run->set_status( $new_status );

        if ( $old_status === $new_status ) {
            return;
        }

        if ( RunStatus::Completed === $new_status ) {
            $run->complete();
        }
        $this->run_repository->save( $run );
    }

    /**
     * Check if entity already cascaded in this request.
     *
     * @param string $type Entity type (jobs or runs).
     * @param int    $id   Entity ID.
     * @return bool True if already cascaded.
     */
    private function already_cascaded( string $type, int $id ): bool {
        return \in_array( $id, $this->cascaded[ $type ], true );
    }

    /**
     * Mark entity as cascaded in this request.
     *
     * @param string $type Entity type (jobs or runs).
     * @param int    $id   Entity ID.
     * @return void
     */
    private function mark_cascaded( string $type, int $id ): void {
        $this->cascaded[ $type ][] = $id;
    }

    /**
     * Log cascade error without breaking execution.
     *
     * Cascade failures should not break task/job updates.
     * Log the error and continue.
     *
     * @param string     $cascade_type Type of cascade (task->job or job->run).
     * @param \Exception $e            The exception.
     * @return void
     */
    private function log_cascade_error( string $cascade_type, \Exception $e ): void {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        \error_log(
            \sprintf(
                'Cascade error (%s): %s',
                $cascade_type,
                $e->getMessage(),
            ),
        );
    }
}
