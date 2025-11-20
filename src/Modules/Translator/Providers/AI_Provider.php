<?php
/**
 * AI_Provider interface for unified AI provider management.
 *
 * @package Polylang AI Automatic Translation
 * @subpackage Translator
 */

namespace PLLAT\Translator\Providers;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
use PLLAT\Translator\Services\AI_Client;

/**
 * Interface for AI providers that handles both metadata and runtime functionality.
 */
interface AI_Provider {
    /**
     * Metadata methods - work without configuration
     */

    /**
     * Get the unique provider key/identifier.
     *
     * @return string The provider key (e.g., 'openai', 'claude').
     */
    public function get_provider_key(): string;

    /**
     * Get the human-readable display name.
     *
     * @return string The display name (e.g., 'OpenAI', 'Anthropic Claude').
     */
    public function get_display_name(): string;

    /**
     * Get all available models for this provider.
     *
     * @return array<string,string> Array of model_key => display_name.
     */
    public function get_available_models(): array;

    /**
     * Get the default model for this provider.
     *
     * @return string The default model key.
     */
    public function get_default_model(): string;

    /**
     * Get the base URL for API requests.
     *
     * @return string The base URL.
     */
    public function get_base_url(): string;

    /**
     * Get the URL where users can obtain an API key.
     *
     * @return string The API key signup/management URL.
     */
    public function get_api_key_url(): string;

    /**
     * Get a description for the API key field.
     *
     * @return string The description text.
     */
    public function get_api_key_description(): string;

    /**
     * Runtime methods - require configured instance
     */

    /**
     * Configure the provider with credentials and model.
     *
     * @param string $api_key The API key.
     * @param string $model   The model to use.
     * @return void
     */
    public function configure( string $api_key, string $model ): void;

    /**
     * Check if the provider is properly configured with required credentials.
     *
     * @return bool True if configured, false otherwise.
     */
    public function is_configured(): bool;

    /**
     * Get the configured AI client for API requests.
     *
     * @return AI_Client The configured HTTP client.
     * @throws \Exception If provider is not configured.
     */
    public function get_client(): AI_Client;

    /**
     * Get the currently configured model.
     *
     * @return string The model identifier.
     * @throws \Exception If provider is not configured.
     */
    public function get_model(): string;

    /**
     * Get the configured API key.
     *
     * @return string The API key.
     * @throws \Exception If provider is not configured.
     */
    public function get_api_key(): string;

    /**
     * Validate the current configuration and return any errors.
     *
     * @return array<string> Array of validation error messages.
     */
    public function validate_configuration(): array;
}
