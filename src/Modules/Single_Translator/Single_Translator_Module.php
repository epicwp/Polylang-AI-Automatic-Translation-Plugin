<?php
/**
 * Single_Translator_Module class file.
 *
 * @package Polylang AI Automatic Translation
 * @subpackage Single_Translator
 */

declare(strict_types=1);

namespace PLLAT\Single_Translator;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use PLLAT\Single_Translator\Controllers\Single_Translation_REST_Controller;
use PLLAT\Single_Translator\Handlers\Job_Processor_Handler;
use PLLAT\Single_Translator\Handlers\Meta_Box_Handler;
use PLLAT\Single_Translator\Services\Async_Job_Dispatcher_Service;
use PLLAT\Single_Translator\Services\Job_Processor_Service;
use PLLAT\Single_Translator\Services\Single_Translation_Service;
use XWP\DI\Decorators\Module;

/**
 * Single Translator Module.
 *
 * Provides functionality for translating individual posts and terms.
 * Pro users use external processor, free users use async actions.
 */
#[Module(
    hook: 'init',
    priority: 5,
    handlers: array(
        Meta_Box_Handler::class,
        Single_Translation_REST_Controller::class,
        Job_Processor_Handler::class,
    ),
    services: array(
        Single_Translation_Service::class,
        Async_Job_Dispatcher_Service::class,
        Job_Processor_Service::class,
    ),
)]
class Single_Translator_Module {
}
