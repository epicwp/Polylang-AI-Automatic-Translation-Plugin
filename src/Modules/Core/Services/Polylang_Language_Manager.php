<?php
/**
 * Polylang Language Manager Implementation
 *
 * @package Polylang AI Automatic Translation
 */

namespace PLLAT\Core\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use PLLAT\Common\Helpers;
use PLLAT\Common\Interfaces\Language_Manager;

/**
 * Polylang-specific implementation of Language_Manager interface.
 */
class Polylang_Language_Manager implements Language_Manager {
    /**
     * Get the default language of the site.
     *
     * @return string The default language code.
     */
    public function get_default_language(): string {
        return \pll_default_language();
    }

    /**
     * Get all available languages.
     *
     * @return array Array of language codes.
     */
    public function get_languages(): array {
        return \pll_languages_list( array( 'hide_empty' => false ) );
    }

    /**
     * Get all available languages (alias for get_languages for backward compatibility).
     *
     * @param bool $exclude_default Whether to exclude the default language.
     * @return array Array of language codes.
     */
    public function get_available_languages( bool $exclude_default = false ): array {
        $languages = $this->get_languages();

        if ( $exclude_default ) {
            $default_language = $this->get_default_language();
            $languages        = \array_filter( $languages, static fn( $lang ) => $lang !== $default_language );
        }

        return $languages;
    }

    /**
     * Get detailed language data for admin interfaces.
     *
     * @return array Array of language objects with slug, name, flag, and label.
     */
    public function get_languages_data(): array {
        if ( ! \function_exists( 'pll_languages_list' ) ) {
            return array();
        }

        // Get detailed language data from Polylang
        $languages = \pll_languages_list(
            array(
                'fields'     => '',  // Return full objects
                'hide_empty' => false,
            ),
        );

        if ( ! \is_array( $languages ) ) {
            return array();
        }

        $formatted_languages = array();
        foreach ( $languages as $language ) {
            if ( ! \is_object( $language ) || ! isset( $language->slug ) ) {
                continue;
            }

            $formatted_languages[] = array(
                'flag'   => $language->flag_url ?? '',
                'label'  => $language->name ?? $language->slug,
                'locale' => $language->locale ?? '',
                'name'   => $language->name ?? $language->slug,
                'slug'   => $language->slug,
            );
        }

        return $formatted_languages;
    }

    /**
     * Get the post in a specific language.
     *
     * @param int    $post_id The post ID.
     * @param string $language The target language code.
     * @return int The post ID in the target language, or 0 if not found.
     */
    public function get_post_by_language( int $post_id, string $language ): int {
        $translated_post_id = \pll_get_post( $post_id, $language );
        return $translated_post_id ? (int) $translated_post_id : 0;
    }

    /**
     * Get the term in a specific language.
     *
     * @param int    $term_id The term ID.
     * @param string $language The target language code.
     * @return int The term ID in the target language, or 0 if not found.
     */
    public function get_term_by_language( int $term_id, string $language ): int {
        $translated_term_id = \pll_get_term( $term_id, $language );
        return $translated_term_id ? (int) $translated_term_id : 0;
    }

    /**
     * Get the language of a post.
     *
     * @param int $post_id The post ID.
     * @return string The language code of the post.
     */
    public function get_post_language( int $post_id ): string {
        return \pll_get_post_language( $post_id ) ?: '';
    }

    /**
     * Get the language of a term.
     *
     * @param int $term_id The term ID.
     * @return string The language code of the term.
     */
    public function get_term_language( int $term_id ): string {
        return \pll_get_term_language( $term_id ) ?: '';
    }

    /**
     * Set the language of a post.
     *
     * @param int    $post_id The post ID.
     * @param string $language The language code.
     * @return bool True on success, false on failure.
     */
    public function set_post_language( int $post_id, string $language ): bool {
        try {
            \pll_set_post_language( $post_id, $language );
            return true;
        } catch ( \Exception ) {
            return false;
        }
    }

