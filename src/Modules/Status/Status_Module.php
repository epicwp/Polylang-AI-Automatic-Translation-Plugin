<?php
/**
 * Status_Module class file.
 *
 * @package Polylang AI Automatic Translation
 * @subpackage Status
 */

namespace PLLAT\Status;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
use PLLAT\Status\Handlers\Site_Health_Handler;
use PLLAT\Status\Services\Cron_Status_Service;
use PLLAT\Status\Services\Status_Info_Service;
use XWP\DI\Decorators\Module;

/**
 * Status module for WordPress Site Health integration.
 * Provides plugin diagnostics and system status information.
 */
#[Module(
    hook: 'plugins_loaded',
    priority: 10,
    context: Module::CTX_ADMIN,
    handlers: array(
        Site_Health_Handler::class,
    ),
    services: array(
        Status_Info_Service::class,
        Cron_Status_Service::class,
    ),
)]
class Status_Module {
    /**
     * Module configuration.
     *
     * @return array<string,mixed>
     */
    public static function configure(): array {
        return array();
    }
}
