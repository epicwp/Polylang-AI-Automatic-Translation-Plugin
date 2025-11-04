<?php
/**
 * Language Manager Interface
 *
 * @package Polylang AI Automatic Translation
 */

namespace PLLAT\Common\Interfaces;

/**
 * Interface for language management operations.
 *
 * This interface allows the plugin to work with different translation
 * providers by implementing provider-specific language managers.
 */
interface Language_Manager {
    /**
     * Get the default language of the site.
     *
     * @return string The default language code.
     */
    public function get_default_language(): string;

    /**
     * Get all available languages.
     *
     * @return array Array of language codes.
     */
    public function get_languages(): array;

    /**
     * Get all available languages (alias for get_languages for backward compatibility).
     *
     * @param bool $exclude_default Whether to exclude the default language.
     * @return array Array of language codes.
     */
    public function get_available_languages( bool $exclude_default = false ): array;

    /**
     * Get detailed language data for admin interfaces.
     *
     * @return array Array of language objects with slug, name, flag, and label.
     */
    public function get_languages_data(): array;

    /**
     * Get the post in a specific language.
     *
     * @param int    $post_id The post ID.
     * @param string $language The target language code.
     * @return int The post ID in the target language, or 0 if not found.
     */
    public function get_post_by_language( int $post_id, string $language ): int;

    /**
     * Get the term in a specific language.
     *
     * @param int    $term_id The term ID.
     * @param string $language The target language code.
     * @return int The term ID in the target language, or 0 if not found.
     */
    public function get_term_by_language( int $term_id, string $language ): int;

    /**
     * Get the language of a post.
     *
     * @param int $post_id The post ID.
     * @return string The language code of the post.
     */
    public function get_post_language( int $post_id ): string;

    /**
     * Get the language of a term.
     *
     * @param int $term_id The term ID.
     * @return string The language code of the term.
     */
    public function get_term_language( int $term_id ): string;

    /**
     * Set the language of a post.
     *
     * @param int    $post_id The post ID.
     * @param string $language The language code.
     * @return bool True on success, false on failure.
     */
    public function set_post_language( int $post_id, string $language ): bool;

    /**
     * Set the language of a term.
     *
     * @param int    $term_id The term ID.
     * @param string $language The language code.
     * @return bool True on success, false on failure.
     */
    public function set_term_language( int $term_id, string $language ): bool;

    /**
     * Check if a language is valid/exists.
     *
     * @param string $language The language code to check.
     * @return bool True if language exists, false otherwise.
     */
    public function is_valid_language( string $language ): bool;

    /**
     * Get all translations for a post.
     *
     * @param int $post_id The post ID.
     * @return array Array of language_code => post_id pairs.
     */
    public function get_post_translations( int $post_id ): array;

    /**
     * Get the translation of a post.
     *
     * @param int    $post_id The post ID.
     * @param string $language The target language code.
     * @return int The post ID in the target language, or 0 if not found.
     */
    public function get_post_translation( int $post_id, string $language ): int;

    /**
     * Get all translations for a term.
     *
     * @param int $term_id The term ID.
     * @return array Array of language_code => term_id pairs.
     */
    public function get_term_translations( int $term_id ): array;

    /**
     * Copy a post to a new language.
     *
     * @param int    $source_id   Source post ID.
     * @param string $target_lang Target language.
     * @return int The post ID in the target language, or 0 if not found.
     */
    public function copy_post( int $source_id, string $target_lang ): int;

    /**
     * Copy a term to a new language.
     *
     * @param int    $source_id   Source term ID.
     * @param string $target_lang Target language.
     * @return int The term ID in the target language, or 0 if not found.
     */
    public function copy_term( int $source_id, string $target_lang ): int;

    /**
     * Get the active Polylang post types.
     *
     * @return array The active Polylang post types.
     */
    public function get_active_post_types(): array;

    /**
     * Get the active Polylang taxonomies.
     *
     * @return array The active Polylang taxonomies.
     */
    public function get_active_taxonomies(): array;

    /**
     * Get the available Polylang post types.
     *
     * @return array The available Polylang post types.
     */
    public function get_available_post_types(): array;

    /**
     * Get the available Polylang taxonomies.
     *
     * @return array The available Polylang taxonomies.
     */
    public function get_available_taxonomies(): array;
}
