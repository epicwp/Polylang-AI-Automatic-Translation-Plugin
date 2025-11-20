<?php
/**
 * Logs_Module class file.
 *
 * @package Polylang AI Automatic Translation
 * @subpackage Logs
 */

namespace PLLAT\Logs;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use PLLAT\Logs\Controllers\Logs_REST_Controller;
use PLLAT\Logs\Handlers\Logging_Handler;
use PLLAT\Logs\Services\Log_Reader_Service;
use PLLAT\Logs\Services\Logger_Service;
use XWP\DI\Decorators\Module;

/**
 * Logs module definition.
 * Handles all logging functionality including log writing, reading, and REST API endpoints.
 */
#[Module(
    hook: 'init',
    priority: -10,
    handlers: array(
        Logs_REST_Controller::class,
        Logging_Handler::class,
    ),
    services: array(
        Logger_Service::class,
        Log_Reader_Service::class,
    ),
)]
class Logs_Module {
    /**
     * Module definition.
     *
     * @return array<string,mixed>
     */
    public static function configure(): array {
        return array(
            Logger_Service::class     => \DI\autowire(),
            Log_Reader_Service::class => \DI\autowire(),
        );
    }
}
