<?php
/**
 * Translator_Module class file.
 *
 * @package Polylang AI Automatic Translation
 * @subpackage Translator
 */

namespace PLLAT\Translator;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
use PLLAT\Common\Services\IP_Verification_Service;
use PLLAT\Common\Services\Rate_Limiter_Service;
use PLLAT\Content\Services\Content_Service;
use PLLAT\Settings\Services\Settings_Service;
use PLLAT\Translator\Handlers\Translator_Handler;
use PLLAT\Translator\Services\AI_Client;
use PLLAT\Translator\Services\AI_Provider_Factory;
use PLLAT\Translator\Services\Bulk\Post_Query_Service;
use PLLAT\Translator\Services\Bulk\Term_Query_Service;
use PLLAT\Translator\Services\Task_Processor;
use PLLAT\Translator\Services\Translation_Run_Service;
use PLLAT\Translator\Services\Translation_Stats_Service;
use PLLAT\Translator\Services\Translator;
use XWP\DI\Decorators\Module;
use XWP\DI\Interfaces\Can_Initialize;

/**
 * Translator module definition.
 *
 * Note: Cascade, Recovery, and Completion logic moved to Sync module.
 */
#[Module(
    hook: 'init',
    priority: 1,
    handlers: array(
        Translator_Handler::class,
    ),
    services: array(
        Post_Query_Service::class,
        Term_Query_Service::class,
        Translation_Stats_Service::class,
        Translation_Run_Service::class,
        IP_Verification_Service::class,
        Rate_Limiter_Service::class,
        Content_Service::class,
    ),
)]
class Translator_Module implements Can_Initialize {
    /**
     * Check if the module can be initialized.
     *
     * Module must always load for both BYOK and Credits modes.
     * - BYOK mode: needs AI services (Translator, AI_Client) + REST endpoints
     * - Credits mode: needs REST endpoints only (External_Processor_Controller)
     *
     * @return bool Always true to ensure module loads in both modes.
     */
    public static function can_initialize(): bool {
        // Only initialize if AI API settings are properly configured.
        $settings_service = new Settings_Service();
        return AI_Provider_Factory::can_create_from_settings( $settings_service );
    }

    /**
     * Module definition.
     *
     * Conditionally register AI services based on translation mode:
     * - BYOK mode: Register AI_Client, Translator, Task_Processor
     * - Credits mode: No AI services needed (external processor handles translation)
     *
     * @return array<string,mixed>
     */
    public static function configure(): array {
        $config           = array();
        $settings_service = new Settings_Service();

        // Only register AI services in BYOK mode
        // Note: We don't check API key here because this runs during bootstrap.
        // The actual validation happens when services are requested.
        if ( $settings_service->is_byok_mode() ) {
            // AI Client factory.
            $config[ AI_Client::class ] = \DI\factory(
                static function ( Settings_Service $settings_service ) {
                    // Validation happens here, when service is actually requested.
                    if ( ! AI_Provider_Factory::can_create_from_settings( $settings_service ) ) {
                        throw new \Exception(
                            'AI provider cannot be created: settings not configured or invalid API key',
                        );
                    }
                    $provider = AI_Provider_Factory::create_from_settings( $settings_service );
                    return $provider->get_client();
                },
            );

            // Text Translator.
            $config[ Translator::class ] = \DI\factory(
                static fn( AI_Client $ai_client ) => new Translator( $ai_client ),
            );

            // Task Processor (factory with both translators as optional dependencies).
            $config[ Task_Processor::class ] = \DI\factory(
                static fn( Translator $text ) => new Task_Processor(
                    $text,
                ),
            );
        }

        return $config;
    }
}
