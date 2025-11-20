<?php
/**
 * Meta_Box_Handler class file.
 *
 * @package Polylang AI Automatic Translation
 * @subpackage Single_Translator
 */

declare(strict_types=1);

namespace PLLAT\Single_Translator\Handlers;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use PLLAT\Common\Helpers;
use PLLAT\Common\Interfaces\Language_Manager;
use PLLAT\Common\Services\Asset_Service;
use XWP\DI\Decorators\Action;
use XWP\DI\Decorators\Handler;

/**
 * Handler for registering meta boxes on post and term edit pages.
 */
#[Handler( tag: 'init', priority: 11, context: Handler::CTX_ADMIN )]
class Meta_Box_Handler {
    /**
     * Constructor.
     *
     * @param Language_Manager $language_manager The language manager.
     * @param Asset_Service    $asset_service    The asset service.
     */
    public function __construct(
        private Language_Manager $language_manager,
        private Asset_Service $asset_service,
    ) {
    }

    /**
     * Enqueue assets for post edit pages.
     *
     * @return void
     */
    #[Action( tag: 'admin_enqueue_scripts' )]
    public function enqueue_post_assets(): void {
        $screen = \get_current_screen();

        if ( ! $screen || 'post' !== $screen->base ) {
            return;
        }

        // Check if post type is active.
        if ( ! \in_array( $screen->post_type, Helpers::get_active_post_types(), true ) ) {
            return;
        }

        // Get post ID.
        $post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        if ( 0 === $post_id ) {
            return;
        }

        // Enqueue assets.
        $this->enqueue_assets( 'post', $post_id );
    }

    /**
     * Register meta box for posts.
     *
     * @return void
     */
    #[Action( tag: 'add_meta_boxes' )]
    public function register_post_meta_box(): void {
        // Check if AI settings are configured.
        if ( ! $this->is_ai_configured() ) {
            return;
        }

        $post_types = Helpers::get_active_post_types();

        foreach ( $post_types as $post_type ) {
            \add_meta_box(
                'pllat-single-translator',
                \__( 'AI Translation', 'epicwp-ai-translation-for-polylang' ),
                array( $this, 'render_post_meta_box' ),
                $post_type,
                'normal',
                'high',
            );
        }
    }

    /**
     * Render meta box for posts.
     *
     * @param \WP_Post $post The post object.
     * @return void
     */
    public function render_post_meta_box( \WP_Post $post ): void {
        // Render React app container.
        // Any-to-any translation: works from any language post.
        echo '<div id="pllat-single-translator-root" data-type="post" data-id="' . \esc_attr(
            (string) $post->ID,
        ) . '"></div>';
    }

    /**
     * Maybe render meta box for terms.
     * Terms don't have a standard meta box API, so we hook into term edit page.
     *
     * @return void
     */
    #[Action( tag: 'admin_enqueue_scripts' )]
    public function maybe_render_term_meta_box(): void {
        $screen = \get_current_screen();

        if ( ! $screen || 'term' !== $screen->base ) {
            return;
        }

        // Check if AI settings are configured.
        if ( ! $this->is_ai_configured() ) {
            return;
        }

        // Check if taxonomy is active.
        $taxonomies = Helpers::get_available_taxonomies();
        if ( ! \in_array( $screen->taxonomy, $taxonomies, true ) ) {
            return;
        }

        // Get term ID from URL.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $term_id = isset( $_GET['tag_ID'] ) ? (int) $_GET['tag_ID'] : 0;

        if ( 0 === $term_id ) {
            return;
        }

        // Any-to-any translation: Add meta box for terms in any language.
        // Add meta box via JavaScript (since terms don't have native meta box API).
        \add_action(
            'admin_footer',
            function () use ( $term_id ) {
                $this->render_term_meta_box( $term_id );
            },
        );

        // Enqueue scripts and styles.
        $this->enqueue_assets( 'term', $term_id );
    }

