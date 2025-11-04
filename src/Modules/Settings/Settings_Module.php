<?php
/**
 * Settings_Module class file.
 *
 * @package Polylang AI Automatic Translation
 * @subpackage Settings
 */

namespace PLLAT\Settings;

use PLLAT\Settings\Handlers\Settings_Ajax_Handler;
use PLLAT\Settings\Handlers\Settings_Page_Handler;
use PLLAT\Settings\Services\Settings_Form;
use PLLAT\Settings\Services\Settings_Service;
use XWP\DI\Decorators\Module;

/**
 * Settings module for managing plugin configuration.
 */
#[Module(
    hook: 'plugins_loaded',
    priority: 10,
    context: Module::CTX_ADMIN | Module::CTX_AJAX,
    handlers: array(
        Settings_Page_Handler::class,
    ),
    services: array(
        Settings_Service::class,
        Settings_Form::class,
    ),
)]
class Settings_Module {
    /**
     * Module configuration.
     *
     * @return array<string,mixed>
     */
    public static function configure(): array {
        return array();
    }
}
