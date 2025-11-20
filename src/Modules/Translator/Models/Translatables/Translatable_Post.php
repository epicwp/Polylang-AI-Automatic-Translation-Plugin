<?php
namespace PLLAT\Translator\Models\Translatables;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
use PLLAT\Common\Helpers;
use PLLAT\Translator\Enums\TranslatableMetaKey;
use PLLAT\Translator\Models\Translations\Translation_Post;
use PLLAT\Translator\Models\Translations\Translation_Post_Meta;

/**
 * A post that can be translated.
 *
 * @package PLLAT\Translator\Models
 */
class Translatable_Post extends Base_Translatable {
    /**
     * Gets the language slug of the post.
     *
     * @return string The language slug.
     */
    public function get_language(): string {
        return $this->language_manager->get_post_language( $this->id );
    }

    /**
     * Gets the connected translations of the post.
     *
     * @return array<string, int> Key value pairs of language slug and post id.
     */
    public function get_translations(): array {
        return $this->language_manager->get_post_translations( $this->id );
    }

    /**
     * Gets the title of the post.
     *
     * @return string The title of the post.
     */
    public function get_title(): string {
        return \get_the_title( $this->id );
    }

    /**
     * Gets the type of the post for job classification.
     * Always returns 'post' for all WordPress post types (post, page, custom post types).
     *
     * @return string The type of the post ('post').
     */
    public function get_type(): string {
        return 'post';
    }

    /**
     * Gets the WordPress content type (post_type).
     *
     * @return string The WordPress post_type (e.g., 'post', 'page', 'product').
     */
    public function get_content_type(): string {
        return \get_post_type( $this->id );
    }

    /**
     * Gets the meta data of the post.
     *
     * @param string $key The meta key.
     * @param bool   $single Whether to return a single value or an array.
     * @return mixed The meta value.
     */
    public function get_meta( string $key, bool $single = false ) {
        return \get_post_meta( $this->id, $key, $single );
    }

    /**
     * Set whether this post is excluded from translation.
     *
     * @param bool $excluded Whether to exclude from translation.
     * @return void
     */
    public function set_excluded_from_translation( bool $excluded ): void {
        if ( $excluded ) {
            \update_post_meta( $this->id, TranslatableMetaKey::Exclude->value, true );
        } else {
            \delete_post_meta( $this->id, TranslatableMetaKey::Exclude->value );
        }
    }

    /**
     * Gets the available fields for the post.
     *
     * @return array<string> The available fields.
     */
    public function get_available_fields(): array {
        return Translation_Post::get_available_fields_for( $this->id );
    }

    /**
     * Gets the available meta fields for the post.
     *
     * @return array<string> The available meta fields.
     */
    public function get_available_meta_fields(): array {
        return Translation_Post_Meta::get_available_fields_for( $this->id );
    }

    /**
     * Gets a specific field of the post data.
     *
     * @param string $field The field to get the data for.
     * @return string|null The value of the field.
     */
    public function get_data( string $field ): ?string {
        $value = \get_post_field( $field, $this->id );
        return \is_string( $value ) ? $value : null;
    }

    /**
     * Gets all the data underlying post data as an array.
     *
     * @return array<string, mixed> The data underlying post data as an array.
     */
    public function get_all_data(): array {
        return \get_post( $this->id )->to_array();
    }

    /**
     * Gets the meta data underlying post meta data as an array.
     *
     * @return array<string, mixed> The meta data underlying post meta data as an array.
     */
    public function get_all_meta( bool $flatten = true ): array {
        return $flatten ? Helpers::get_flatten_post_meta( $this->id ) : \get_post_custom( $this->id );
    }
}
