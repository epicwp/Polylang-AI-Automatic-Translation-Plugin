<?php
/**
 * Sync_Module class file.
 *
 * @package Polylang AI Automatic Translation
 * @subpackage Sync
 */

declare(strict_types=1);

namespace PLLAT\Sync;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
use PLLAT\Sync\Controllers\Discovery_REST_Controller;
use PLLAT\Sync\Handlers\Cascade_Handler;
use PLLAT\Sync\Handlers\Cleanup_Handler;
use PLLAT\Sync\Handlers\Recovery_Handler;
use PLLAT\Sync\Handlers\Sync_Handler;
use PLLAT\Sync\Services\Cleanup_Service;
use PLLAT\Sync\Services\Health_Service;
use PLLAT\Sync\Services\Recovery_Service;
use PLLAT\Sync\Services\Sync_Service;
use XWP\DI\Decorators\Module;
use XWP\DI\Interfaces\On_Initialize;

/**
 * Sync Module - Manages synchronization of jobs, tasks, and runs.
 *
 * Responsibilities:
 * - Content discovery (finding missing translations)
 * - Status cascade (tasks → jobs → runs)
 * - Job completion orchestration
 * - Stale job/run recovery (hourly health checks)
 * - Content deletion cleanup
 * - System health monitoring
 *
 * Architecture:
 * - Cascade_Handler is the foundation (priority 5)
 * - All status updates flow through cascade
 * - Services contain business logic
 * - Handlers orchestrate events
 */
#[Module(
    hook: 'init',
    priority: 15,
    handlers: array(
        Sync_Handler::class,
        Discovery_REST_Controller::class,
        Cascade_Handler::class,
        Recovery_Handler::class,
        Cleanup_Handler::class,
    ),
    services: array(
        Sync_Service::class,
        Recovery_Service::class,
        Cleanup_Service::class,
        Health_Service::class,
    ),
)]
class Sync_Module implements On_Initialize {
    public function on_initialize(): void {
        // die( 'Sync_Module' );
    }
}