    /**
     * Set the language of a term.
     *
     * @param int    $term_id The term ID.
     * @param string $language The language code.
     * @return bool True on success, false on failure.
     */
    public function set_term_language( int $term_id, string $language ): bool {
        try {
            \pll_set_term_language( $term_id, $language );
            return true;
        } catch ( \Exception ) {
            return false;
        }
    }

    /**
     * Check if a language is valid/exists.
     *
     * @param string $language The language code to check.
     * @return bool True if language exists, false otherwise.
     */
    public function is_valid_language( string $language ): bool {
        $languages = $this->get_languages();
        return \in_array( $language, $languages, true );
    }

    /**
     * Get the translation of a post.
     *
     * @param int    $post_id The post ID.
     * @param string $language The target language code.
     * @return int The post ID in the target language, or 0 if not found.
     */
    public function get_post_translation( int $post_id, string $language ): int {
        return \pll_get_post( $post_id, $language );
    }

    /**
     * Get all translations for a post.
     *
     * @param int $post_id The post ID.
     * @return array Array of language_code => post_id pairs.
     */
    public function get_post_translations( int $post_id ): array {
        return \pll_get_post_translations( $post_id ) ?: array();
    }

    /**
     * Get all translations for a term.
     *
     * @param int $term_id The term ID.
     * @return array Array of language_code => term_id pairs.
     */
    public function get_term_translations( int $term_id ): array {
        return \pll_get_term_translations( $term_id ) ?: array();
    }

    /**
     * Get the active Polylang post types.
     *
     * @return array The active Polylang post types.
     */
    public function get_active_post_types(): array {
        return Helpers::get_active_post_types();
    }

    /**
     * Get the active Polylang taxonomies.
     *
     * @return array The active Polylang taxonomies.
     */
    public function get_active_taxonomies(): array {
        return Helpers::get_active_taxonomies();
    }

    /**
     * Get the available Polylang post types.
     *
     * @return array The available Polylang post types.
     */
    public function get_available_post_types(): array {
        return Helpers::get_available_post_types();
    }

    /**
     * Get the available Polylang taxonomies.
     *
     * @return array The available Polylang taxonomies.
     */
    public function get_available_taxonomies(): array {
        return Helpers::get_available_taxonomies();
    }

    /**
     * Copy a post to a new language.
     *
     * @param int    $source_id   Source post ID.
     * @param string $target_lang Target language.
     * @return int The post ID in the target language, or 0 if not found.
     */
    public function copy_post( int $source_id, string $target_lang ): int {
        return $this->is_pro() ? $this->copy_post_pro( $source_id, $target_lang ) : $this->copy_post_free(
            $source_id,
            $target_lang,
        );
    }

    /**
     * Copy a term to a new language using Polylang free copy term method.
     *
     * @param int    $source_id   Source term ID.
     * @param string $target_lang The target language code.
     * @return int The term ID in the target language, or 0 if not found.
     */
    public function copy_term( int $source_id, string $target_lang ): int {
        $term = \get_term( $source_id );
        if ( ! $term instanceof \WP_Term ) {
            return 0;
        }

        // Check if the source term has a language.
        $source_language = \PLL()->model->term->get_language( $term->term_id );
        if ( ! $source_language || $source_language->slug === $target_lang ) {
            return 0;
        }

        // Check if translation already exists.
        $existing_translation = \PLL()->model->term->get_translation( $term->term_id, $target_lang );
        if ( $existing_translation ) {
            return (int) $existing_translation;
        }

        // Duplicate the parent if the parent translation doesn't exist yet.
        $tr_parent = 0;
        if ( $term->parent ) {
            $tr_parent = \PLL()->model->term->get_translation( $term->parent, $target_lang );
            if ( ! $tr_parent ) {
                $tr_parent = $this->copy_term( $term->parent, $target_lang );
            }
        }

        // Prepare the arguments for the new term.
        $args = array(
            'description' => \wp_slash( $term->description ),
            'parent'      => $tr_parent,
        );

        // Force language or use slug.
        $args['slug'] = \PLL()->model->options['force_lang']
            ? $term->slug . '___' . $target_lang
            : \sanitize_title( $term->name ) . '-' . $target_lang;

        // Create the new term.
        $t = \wp_insert_term( \wp_slash( $term->name ), $term->taxonomy, $args );

        // Check if the new term was created successfully.
        if ( \is_wp_error( $t ) || ! \is_array( $t ) || empty( $t['term_id'] ) ) {
            return 0;
        }

        // Set the language of the new term.
        $tr_term_id = (int) $t['term_id'];
        \PLL()->model->term->set_language( $tr_term_id, $target_lang );

        // Get the translations of the source term.
        $translations                 = \PLL()->model->term->get_translations( $term->term_id );
        $translations[ $target_lang ] = $tr_term_id;

        // Save the translations of the source term.
        \PLL()->model->term->save_translations( $term->term_id, $translations );

        // Trigger the action to notify other plugins about the new term.
        \do_action( 'pll_duplicate_term', $term->term_id, $tr_term_id, $target_lang );

        return $tr_term_id;
    }

