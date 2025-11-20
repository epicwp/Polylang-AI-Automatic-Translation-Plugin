<?php
declare(strict_types=1);

namespace PLLAT\Content\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use PLLAT\Common\Interfaces\Language_Manager;
use PLLAT\Content\Services\Traits\Reference_Parsing_Trait;

/**
 * Handles updates for term content.
 */
class Term_Content_Service {
    use Reference_Parsing_Trait;

    /**
     * Constructor.
     *
     * @param Language_Manager $language_manager Language manager.
     */
    public function __construct(
        private Language_Manager $language_manager,
    ) {
    }

    /**
     * Update a term field based on a reference key.
     * Delegates to core/meta/custom handlers.
     *
     * @param int    $term_id     Term ID.
     * @param string $reference   Reference key (core|_meta|*_custom_data*).
     * @param string $translation Translated value.
     * @return void
     */
    public function update_term_field( int $term_id, string $reference, string $translation ): void {
        $reference_info = $this->parse_reference( $reference );

        switch ( $reference_info['type'] ) {
            case 'core':
                $this->update_core_term_field( $term_id, $reference_info['field'], $translation );
                break;
            case 'meta':
                \update_term_meta( $term_id, $reference_info['field'], $translation );
                break;
            case 'custom_data':
                \do_action(
                    'pllat_update_custom_term_field',
                    $term_id,
                    $reference_info['field'],
                    $translation,
                );
                break;
            default:
                \do_action( 'pllat_update_unknown_term_field', $term_id, $reference, $translation );
        }
    }

    /**
     * Update one of the supported core term fields.
     *
     * @param int    $term_id     Term ID.
     * @param string $field       Core field name.
     * @param string $translation Translated value.
     * @return void
     */
    public function update_core_term_field( int $term_id, string $field, string $translation ): void {
        $update_data = array();
        switch ( $field ) {
            case 'name':
                $update_data['name'] = $translation;
                break;
            case 'description':
                $update_data['description'] = $translation;
                break;
            case 'slug':
                $update_data['slug'] = \sanitize_title( $translation );
                break;
            default:
                \do_action( 'pllat_update_unknown_core_term_field', $term_id, $field, $translation );
                return;
        }
        if ( \count( $update_data ) <= 0 ) {
            return;
        }

        // Get taxonomy from term - wp_update_term requires it
        $term = \get_term( $term_id );
        if ( ! $term || \is_wp_error( $term ) ) {
            return;
        }

        \wp_update_term( $term_id, $term->taxonomy, $update_data );
    }

    /**
     * Resolve or infer a target term ID for the given language.
     * Creates a new term if translation doesn't exist (same behavior as posts).
     *
     * @param int    $source_id   Source term ID.
     * @param string $target_lang Target language.
     * @return int Target term ID.
     */
    public function resolve_target_id( int $source_id, string $target_lang ): int {
        $target_id = \pll_get_term( $source_id, $target_lang );
        if ( ! $target_id ) {
            // Create term if it doesn't exist (same as posts).
            $target_id = $this->language_manager->copy_term( $source_id, $target_lang );
        }
        return (int) $target_id;
    }
}
