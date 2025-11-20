<?php
/**
 * Job_Processor_Handler class file.
 *
 * @package Polylang AI Automatic Translation
 * @subpackage Single_Translator
 */

declare(strict_types=1);

namespace PLLAT\Single_Translator\Handlers;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use PLLAT\Single_Translator\Services\Job_Processor_Service;
use Psr\Container\ContainerInterface;
use XWP\DI\Decorators\Action;
use XWP\DI\Decorators\Handler;

/**
 * Handles async job processing actions.
 *
 * Listens to 'pllat_process_single_job' actions triggered by Action Scheduler
 * and delegates processing to Job_Processor_Service.
 *
 * This handler is used for free users who process jobs via async actions
 * instead of the external processor service.
 *
 * Note: Uses lazy loading via DI container to prevent fatal errors when
 * AI provider settings are not configured during plugin activation.
 */
#[Handler( tag: 'init', priority: 18 )]
class Job_Processor_Handler {
    /**
     * Constructor.
     *
     * @param ContainerInterface $container DI container for lazy loading services.
     */
    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    /**
     * Process a job via async action.
     *
     * Triggered by Action Scheduler when 'pllat_process_single_job' action fires.
     * The job_id is passed as the first parameter by Action Scheduler.
     *
     * @param int $job_id The job ID to process.
     * @return void
     */
    #[Action( tag: 'pllat_process_single_job' )]
    public function process_job( int $job_id ): void {
        try {
            // Lazy-load the processor service only when action fires.
            // This prevents fatal error if AI provider settings are not configured.
            $processor_service = $this->container->get( Job_Processor_Service::class );
            $processor_service->process_job( $job_id );
        } catch ( \Exception $e ) {
            // Log error but don't throw - Action Scheduler will mark action as failed.
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            \error_log(
                \sprintf(
                    '[PLLAT] Job %d processing failed: %s',
                    $job_id,
                    $e->getMessage(),
                ),
            );
        }
    }
}
