<?php
// phpcs:disable
/**
 * Settings_Form class file.
 *
 * @package Polylang AI Automatic Translation
 * @subpackage Settings
 */

namespace PLLAT\Settings\Services;

use PLLAT\Translator\Services\AI_Provider_Registry;

/**
 * Handles rendering of settings forms and UI components using WordPress Settings API.
 */
class Settings_Form {
    /**
     * The settings service.
     *
     * @var Settings_Service
     */
    private Settings_Service $settings_service;

    /**
     * Constructor.
     *
     * @param Settings_Service $settings_service The settings service.
     */
    public function __construct( Settings_Service $settings_service ) {
        $this->settings_service = $settings_service;
    }

    /**
     * Initialize settings registration and scripts
     *
     * @return void
     */
    public function register_hooks(): void {
        \add_action( 'admin_init', array( $this, 'register_settings' ) );
        \add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    /**
     * Register all settings using WordPress Settings API
     *
     * @return void
     */
    public function register_settings(): void {
        // Register settings group with sanitization
        \register_setting(
            'pllat_settings_group',
            'pllat_translator_api',
            array(
                'default'           => 'openai',
                'sanitize_callback' => array( $this, 'sanitize_api_provider' ),
            ),
        );
        \register_setting(
            'pllat_settings_group',
            'pllat_max_output_tokens',
            array(
                'default'           => 16000,
                'sanitize_callback' => array( $this, 'sanitize_max_tokens' ),
            ),
        );
        \register_setting(
            'pllat_settings_group',
            'pllat_website_ai_context',
            array(
                'default'           => '',
                'sanitize_callback' => 'sanitize_textarea_field',
            ),
        );
        \register_setting(
            'pllat_settings_group',
            'pllat_debug_mode',
            array(
                'default'           => false,
                'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
            ),
        );
        \register_setting(
            'pllat_settings_group',
            'pllat_translation_mode',
            array(
                'default'           => 'byok',
                'sanitize_callback' => array( $this, 'sanitize_translation_mode' ),
            ),
        );

        // Register API key settings for each provider
        $providers = AI_Provider_Registry::get_providers_for_options();
        foreach ( \array_keys( $providers ) as $provider ) {
            \register_setting(
                'pllat_settings_group',
                "pllat_{$provider}_api_key",
                array(
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            );
            \register_setting(
                'pllat_settings_group',
                "pllat_{$provider}_translation_model",
                array(
                    'default'           => AI_Provider_Registry::get_default_model_for_provider( $provider ),
                    'sanitize_callback' => function( $value ) use ( $provider ) {
                        return $this->sanitize_model_for_api( $value, $provider );
                    },
                ),
            );
        }

        // Add settings section
        \add_settings_section(
            'pllat_main_section',
            \__( 'AI Translation Configuration', 'polylang-ai-autotranslate' ),
            array( $this, 'render_section_description' ),
            'pllat_settings',
        );

        // Add settings fields
        $this->add_settings_fields();
    }

        /**
     * Add all settings fields
     *
     * @return void
     */
    private function add_settings_fields(): void {
        // Translation Mode field (BYOK vs Credits)
        \add_settings_field(
            'pllat_translation_mode',
            \__( 'Translation Mode', 'polylang-ai-autotranslate' ),
            array( $this, 'render_translation_mode_field' ),
            'pllat_settings',
            'pllat_main_section',
        );

        // API Provider field
        \add_settings_field(
            'pllat_translator_api',
            \__( 'Translation API Provider', 'polylang-ai-autotranslate' ),
            array( $this, 'render_api_provider_field' ),
            'pllat_settings',
            'pllat_main_section',
        );

        // API Key fields
        $this->add_api_key_fields();

        // Model selection field
        \add_settings_field(
            'pllat_translation_model',
            \__( 'Translation Model', 'polylang-ai-autotranslate' ),
            array( $this, 'render_model_selection_field' ),
            'pllat_settings',
            'pllat_main_section',
        );

        // Max tokens field
        \add_settings_field(
            'pllat_max_output_tokens',
            \__( 'Max Output Tokens', 'polylang-ai-autotranslate' ),
            array( $this, 'render_max_tokens_field' ),
            'pllat_settings',
            'pllat_main_section',
        );

        // AI context field
        \add_settings_field(
            'pllat_website_ai_context',
            \__( 'Website Context', 'polylang-ai-autotranslate' ),
            array( $this, 'render_ai_context_field' ),
            'pllat_settings',
            'pllat_main_section',
        );

        // Debug mode field
        \add_settings_field(
            'pllat_debug_mode',
            \__( 'Debug Mode', 'polylang-ai-autotranslate' ),
            array( $this, 'render_debug_mode_field' ),
            'pllat_settings',
            'pllat_main_section',
        );
    }

    /**
     * Enqueue scripts and styles
     *
     * @param string $hook The current admin page hook.
     * @return void
     */
    public function enqueue_scripts( string $hook ): void {
        if ( 'languages_page_pllat-settings' !== $hook ) {
            return;
        }

        \wp_enqueue_script( 'jquery' );
        \wp_enqueue_script(
            'pllat-settings',
            \plugins_url( '../assets/settings.js', __FILE__ ),
            array( 'jquery' ),
            '1.0.0',
            true,
        );

        \wp_localize_script(
            'pllat-settings',
            'pllat_settings',
            array(
                'defaults'       => AI_Provider_Registry::get_all_default_models(),
                'models'         => $this->get_all_models(),
                'current_models' => $this->get_current_models_for_all_apis(),
            ),
        );
    }

    /**
     * Render the complete settings form using WordPress Settings API
     *
     * @return void
     */
    public function render(): void {
        $validation = $this->settings_service->validate_api_settings();
        ?>
        <div class="wrap">
            <h1><?php \esc_html_e( 'AI Translation Settings', 'polylang-ai-autotranslate' ); ?></h1>

            <?php if ( ! $validation['valid'] ) : ?>
                <div class="notice notice-error">
                    <p><strong>
                    <?php
                    \esc_html_e( 'Configuration Issues:', 'polylang-ai-autotranslate' );
                    ?>
                    </strong></p>
                    <ul>
                        <?php foreach ( $validation['errors'] as $error ) : ?>
                            <li><?php echo \esc_html( $error ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" action="options.php" id="pllat-settings-form">
                <?php
                \settings_fields( 'pllat_settings_group' );
                \do_settings_sections( 'pllat_settings' );
                \submit_button( \__( 'Save Settings', 'polylang-ai-autotranslate' ) );
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render section description
     *
     * @return void
     */
    public function render_section_description(): void { ?>
        <p><?php \esc_html_e( 'Configure your AI translation settings below.', 'polylang-ai-autotranslate' ); ?></p>
        <?php
    }

    /**
     * Render translation mode selection field (BYOK vs Credits)
     *
     * @return void
     */
    public function render_translation_mode_field(): void {
        $current_mode = $this->settings_service->get_translation_mode();
        ?>
        <fieldset>
            <label style="display: block; margin-bottom: 20px!important;">
                <input type="radio"
                    name="pllat_translation_mode"
                    value="byok"
                    <?php \checked( $current_mode, 'byok' ); ?> />
                <strong><?php \esc_html_e( 'BYOK (Bring Your Own Key)', 'polylang-ai-autotranslate' ); ?></strong>
                <p class="description" style="margin-left: 25px;">
                    <?php \esc_html_e( 'Use your own API key from OpenAI, Claude, or Gemini. You pay the AI provider directly for usage.', 'polylang-ai-autotranslate' ); ?>
                </p>
            </label>

            <label style="display: block; margin-bottom: 10px; opacity: 0.85;">
                <input type="radio"
                    name="pllat_translation_mode"
                    value="credits"
                    disabled
                    <?php \checked( $current_mode, 'credits' ); ?> />
                <strong><?php \esc_html_e( 'Credits', 'polylang-ai-autotranslate' ); ?></strong>
                <span style="background: #fff3cd; color: #856404; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 500; margin-left: 5px;">
                    <?php \esc_html_e( 'Coming Soon', 'polylang-ai-autotranslate' ); ?>
                </span>
                <p class="description" style="margin-left: 25px;">
                    <?php \esc_html_e( 'Purchase translation credits from us. No API key needed - we handle everything.', 'polylang-ai-autotranslate' ); ?>
                </p>
            </label>
        </fieldset>
        <?php
    }

    /**
     * Render API provider selection field
     *
     * @return void
     */
    public function render_api_provider_field(): void {
        $active_api = $this->settings_service->get_active_translation_api();
        $providers  = AI_Provider_Registry::get_providers_for_options();
        ?>
        <select name="pllat_translator_api" id="pllat_translator_api" class="regular-text">
            <?php foreach ( $providers as $api_key => $label ) : ?>
                <option value="<?php echo \esc_attr( $api_key ); ?>" <?php \selected( $active_api, $api_key ); ?>>
                    <?php echo \esc_html( $label ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php \esc_html_e( 'Select the AI provider for translations.', 'polylang-ai-autotranslate' ); ?>
        </p>
        <?php
    }

    /**
     * Render individual API key field
     *
     * @param array<string,string> $args The field arguments containing api_key and label.
     * @return void
     */
    public function render_api_key_field( array $args ): void {
        $provider       = $args['provider'];
        $api_key_value = $this->settings_service->get_translation_api_key( $provider );
        $row_class = 'api-key-row';
        ?>
        <div class="<?php echo \esc_attr( $row_class ); ?>" data-api="<?php echo \esc_attr( $provider ); ?>">
            <input type="password" 
                name="pllat_<?php echo \esc_attr( $provider ); ?>_api_key" 
                id="pllat_<?php echo \esc_attr( $provider ); ?>_api_key"
                value="<?php echo \esc_attr( $api_key_value ); ?>" 
                class="regular-text" 
                autocomplete="off" 
            />
            <p class="description">
                <?php echo $this->get_api_key_description( $provider ); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Render model selection field
     *
     * @return void
     */
    public function render_model_selection_field(): void {
        $active_api       = $this->settings_service->get_active_translation_api();
        $current_model    = $this->settings_service->get_translation_model( $active_api );
        $available_models = AI_Provider_Registry::get_models_for_provider( $active_api );
        ?>
        <select name="pllat_<?php echo \esc_attr( $active_api ); ?>_translation_model" id="pllat_translation_model" class="regular-text">
            <?php foreach ( $available_models as $model_key => $label ) : ?>
                <option value="<?php echo \esc_attr( $model_key ); ?>" <?php \selected( $current_model, $model_key ); ?>>
                    <?php echo \esc_html( $label ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php \esc_html_e( 'Select the AI model to use for translations.', 'polylang-ai-autotranslate' ); ?>
        </p>
        <?php
    }

    /**
     * Render max output tokens field
     *
     * @return void
     */
    public function render_max_tokens_field(): void {
        $max_tokens = $this->settings_service->get_max_output_tokens();
        ?>
        <input type="number" 
            name="pllat_max_output_tokens" 
            id="pllat_max_output_tokens"
            value="<?php echo \esc_attr( $max_tokens ); ?>" 
            class="small-text" 
            min="100" 
            max="32000" 
            step="100" />
        <p class="description">
            <?php
            \esc_html_e(
                'Maximum number of tokens for AI responses. Higher values allow longer translations but increase costs.',
                'polylang-ai-autotranslate',
            );
            ?>
        </p>
        <?php
    }

    /**
     * Render AI context field
     *
     * @return void
     */
    public function render_ai_context_field(): void {
        $ai_context = $this->settings_service->get_website_ai_context();
        ?>
        <textarea name="pllat_website_ai_context"
            id="pllat_website_ai_context"
            rows="4"
            cols="50"
            class="large-text"><?php echo \esc_textarea( $ai_context ); ?></textarea>
        <p class="description">
            <?php
            \esc_html_e(
                'Provide context about your website/audience/company to help the AI understand your content better. This improves translation quality.',
                'polylang-ai-autotranslate',
            );
            ?>
        </p>
        <?php
    }

    /**
     * Render debug mode field
     *
     * @return void
     */
    public function render_debug_mode_field(): void {
        $debug_mode = $this->settings_service->is_debug_mode();
        ?>
        <fieldset>
            <label>
                <input type="checkbox" 
                    name="pllat_debug_mode" 
                    id="pllat_debug_mode" 
                    value="1" 
                    <?php \checked( $debug_mode ); ?> />
                <?php \esc_html_e( 'Enable debug logging', 'polylang-ai-autotranslate' ); ?>
            </label>
            <p class="description">
                <?php
                \esc_html_e(
                    'Enable detailed logging for troubleshooting. Disable in production.',
                    'polylang-ai-autotranslate',
                );
                ?>
            </p>
        </fieldset>
        <?php
    }

    /**
     * Sanitize API provider selection.
     *
     * @param mixed $value The input value.
     * @return string The sanitized value.
     */
    public function sanitize_api_provider( $value ): string {
        if ( null === $value || ! \is_string( $value ) ) {
            return 'openai';
        }

        $providers = AI_Provider_Registry::get_providers_for_options();
        return \array_key_exists( $value, $providers ) ? $value : 'openai';
    }

    /**
     * Sanitize translation mode selection.
     *
     * @param mixed $value The input value.
     * @return string The sanitized value.
     */
    public function sanitize_translation_mode( $value ): string {
        if ( null === $value || ! \is_string( $value ) ) {
            return 'byok';
        }

        // Only allow 'byok' or 'credits'
        return \in_array( $value, array( 'byok', 'credits' ), true ) ? $value : 'byok';
    }

    /**
     * Sanitize model selection for a specific API.
     *
     * @param mixed       $value The input value.
     * @param string|null $api   The API provider (if not provided, uses active API).
     * @return string The sanitized value.
     */

    /**
     * Sanitize model for specific API (used in closure callbacks)
     *
     * @param mixed  $value The input value.
     * @param string $provider   The specific API provider.
     * @return string The sanitized value.
     */
    public function sanitize_model_for_api( $value, string $provider ): string {
        $models = AI_Provider_Registry::get_models_for_provider( $provider );
        error_log( print_r( $models, true ) );
        error_log( 'value: ' . print_r( $value, true ) );
        error_log( 'provider: ' . print_r( $provider, true ) );
        error_log( 'final: ' . print_r( \array_key_exists( $value, $models ) ? $value : AI_Provider_Registry::get_default_model_for_provider( $provider ), true ) );
        return \array_key_exists( $value, $models ) ? $value : AI_Provider_Registry::get_default_model_for_provider( $provider );
    }

    /**
     * Sanitize max tokens input.
     *
     * @param mixed $value The input value.
     * @return int The sanitized value.
     */
    public function sanitize_max_tokens( $value ): int {
        $tokens = \intval( $value );
        return \max( 100, \min( 32000, $tokens ) );
    }

    /**
     * Sanitize checkbox input.
     *
     * @param mixed $value The input value.
     * @return bool The sanitized value.
     */
    public function sanitize_checkbox( $value ): bool {
        return '1' === $value || 1 === $value || true === $value;
    }

    /**
     * Add API key fields for each provider
     *
     * @return void
     */
    private function add_api_key_fields(): void {
        $providers = AI_Provider_Registry::get_providers_for_options();
        foreach ( $providers as $provider => $label ) {
            \add_settings_field(
                "pllat_{$provider}_api_key",
                \sprintf( \__( '%s API Key', 'polylang-ai-autotranslate' ), $label ),
                array( $this, 'render_api_key_field' ),
                'pllat_settings',
                'pllat_main_section',
                array(
                    'provider' => $provider,
                    'label'   => $label,
                ),
            );
        }
    }

    /**
     * Get all available models for JavaScript
     *
     * @return array<string,array<string,string>>
     */
    private function get_all_models(): array {
        $providers  = AI_Provider_Registry::get_providers_for_options();
        $all_models = array();

        foreach ( \array_keys( $providers ) as $api ) {
            $all_models[ $api ] = AI_Provider_Registry::get_models_for_provider( $api );
        }

        return $all_models;
    }

    /**
     * Get current selected models for all API providers
     *
     * @return array<string,string>
     */
    private function get_current_models_for_all_apis(): array {
        $providers      = AI_Provider_Registry::get_providers_for_options();
        $current_models = array();

        foreach ( \array_keys( $providers ) as $api ) {
            $current_models[ $api ] = $this->settings_service->get_translation_model( $api );
        }

        return $current_models;
    }

    /**
     * Get API key description for a specific provider
     *
     * @param string $api The API provider.
     * @return string The description.
     */
    private function get_api_key_description( string $api ): string {
        $description = AI_Provider_Registry::get_api_key_description_for_provider( $api );
        return $description ?: \__(
            'Enter your API key for this provider.',
            'polylang-ai-autotranslate',
        );
    }

}
