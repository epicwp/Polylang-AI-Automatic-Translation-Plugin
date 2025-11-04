<?php
declare(strict_types=1);

namespace PLLAT\Content\Services;

use PLLAT\Common\Interfaces\Language_Manager;
use PLLAT\Content\Services\Traits\Reference_Parsing_Trait;

/**
 * Handles updates for post content.
 */
class Post_Content_Service {
    use Reference_Parsing_Trait;

    public function __construct(
        private Language_Manager $language_manager,
    ) {
    }

    /**
     * Update a post field based on a reference key.
     * Delegates to core/meta/custom handlers.
     *
     * @param int    $post_id     Post ID.
     * @param string $reference   Reference key (core|_meta|*_custom_data*).
     * @param string $translation Translated value.
     * @return void
     */
    public function update_post_field( int $post_id, string $reference, string $translation ): void {
        $reference_info = $this->parse_reference( $reference );

        switch ( $reference_info['type'] ) {
            case 'core':
                $this->update_core_post_field( $post_id, $reference_info['field'], $translation );
                break;

            case 'meta':
                /**
                 * Filter the translation value before updating post meta.
                 *
                 * This allows integrations to normalize or transform the value
                 * before it's saved (e.g., encoding adjustments for Elementor).
                 *
                 * @param string $translation The translated value.
                 * @param int    $post_id     Post ID.
                 * @param string $meta_key    Meta key being updated.
                 */
                $translation = \apply_filters(
                    'pllat_before_update_post_meta',
                    $translation,
                    $post_id,
                    $reference_info['field']
                );

                \update_post_meta( $post_id, $reference_info['field'], $translation );

                /**
                 * Fires after a post meta field has been updated with a translation.
                 *
                 * This allows integrations (like Elementor) to clear caches or perform
                 * additional processing after specific meta fields are updated.
                 *
                 * @param int    $post_id    Post ID.
                 * @param string $meta_key   Meta key that was updated.
                 * @param string $translation New meta value (translated).
                 */
                \do_action( 'pllat_after_update_post_meta', $post_id, $reference_info['field'], $translation );
                break;

            case 'custom_data':
                \do_action(
                    'pllat_update_custom_post_field',
                    $post_id,
                    $reference_info['field'],
                    $translation,
                );
                break;

            default:
                \do_action( 'pllat_update_unknown_post_field', $post_id, $reference, $translation );
        }
    }

    /**
     * Update one of the supported core post fields.
     *
     * @param int    $post_id     Post ID.
     * @param string $field       Core field name.
     * @param string $translation Translated value.
     * @return void
     */
    public function update_core_post_field( int $post_id, string $field, string $translation ): void {
        $update_data = array( 'ID' => $post_id );

        switch ( $field ) {
            case 'post_title':
                $update_data['post_title'] = $translation;
                break;
            case 'post_content':
                $update_data['post_content'] = $translation;
                break;
            case 'post_excerpt':
                $update_data['post_excerpt'] = $translation;
                break;
            case 'post_name':
                $update_data['post_name'] = \sanitize_title( $translation );
                break;
            default:
                \do_action( 'pllat_update_unknown_core_post_field', $post_id, $field, $translation );
                return;
        }

        if ( \count( $update_data ) <= 1 ) {
            return;
        }

        \wp_update_post( $update_data );
    }

    /**
     * Resolve or infer a target post ID for the given language.
     * Falls back to source when a translation does not exist yet.
     *
     * @param int    $source_id   Source post ID.
     * @param string $target_lang Target language.
     * @return int Target post ID (or source ID fallback).
     */
    public function resolve_target_id( int $source_id, string $target_lang ): int {
        $target_id = $this->language_manager->get_post_translation( $source_id, $target_lang );
        if ( ! $target_id ) {
            $target_id = $this->language_manager->copy_post( $source_id, $target_lang );
        }
        return (int) $target_id;
    }
}
