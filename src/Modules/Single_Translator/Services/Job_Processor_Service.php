<?php
/**
 * Job_Processor_Service class file.
 *
 * @package Polylang AI Automatic Translation
 * @subpackage Single_Translator
 */

declare(strict_types=1);

namespace PLLAT\Single_Translator\Services;

use PLLAT\Translator\Enums\JobStatus;
use PLLAT\Translator\Enums\TaskStatus;
use PLLAT\Translator\Repositories\Job_Repository;
use PLLAT\Translator\Repositories\Task_Repository;
use PLLAT\Translator\Services\Task_Processor;

/**
 * Service for processing translation jobs via async actions.
 *
 * Responsibilities:
 * - Process all tasks for a given job
 * - Handle task failures with retry logic
 * - Let cascade handler manage job status updates
 *
 * This service is called by Job_Processor_Handler when an async
 * action is triggered by Action Scheduler.
 */
class Job_Processor_Service {
    /**
     * Constructor.
     *
     * @param Job_Repository  $job_repository  Job repository.
     * @param Task_Repository $task_repository Task repository.
     * @param Task_Processor  $task_processor  Task processor (handles actual translation).
     */
    public function __construct(
        private Job_Repository $job_repository,
        private Task_Repository $task_repository,
        private Task_Processor $task_processor,
    ) {
    }

    /**
     * Process a translation job.
     *
     * Processes all tasks for the job sequentially. Each task is translated
     * via the Task_Processor, which handles translator selection (text/JSON).
     *
     * The cascade handler automatically updates job status based on task states:
     * - All tasks completed → Job completed
     * - Any task exhausted → Job failed
     * - Has terminal tasks → Job in_progress
     *
     * @param int $job_id The job ID to process.
     * @return void
     * @throws \Exception If job not found.
     */
    public function process_job( int $job_id ): void {
        $job = $this->job_repository->find( $job_id );

        if ( ! $job ) {
            throw new \Exception( "Job {$job_id} not found" );
        }

        // Mark job as in_progress.
        $job->set_status( JobStatus::InProgress );
        $this->job_repository->save( $job ); // Triggers cascade.

        $tasks = $this->task_repository->find_by_job_id( $job_id );

        foreach ( $tasks as $task ) {
            // Skip already completed or exhausted tasks.
            if ( $task->is_completed() || $task->is_exhausted() ) {
                continue;
            }

            try {
                $this->process_task( $task, $job );
            } catch ( \Exception $e ) {
                $this->handle_task_failure( $task, $e );

                // If task is now exhausted, stop processing remaining tasks.
                // Cascade will mark job as failed.
                if ( $task->is_exhausted() ) {
                    break;
                }
            }
        }

        // Note: No need to manually update job status here.
        // The cascade handler will update job status based on task states.
    }

    /**
     * Process a single task.
     *
     * @param \PLLAT\Translator\Models\Task $task The task to process.
     * @param \PLLAT\Translator\Models\Job  $job  The parent job.
     * @return void
     * @throws \Exception If translation fails.
     */
    private function process_task( $task, $job ): void {
        // Translate via Task_Processor (handles translator selection).
        $translation = $this->task_processor->process_task(
            $task,
            $job->get_lang_from(),
            $job->get_lang_to(),
            array(
                'content_id'   => $job->get_id_from(),
                'content_type' => $job->get_content_type(),
            ),
        );

        // Update task with translation.
        $task->set_translation( $translation );
        $task->set_status( TaskStatus::Completed );
        $this->task_repository->save( $task ); // Triggers cascade.
    }

    /**
     * Handle task processing failure.
     *
     * Updates task status to failed, increments attempts,
     * and stores error message.
     *
     * @param \PLLAT\Translator\Models\Task $task      The failed task.
     * @param \Exception                    $exception The exception.
     * @return void
     */
    private function handle_task_failure( $task, \Exception $exception ): void {
        $task->set_status( TaskStatus::Failed );
        $task->set_issue( $exception->getMessage() );
        $task->increment_attempts();
        $this->task_repository->save( $task ); // Triggers cascade.
    }
}
