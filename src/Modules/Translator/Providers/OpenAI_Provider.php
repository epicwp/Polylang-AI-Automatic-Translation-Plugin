<?php
/**
 * OpenAI_Provider implementation.
 *
 * @package Polylang AI Automatic Translation
 * @subpackage Translator
 */

namespace PLLAT\Translator\Providers;

use PLLAT\Translator\Services\AI_Client;

/**
 * OpenAI provider implementation.
 */
class OpenAI_Provider implements AI_Provider {
    /**
     * Configured API key.
     *
     * @var string|null
     */
    private ?string $api_key = null;

    /**
     * Configured model.
     *
     * @var string|null
     */
    private ?string $model = null;

    /**
     * Configured AI client.
     *
     * @var AI_Client|null
     */
    private ?AI_Client $client = null;

    /**
     * Get the unique provider key/identifier.
     *
     * @return string The provider key.
     */
    public function get_provider_key(): string {
        return 'openai';
    }

    /**
     * Get the human-readable display name.
     *
     * @return string The display name.
     */
    public function get_display_name(): string {
        return 'OpenAI (ChatGPT)';
    }

    /**
     * Get all available models for this provider.
     *
     * @return array<string,string> Array of model_key => display_name.
     */
    public function get_available_models(): array {
        return array(
            'gpt-4.1'      => 'GPT-4.1',
            'gpt-4.1-mini' => 'GPT-4.1 Mini',
            'gpt-4o'       => 'GPT-4o',
            'gpt-4o-mini'  => 'GPT-4o Mini',
            'gpt-5'        => 'GPT-5',
        );
    }

    /**
     * Get the default model for this provider.
     *
     * @return string The default model key.
     */
    public function get_default_model(): string {
        return 'gpt-4o';
    }

    /**
     * Get the base URL for API requests.
     *
     * @return string The base URL.
     */
    public function get_base_url(): string {
        return 'https://api.openai.com/v1';
    }

    /**
     * Get the URL where users can obtain an API key.
     *
     * @return string The API key signup/management URL.
     */
    public function get_api_key_url(): string {
        return 'https://platform.openai.com/api-keys';
    }

    /**
     * Get a description for the API key field.
     *
     * @return string The description text.
     */
    public function get_api_key_description(): string {
        return \__(
            'Get your API key from OpenAI Platform (https://platform.openai.com/api-keys)',
            'polylang-automatic-ai-translation',
        );
    }

    /**
     * Configure the provider with credentials and model.
     *
     * @param string $api_key The API key.
     * @param string $model   The model to use.
     * @return void
     */
    public function configure( string $api_key, string $model ): void {
        $this->api_key = $api_key;
        $this->model   = $model;

        // Reset client so it gets recreated with new config
        $this->client = null;
    }

    /**
     * Check if the provider is properly configured with required credentials.
     *
     * @return bool True if configured, false otherwise.
     */
    public function is_configured(): bool {
        return null !== $this->api_key && '' !== $this->api_key && null !== $this->model && '' !== $this->model;
    }

    /**
     * Get the configured AI client for API requests.
     *
     * @return AI_Client The configured HTTP client.
     * @throws \Exception If provider is not configured.
     */
    public function get_client(): AI_Client {
        if ( ! $this->is_configured() ) {
            throw new \Exception( 'OpenAI provider is not configured. Please set API key and model.' );
        }

        if ( null === $this->client ) {
            $this->client = new AI_Client(
                $this->api_key,
                $this->get_base_url(),
                $this->model,
            );
        }

        return $this->client;
    }

    /**
     * Get the currently configured model.
     *
     * @return string The model identifier.
     * @throws \Exception If provider is not configured.
     */
    public function get_model(): string {
        if ( ! $this->is_configured() ) {
            throw new \Exception( 'OpenAI provider is not configured. Please set API key and model.' );
        }

        return $this->model;
    }

    /**
     * Get the configured API key.
     *
     * @return string The API key.
     * @throws \Exception If provider is not configured.
     */
    public function get_api_key(): string {
        if ( ! $this->is_configured() ) {
            throw new \Exception( 'OpenAI provider is not configured. Please set API key and model.' );
        }

        return $this->api_key;
    }

    /**
     * Validate the current configuration and return any errors.
     *
     * @return array<string> Array of validation error messages.
     */
    public function validate_configuration(): array {
        $errors = array();

        if ( null === $this->api_key || '' === $this->api_key ) {
            $errors[] = \__( 'OpenAI API key is required.', 'polylang-automatic-ai-translation' );
        }

        if ( null === $this->model || '' === $this->model ) {
            $errors[] = \__( 'OpenAI model selection is required.', 'polylang-automatic-ai-translation' );
        } elseif ( ! \array_key_exists( $this->model, $this->get_available_models() ) ) {
            $errors[] = \sprintf(
                /* translators: %s: Model name */
                \__( 'Invalid OpenAI model: %s', 'polylang-automatic-ai-translation' ),
                $this->model,
            );
        }

        // Basic API key format validation.
        if ( null !== $this->api_key && '' !== $this->api_key && ! \str_starts_with( $this->api_key, 'sk-' ) ) {
            $errors[] = \__( 'OpenAI API key should start with "sk-".', 'polylang-automatic-ai-translation' );
        }

        return $errors;
    }
}
