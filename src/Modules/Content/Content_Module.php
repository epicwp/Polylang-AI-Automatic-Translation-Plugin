<?php
namespace PLLAT\Content;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use PLLAT\Common\Interfaces\Language_Manager;
use PLLAT\Content\Handlers\Content_Change_Handler;
use XWP\DI\Decorators\Module;

/**
 * Content Module - Manages content operations.
 *
 * Note: Cleanup logic moved to Sync module.
 */
#[Module(
    hook: 'init',
    priority: 10,
    handlers: array( Content_Change_Handler::class ),
    services: array(
        Language_Manager::class,
    ),
)]
class Content_Module {
    /**
     * Module definition.
     *
     * @return array<string,mixed>
     */
    public static function configure(): array {
        return array();
    }
}
