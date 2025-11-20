<?php
namespace PLLAT\Translator\Models;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Translation config class.
 */
class Translation_Config implements \JsonSerializable {
    /**
     * Constructor.
     *
     * @param string   $lang_from The source language.
     * @param array    $langs_to The target languages.
     * @param array    $post_types The post types.
     * @param array    $taxonomies The taxonomies.
     * @param array    $string_groups The string groups.
     * @param array    $terms Specific terms.
     * @param array    $specific_posts Specific post IDs to translate.
     * @param array    $specific_terms Specific term IDs to translate.
     * @param string   $instructions Additional instructions for the AI.
     * @param bool     $forced Whether to force retranslation of existing content.
     * @param int|null $limit Maximum number of items to translate (null = no limit).
     */
    public function __construct(
        protected string $lang_from,
        protected array $langs_to,
        protected array $post_types,
        protected array $taxonomies,
        protected array $string_groups,
        protected array $terms,
        protected array $specific_posts = array(),
        protected array $specific_terms = array(),
        protected string $instructions = '',
        protected bool $forced = false,
        protected ?int $limit = null,
    ) {
    }

    /**
     * Get the source language.
     *
     * @return string The source language 2-letter code.
     */
    public function get_lang_from(): string {
        return $this->lang_from;
    }

    /**
     * Get the target languages.
     *
     * @return array<int, string> The target 2-letter language codes.
     */
    public function get_langs_to(): array {
        return $this->langs_to;
    }

    /**
     * Get the post types.
     *
     * @return array<int, string> The post type names.
     */
    public function get_post_types(): array {
        return $this->post_types;
    }

    /**
     * Get the taxonomies.
     *
     * @return array<int, string> The taxonomy names.
     */
    public function get_taxonomies(): array {
        return $this->taxonomies;
    }

    /**
     * Get the string groups.
     *
     * @return array<int, string> The string group names.
     */
    public function get_string_groups(): array {
        return $this->string_groups;
    }

    /**
     * Get the terms.
     *
     * @return array<int, int> The term ids.
     */
    public function get_terms(): array {
        return $this->terms;
    }

    /**
     * Update the source language.
     *
     * @param string $lang_from The source language 2-letter code.
     */
    public function set_lang_from( string $lang_from ): void {
        $this->lang_from = $lang_from;
    }

    /**
     * Update the target languages.
     *
     * @param array<int, string> $langs_to The target languages 2-letter codes.
     */
    public function set_langs_to( array $langs_to ): void {
        $this->langs_to = $langs_to;
    }

    /**
     * Update the post types.
     *
     * @param array<int, string> $post_types The post type names.
     */
    public function set_post_types( array $post_types ): void {
        $this->post_types = $post_types;
    }

    /**
     * Update the taxonomies.
     *
     * @param array<int, string> $taxonomies The taxonomy names.
     */
    public function set_taxonomies( array $taxonomies ): void {
        $this->taxonomies = $taxonomies;
    }

    /**
     * Update the string groups.
     *
     * @param array<int, string> $string_groups The string group names.
     */
    public function set_string_groups( array $string_groups ): void {
        $this->string_groups = $string_groups;
    }

    /**
     * Update the terms.
     *
     * @param array<int, int> $terms The term ids.
     */
    public function set_terms( array $terms ): void {
        $this->terms = $terms;
    }

    /**
     * Get the specific posts.
     *
     * @return array<int, int> The specific post IDs.
     */
    public function get_specific_posts(): array {
        return $this->specific_posts;
    }

    /**
     * Update the specific posts.
     *
     * @param array<int, int> $specific_posts The specific post IDs.
     */
    public function set_specific_posts( array $specific_posts ): void {
        $this->specific_posts = $specific_posts;
    }

    /**
     * Get the specific terms.
     *
     * @return array<int, int> The specific term IDs.
     */
    public function get_specific_terms(): array {
        return $this->specific_terms;
    }

    /**
     * Update the specific terms.
     *
     * @param array<int, int> $specific_terms The specific term IDs.
     */
    public function set_specific_terms( array $specific_terms ): void {
        $this->specific_terms = $specific_terms;
    }

    /**
     * Get the instructions.
     *
     * @return string The additional instructions for the AI.
     */
    public function get_instructions(): string {
        return $this->instructions;
    }

    /**
     * Update the instructions.
     *
     * @param string $instructions The additional instructions for the AI.
     */
    public function set_instructions( string $instructions ): void {
        $this->instructions = $instructions;
    }

    /**
     * Check if forced retranslation is enabled.
     *
     * @return bool Whether to force retranslation.
     */
    public function is_forced(): bool {
        return $this->forced;
    }

    /**
     * Update the forced flag.
     *
     * @param bool $forced Whether to force retranslation.
     */
    public function set_forced( bool $forced ): void {
        $this->forced = $forced;
    }

    /**
     * Get the item limit.
     *
     * @return int|null The maximum number of items to translate, or null for no limit.
     */
    public function get_limit(): ?int {
        return $this->limit;
    }

    /**
     * Update the item limit.
     *
     * @param int|null $limit The maximum number of items to translate, or null for no limit.
     */
    public function set_limit( ?int $limit ): void {
        $this->limit = $limit;
    }

    /**
     * Convert the config to an array for JSON serialization.
     *
     * @return array The config as an array.
     */
    public function jsonSerialize(): array {
        return array(
            'forced'         => $this->is_forced(),
            'instructions'   => $this->get_instructions(),
            'langs_to'       => $this->get_langs_to(),
            'lang_from'      => $this->get_lang_from(),
            'limit'          => $this->get_limit(),
            'post_types'     => $this->get_post_types(),
            'specific_posts' => $this->get_specific_posts(),
            'specific_terms' => $this->get_specific_terms(),
            'string_groups'  => $this->get_string_groups(),
            'taxonomies'     => $this->get_taxonomies(),
            'terms'          => $this->get_terms(),
        );
    }
}
