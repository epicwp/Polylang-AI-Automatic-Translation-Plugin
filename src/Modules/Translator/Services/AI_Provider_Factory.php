<?php
/**
 * AI_Provider_Factory for creating configured providers from settings.
 *
 * @package Polylang AI Automatic Translation
 * @subpackage Translator
 */

namespace PLLAT\Translator\Services;

use PLLAT\Settings\Services\Settings_Service;
use PLLAT\Translator\Providers\AI_Provider;
use PLLAT\Translator\Services\AI_Provider_Registry;

/**
 * Factory for creating configured AI providers from settings.
 */
class AI_Provider_Factory {
    /**
     * Create a configured provider from settings.
     *
     * @param Settings_Service $settings The settings service.
     * @return AI_Provider The configured provider.
     * @throws \Exception If provider cannot be created or configured.
     */
    public static function create_from_settings( Settings_Service $settings ): AI_Provider {
        $active_api = $settings->get_active_translation_api();

        // Get provider from registry.
        $provider = AI_Provider_Registry::get_provider( $active_api );
        if ( null === $provider ) {
            throw new \Exception( "AI Provider not found: {$active_api}" );
        }

        // Get credentials from settings.
        $api_key = $settings->get_translation_api_key( $active_api );
        if ( null === $api_key || '' === $api_key ) {
            throw new \Exception( "API key not configured for provider: {$active_api}" );
        }

        $model = $settings->get_translation_model( $active_api );
        if ( null === $model || '' === $model ) {
            throw new \Exception( "Model not configured for provider: {$active_api}" );
        }

        // Clone provider to avoid modifying the registry instance.
        $configured_provider = clone $provider;

        // Configure with credentials.
        $configured_provider->configure( $api_key, $model );

        // Validate configuration.
        $validation_errors = $configured_provider->validate_configuration();
        if ( \count( $validation_errors ) > 0 ) {
            throw new \Exception(
                'Provider configuration invalid: ' . \implode( ', ', $validation_errors ),
            );
        }

        return $configured_provider;
    }

    /**
     * Check if a provider can be created from current settings.
     *
     * @param Settings_Service $settings The settings service.
     * @return bool True if provider can be created.
     */
    public static function can_create_from_settings( Settings_Service $settings ): bool {
        try {
            self::create_from_settings( $settings );
            return true;
        } catch ( \Exception ) {
            return false;
        }
    }

    /**
     * Get validation errors for current settings without throwing exceptions.
     *
     * @param Settings_Service $settings The settings service.
     * @return array<string> Array of validation error messages.
     */
    public static function get_validation_errors( Settings_Service $settings ): array {
        $errors = array();

        try {
            $active_api = $settings->get_active_translation_api();

            // Check if provider exists
            $provider = AI_Provider_Registry::get_provider( $active_api );
            if ( null === $provider ) {
                $errors[] = "Unknown AI provider: {$active_api}";
                return $errors;
            }

            // Check API key
            $api_key = $settings->get_translation_api_key( $active_api );
            if ( null === $api_key || '' === $api_key ) {
                $errors[] = "API key not configured for {$provider->get_display_name()}";
            }

            // Check model
            $model = $settings->get_translation_model( $active_api );
            if ( null === $model || '' === $model ) {
                $errors[] = "Model not configured for {$provider->get_display_name()}";
            } else {
                $available_models = $provider->get_available_models();
                if ( ! \array_key_exists( $model, $available_models ) ) {
                    $errors[] = "Invalid model '{$model}' for {$provider->get_display_name()}";
                }
            }

            // If we have minimum config, test provider validation
            if ( null !== $api_key && '' !== $api_key && null !== $model && '' !== $model ) {
                $test_provider = clone $provider;
                $test_provider->configure( $api_key, $model );
                $provider_errors = $test_provider->validate_configuration();
                $errors          = \array_merge( $errors, $provider_errors );
            }
        } catch ( \Exception $e ) {
            $errors[] = 'Configuration error: ' . $e->getMessage();
        }

        return $errors;
    }

    /**
     * Create a provider for testing purposes with custom credentials.
     *
     * @param string $provider_key The provider key.
     * @param string $api_key      The API key.
     * @param string $model        The model.
     * @return AI_Provider The configured provider.
     * @throws \Exception If provider cannot be created.
     */
    public static function create_for_testing( string $provider_key, string $api_key, string $model ): AI_Provider {
        $provider = AI_Provider_Registry::get_provider( $provider_key );
        if ( null === $provider ) {
            throw new \Exception( "AI Provider not found: {$provider_key}" );
        }

        $configured_provider = clone $provider;
        $configured_provider->configure( $api_key, $model );

        return $configured_provider;
    }
}
