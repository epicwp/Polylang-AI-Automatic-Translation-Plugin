<?php
/**
 * Sync_Handler class file.
 *
 * @package Polylang AI Automatic Translation
 * @subpackage Sync
 */

namespace PLLAT\Sync\Handlers;

use PLLAT\Sync\Services\Sync_Service;
use XWP\DI\Decorators\Action;
use XWP\DI\Decorators\Handler;

/**
 * Handler for discovery process via Action Scheduler.
 * Uses a single global processor that handles all discovery work.
 */
#[Handler(
    tag: 'init',
    priority: 16,
    context: Handler::CTX_CRON | Handler::CTX_ADMIN | Handler::CTX_AJAX | Handler::CTX_CLI,
)]
class Sync_Handler {
    /**
     * Sync service.
     *
     * @var Sync_Service
     */
    private Sync_Service $sync_service;

    /**
     * Constructor.
     *
     * @param Sync_Service $sync_service The sync service.
     */
    public function __construct( Sync_Service $sync_service ) {
        $this->sync_service = $sync_service;
    }

    /**
     * Schedule the global discovery processor.
     * Runs on init hook with priority 20 to ensure Action Scheduler is loaded.
     *
     * @return void
     */
    #[Action( tag: 'init', priority: 17 )]
    public function schedule_discovery_processor(): void {
        $this->sync_service->ensure_global_processor_scheduled();
    }

    /**
     * Process discovery (global processor).
     * Called by Action Scheduler via 'pllat_discovery_process' action every 30 seconds.
     *
     * @return void
     */
    #[Action( tag: Sync_Service::ACTION )]
    public function process_discovery(): void {
        $this->sync_service->process_cycle();
    }
}
