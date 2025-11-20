<?php
/**
 * App class file.
 *
 * @package Polylang AI Automatic Translation
 */

namespace PLLAT;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use PLLAT\Content\Content_Module;
use PLLAT\Core\Core_Module;
use PLLAT\Logs\Logs_Module;
use PLLAT\Settings\Settings_Module;
use PLLAT\Single_Translator\Single_Translator_Module;
use PLLAT\Status\Status_Module;
use PLLAT\Sync\Sync_Module;
use PLLAT\Translator\Translator_Module;
use XWP\DI\Decorators\Module;
use XWP\DI\Interfaces\On_Initialize;

/**
 * Main application class.
 */
#[Module(
    hook: 'pll_init',
    priority: 0,
    imports: array(
        Status_Module::class,
        Core_Module::class,
        Settings_Module::class,
        Sync_Module::class,
        Logs_Module::class,
        Translator_Module::class,
        Content_Module::class,
        Single_Translator_Module::class,
    ),
)]
class App implements On_Initialize {
    /**
     * Can we initialize the module.
     *
     * @return bool
     */
    public static function can_initialize(): bool {
        return ! \pllat_is_pll_deactivating() && \pll_default_language();
    }

    /**
     * Get the module configuration.
     *
     * @return array<string,mixed>
     */
    public static function configure(): array {
        return array(
            'app.name' => \DI\factory(
                static fn() => \__( 'Polylang AI Automatic Translation', 'epicwp-ai-translation-for-polylang' ),
            ),
        );
    }

    /**
     * Register error handler for this plugin.
     */
    public function on_initialize(): void {
        /**
         * Fires when the plugin is initialized.
         *
         * @param string $default_language The default language.
         */
        \do_action( 'pllat_loaded', \pll_default_language() );
    }
}