    /**
     * Render meta box for terms.
     *
     * @param int $term_id The term ID.
     * @return void
     */
    private function render_term_meta_box( int $term_id ): void {
        ?>
        <script type="text/javascript">
        (function($) {
            if (!$) return;

            // Create meta box HTML
            var metaBox = $('<div class="postbox">')
                .attr('id', 'pllat-single-translator-term')
                .css('margin-top', '20px');

            var metaBoxHeader = $('<div class="postbox-header">')
                .append(
                    $('<h2 class="hndle">').text('
                    <?php
                    echo \esc_js(
                        \__( 'AI Translation', 'epicwp-ai-translation-for-polylang' ),
                    );
                    ?>
                                                    ')
                );

            var metaBoxBody = $('<div class="inside">')
                .append(
                    $('<div id="pllat-single-translator-root">')
                        .attr('data-type', 'term')
                        .attr('data-id', '<?php echo \esc_js( (string) $term_id ); ?>')
                );

            metaBox.append(metaBoxHeader).append(metaBoxBody);

            // Insert after term name field
            $('.term-name-wrap').after(metaBox);
        })(window.jQuery);
        </script>
        <?php
    }

    /**
     * Enqueue assets for the meta box.
     *
     * @param string $type Content type (post or term).
     * @param int    $id   Content ID.
     * @return void
     */
    private function enqueue_assets( string $type, int $id ): void {
        $asset_file = PLLAT_PLUGIN_DIR . 'build/admin/single-translator.asset.php';

        if ( ! \file_exists( $asset_file ) ) {
            return;
        }

        $asset = require $asset_file;

        // Enqueue script.
        \wp_enqueue_script(
            'pllat-single-translator',
            PLLAT_PLUGIN_URL . 'build/admin/single-translator.js',
            $asset['dependencies'],
            $asset['version'],
            true,
        );

        // Enqueue WordPress component styles (no custom CSS needed).
        \wp_enqueue_style( 'wp-components' );

        // Get current content language for any-to-any translation.
        $current_lang = 'post' === $type
            ? $this->language_manager->get_post_language( $id )
            : $this->language_manager->get_term_language( $id );

        // Localize script with initial data.
        \wp_localize_script(
            'pllat-single-translator',
            'pllatSingleTranslator',
            array(
                'apiUrl'   => \rest_url( 'pllat/v1/single-translator' ),
                'id'       => $id,
                'language' => array(
                    'currentLang' => $current_lang,
                    'defaultLang' => $this->language_manager->get_default_language(),
                    'languages'   => $this->get_language_list( $current_lang ),
                ),
                'nonce'    => \wp_create_nonce( 'pllat-single-translator' ),
                'type'     => $type,
            ),
        );

        // Also add global pllat object for shared utilities (flag display, icons, etc).
        \wp_localize_script(
            'pllat-single-translator',
            'pllat',
            array(
                'assets'    => $this->asset_service->get_shared_assets(),
                'languages' => $this->language_manager->get_languages_data(),
            ),
        );
    }

    /**
     * Check if AI is configured.
     *
     * @return bool True if AI provider is configured.
     */
    private function is_ai_configured(): bool {
        $provider = \get_option( 'pllat_provider' );
        return null !== $provider && '' !== $provider;
    }

    /**
     * Get list of available languages for localization.
     * Returns full language data including flags to match dashboard format.
     *
     * @param string $exclude_lang Language to exclude (current content language for any-to-any translation).
     * @return array Language data with slug, name, and flag.
     */
    private function get_language_list( string $exclude_lang ): array {
        $languages_data = $this->language_manager->get_languages_data();
        $languages      = array();

        foreach ( $languages_data as $lang_data ) {
            // Handle both object and array format.
            $slug = \is_object( $lang_data ) ? $lang_data->slug : $lang_data['slug'];
            $name = \is_object( $lang_data ) ? $lang_data->name : $lang_data['name'];
            $flag = \is_object( $lang_data ) ? $lang_data->flag : ( $lang_data['flag'] ?? '' );

            // Skip current content language (can't translate to same language).
            if ( $slug === $exclude_lang ) {
                continue;
            }

            $languages[] = array(
                'flag' => $flag,
                'name' => $name,
                'slug' => $slug,
            );
        }

        return $languages;
    }
}
