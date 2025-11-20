<?php
namespace PLLAT\Translator\Models\Translations;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Translation_Term_Meta class.
 *
 * @package PLLAT\Translator\Models\Translations
 */
class Translation_Term_Meta extends Translation_Base {
    /**
     * Get the available fields for translation.
     *
     * @return array
     */
    public static function get_available_fields(): array {
        return \apply_filters(
            'pllat_available_term_meta_translation_fields',
            array(
                // Yoast SEO
                'wpseo_title',
                'wpseo_desc',
                'wpseo_focuskw',
                'wpseo_metakeywords',
                'wpseo_opengraph-title',
                'wpseo_opengraph-description',
                'wpseo_twitter-title',
                'wpseo_twitter-description',
                'wpseo_bctitle',

                // Rank Math SEO
                'rank_math_title',
                'rank_math_description',
                'rank_math_focus_keyword',
                'rank_math_facebook_title',
                'rank_math_facebook_description',
                'rank_math_twitter_title',
                'rank_math_twitter_description',

                // All in One SEO (AIOSEO stores term meta in custom table, not term meta)

                // SEOPress
                '_seopress_titles_title',
                '_seopress_titles_desc',
                '_seopress_social_fb_title',
                '_seopress_social_fb_desc',
                '_seopress_social_twitter_title',
                '_seopress_social_twitter_desc',
                '_seopress_analysis_target_kw',

                // Slim SEO
                'slim_seo_title',
                'slim_seo_description',
                'slim_seo_facebook_title',
                'slim_seo_facebook_description',
                'slim_seo_twitter_title',
                'slim_seo_twitter_description',
                'slim_seo_keywords',

                // The SEO Framework
                '_genesis_title',
                '_genesis_description',
                '_open_graph_title',
                '_open_graph_description',
                '_twitter_title',
                '_twitter_description',
                '_genesis_keywords',
            ),
        );
    }

    /**
     * Get the available fields for translation for a specific term.
     *
     * @param int $id The term ID.
     * @return array
     */
    public static function get_available_fields_for( int $id ): array {
        return \apply_filters(
            'pllat_available_term_meta_translation_fields_for',
            self::get_available_fields(),
            $id,
        );
    }
}
