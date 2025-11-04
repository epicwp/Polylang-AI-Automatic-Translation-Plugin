<?php
/**
 * Core_Module class file.
 *
 * @package Polylang AI Automatic Translation
 * @subpackage Core
 */

namespace PLLAT\Core;

use PLLAT\Common\Interfaces\Language_Manager;
use PLLAT\Core\Services\Polylang_Language_Manager;
use PLLAT\Translator\Providers\OpenAI_Provider;
use PLLAT\Translator\Services\AI_Provider_Registry;
use XWP\DI\Decorators\Module;
use XWP\DI\Interfaces\Can_Initialize;
use XWP\DI\Interfaces\On_Initialize;

/**
 * Core module for shared services and infrastructure.
 */
#[Module( hook: 'plugins_loaded', priority: 5, services: array( Language_Manager::class ) )]
class Core_Module implements Can_Initialize, On_Initialize {
    /**
     * Check if the module can be initialized.
     *
     * @return bool
     */
    public static function can_initialize(): bool {
        return true; // Core module should always initialize.
    }

    /**
     * Module configuration.
     *
     * @return array<string,mixed>
     */
    public static function configure(): array {
        return array(
            Language_Manager::class => \DI\autowire( Polylang_Language_Manager::class ),
        );
    }

    /**
     * Initialize the module and register AI providers.
     *
     * @return void
     */
    public function on_initialize(): void {
        $this->register_ai_providers();
    }

    /**
     * Register AI providers - Free version only includes OpenAI.
     *
     * @return void
     */
    private function register_ai_providers(): void {
        // Free version: Only OpenAI provider.
        AI_Provider_Registry::register_provider( new OpenAI_Provider() );

        /**
         * Filter to allow registration of additional AI providers.
         *
         * @param AI_Provider_Registry $registry The provider registry.
         */
        \do_action( 'pllat_register_ai_providers', AI_Provider_Registry::class );

        AI_Provider_Registry::mark_initialized();
    }
}
