<?php
declare(strict_types=1);

namespace PLLAT\Logs\Handlers;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use PLLAT\Logs\Services\Logger_Service;
use PLLAT\Translator\Models\Job;
use PLLAT\Translator\Models\Run;
use XWP\DI\Decorators\Action;
use XWP\DI\Decorators\Handler;

/**
 * Logging handler for translation events.
 *
 * Listens to translation-related WordPress actions and logs them via Logger_Service.
 * This provides complete separation between business logic and logging concerns.
 */
#[Handler( tag: 'init', priority: 10 )]
class Logging_Handler {
    /**
     * Constructor.
     *
     * @param Logger_Service $logger The logger service.
     */
    public function __construct(
        private Logger_Service $logger,
    ) {
    }

    /**
     * Log when a run is created/initiated.
     *
     * @param Run $run The run that was created.
     * @return void
     */
    #[Action( tag: 'pllat_run_created', priority: 10 )]
    public function log_run_created( Run $run ): void {
        try {
            $this->logger->log_run_created( $run );
        } catch ( \Throwable $e ) {
            \error_log( '[PLLAT Logging Error] Failed to log run creation: ' . $e->getMessage() );
        }
    }

    /**
     * Log when a run processing actually starts.
     *
     * @param Run $run The run that started processing.
     * @return void
     */
    #[Action( tag: 'pllat_run_processing_started', priority: 10 )]
    public function log_run_processing_start( Run $run ): void {
        try {
            $this->logger->log_run_processing_started( $run );
        } catch ( \Throwable $e ) {
            \error_log( '[PLLAT Logging Error] Failed to log run processing start: ' . $e->getMessage() );
        }
    }

    /**
     * Log when a run completes.
     *
     * @param Run $run The run that completed.
     * @return void
     */
    #[Action( tag: 'pllat_run_completed', priority: 10 )]
    public function log_run_completion( Run $run ): void {
        try {
            $this->logger->log_run_completed( $run );
        } catch ( \Throwable $e ) {
            \error_log( '[PLLAT Logging Error] Failed to log run completion: ' . $e->getMessage() );
        }
    }

    /**
     * Log when a run fails.
     *
     * @param Run    $run   The run that failed.
     * @param string $error The error message.
     * @return void
     */
    #[Action( tag: 'pllat_run_failed', priority: 10 )]
    public function log_run_failure( Run $run, string $error ): void {
        try {
            $this->logger->log_run_failed( $run, $error );
        } catch ( \Throwable $e ) {
            \error_log( '[PLLAT Logging Error] Failed to log run failure: ' . $e->getMessage() );
        }
    }

    /**
     * Log when a run is cancelled.
     *
     * @param Run $run The run that was cancelled.
     * @return void
     */
    #[Action( tag: 'pllat_run_cancelled', priority: 10 )]
    public function log_run_cancellation( Run $run ): void {
        try {
            $this->logger->log_run_cancelled( $run );
        } catch ( \Throwable $e ) {
            \error_log( '[PLLAT Logging Error] Failed to log run cancellation: ' . $e->getMessage() );
        }
    }

    /**
     * Log when a job completes successfully.
     *
     * @param Job $job The job that completed.
     * @return void
     */
    #[Action( tag: 'pllat_job_completed', priority: 10 )]
    public function log_job_completion( Job $job ): void {
        try {
            $this->logger->log_job_completed( $job );
        } catch ( \Throwable $e ) {
            \error_log( '[PLLAT Logging Error] Failed to log job completion: ' . $e->getMessage() );
        }
    }

    /**
     * Log when a job fails.
     *
     * @param Job        $job       The job that failed.
     * @param \Exception $exception The exception that caused the failure.
     * @return void
     */
    #[Action( tag: 'pllat_job_processing_failed', priority: 10 )]
    public function log_job_failure( Job $job, \Exception $exception ): void {
        try {
            $this->logger->log_job_failed( $job, $exception->getMessage() );
        } catch ( \Throwable $e ) {
            \error_log( '[PLLAT Logging Error] Failed to log job failure: ' . $e->getMessage() );
        }
    }

    /**
     * Log when discovery cycle finds new content.
     *
     * @param int $posts_found     Number of posts found needing translation.
     * @param int $terms_found     Number of terms found needing translation.
     * @param int $posts_processed Number of posts processed in this cycle.
     * @param int $terms_processed Number of terms processed in this cycle.
     * @return void
     */
    #[Action( tag: 'pllat_discovery_cycle_completed', priority: 10 )]
    public function log_discovery_cycle( int $posts_found, int $terms_found, int $posts_processed, int $terms_processed ): void {
        try {
            $this->logger->log_discovery_cycle(
                $posts_found,
                $terms_found,
                $posts_processed,
                $terms_processed,
            );
        } catch ( \Throwable $e ) {
            \error_log( '[PLLAT Logging Error] Failed to log discovery cycle: ' . $e->getMessage() );
        }
    }
}