    /**
     * Check if Polylang is pro.
     *
     * @return bool True if Polylang is pro, false otherwise.
     */
    private function is_pro(): bool {
        return \property_exists( \PLL(), 'sync_post_model' );
    }

    /**
     * Copy a post to a new language using Polylang pro copy post method.
     *
     * @param int    $source_id   Source post ID.
     * @param string $language The target language code.
     * @return int The post ID in the target language, or 0 if not found.
     */
    private function copy_post_pro( int $source_id, string $language ): int {
        $post_id = \method_exists( \PLL()->sync_post_model, 'copy' ) ? \PLL()->sync_post_model->copy(
            $source_id,
            $language,
            false,
        ) : \PLL()->sync_post_model->copy_post( $source_id, $language, false );
        return $post_id;
    }

    /**
     * Copy a post to a new language using Polylang free copy post method.
     *
     * @param int    $source_id   Source post ID.
     * @param string $language The target language code.
     * @return int The post ID in the target language, or 0 if not found.
     */
    private function copy_post_free( int $source_id, string $language ): int {
        global $wpdb;

        // Get the translated post.
        $tr_id   = \PLL()->model->post->get( $source_id, \PLL()->model->get_language( $language ) );
        $tr_post = $post = \get_post( $source_id );

        if ( ! $tr_post instanceof \WP_Post ) {
            return 0;
        }

        // If the post is not translated, create a new post.
        if ( ! $tr_id ) {
            $tr_post->ID = 0;
            $tr_id       = \wp_insert_post( \wp_slash( $tr_post->to_array() ) );
            \PLL()->model->post->set_language( $tr_id, $language );

            // Get the translations of the source post
            $translations              = \PLL()->model->post->get_translations( $source_id );
            $translations[ $language ] = $tr_id;

            // Save the translations of the source post.
            \PLL()->model->post->save_translations( $source_id, $translations );

            // Copy the taxonomies of the source post.
            \PLL()->sync->taxonomies->copy( $source_id, $tr_id, $language );
            \PLL()->sync->post_metas->copy( $source_id, $tr_id, $language );

            \do_action( 'pll_save_post', $source_id, $post, $translations );
        }

        $tr_post->ID          = $tr_id;
        $tr_post->post_parent = (int) \PLL()->model->post->get( $post->post_parent, $language );

        $columns = array(
            'post_author',
            'post_date',
            'post_date_gmt',
            'post_content',
            'post_title',
            'post_excerpt',
            'comment_status',
            'ping_status',
            'post_name',
            'post_modified',
            'post_modified_gmt',
            'post_parent',
            'menu_order',
            'post_mime_type',
        );

        if ( \is_sticky( $source_id ) ) {
            \stick_post( $tr_id );
        }

        // Update the post.
        $tr_post = \array_intersect_key( (array) $tr_post, \array_combine( $columns, $columns ) );
        $wpdb->update( $wpdb->posts, $tr_post, array( 'ID' => $tr_id ) );

        // Clean the post cache.
        \clean_post_cache( $tr_id );

        return $tr_id;
    }
}
