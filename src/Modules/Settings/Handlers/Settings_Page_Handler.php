<?php
/**
 * Settings_Page_Handler class file.
 *
 * @package Polylang AI Automatic Translation
 * @subpackage Settings
 */

namespace PLLAT\Settings\Handlers;

use PLLAT\Settings\Services\Settings_Form;
use PLLAT\Settings\Services\Settings_Service;
use XWP\DI\Decorators\Action;
use XWP\DI\Decorators\Handler;

/**
 * Handles the settings page registration and display.
 */
#[Handler( tag: 'init', priority: 11, context: Handler::CTX_ADMIN )]
class Settings_Page_Handler {
    /**
     * Constructor.
     *
     * @param Settings_Service $settings_service The settings service.
     * @param Settings_Form    $settings_form    The settings form renderer.
     */
    public function __construct( protected Settings_Service $settings_service, protected Settings_Form $settings_form ) {
        $this->settings_form->register_hooks();
    }

    /**
     * Register the settings page in the admin menu.
     *
     * @return void
     */
    #[Action( tag: 'admin_menu', priority: 11 )]
    public function register_admin_menu(): void {
        \add_submenu_page(
            'mlang',
            \__( 'AI Settings', 'polylang-automatic-ai-translation' ),
            \__( 'AI Settings', 'polylang-automatic-ai-translation' ),
            'manage_options',
            'pllat-settings',
            array( $this, 'render_settings_page' ),
        );
    }

    /**
     * Render the settings page.
     *
     * @return void
     */
    public function render_settings_page(): void {
        // Check user capabilities.
        if ( ! \current_user_can( 'manage_options' ) ) {
            \wp_die(
                \esc_html__(
                    'You do not have sufficient permissions to access this page.',
                    'polylang-automatic-ai-translation',
                ),
            );
        }

        // Render the form.
        $this->settings_form->render();
    }
}
