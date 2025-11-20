<?php
namespace PLLAT\Translator\Models\Translations;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Translation_Post_Meta class.
 *
 * @package PLLAT\Translator\Models\Translations
 */
class Translation_Post_Meta extends Translation_Base {
    /**
     * Get the available fields for translation.
     *
     * @return array
     */
    public static function get_available_fields(): array {
        return \apply_filters(
            'pllat_available_post_meta_translation_fields',
            array(
                // WordPress core.
                '_wp_attachment_image_alt',
                '_variation_description',

                // Yoast SEO.
                '_yoast_wpseo_title',
                '_yoast_wpseo_metadesc',
                '_yoast_wpseo_focuskw',
                '_yoast_wpseo_metakeywords',
                '_yoast_wpseo_opengraph-title',
                '_yoast_wpseo_opengraph-description',
                '_yoast_wpseo_twitter-title',
                '_yoast_wpseo_twitter-description',
                '_yoast_wpseo_bctitle',

                // Rank Math SEO.
                'rank_math_title',
                'rank_math_description',
                'rank_math_focus_keyword',
                'rank_math_facebook_title',
                'rank_math_facebook_description',
                'rank_math_twitter_title',
                'rank_math_twitter_description',

                // All in One SEO.
                '_aioseo_title',
                '_aioseo_description',
                '_aioseo_og_title',
                '_aioseo_og_description',
                '_aioseo_twitter_title',
                '_aioseo_twitter_description',
                '_aioseo_keywords',
                '_aioseo_focus_keyphrase',

                // SEOPress.
                '_seopress_titles_title',
                '_seopress_titles_desc',
                '_seopress_social_fb_title',
                '_seopress_social_fb_desc',
                '_seopress_social_twitter_title',
                '_seopress_social_twitter_desc',
                '_seopress_analysis_target_kw',

                // Slim SEO.
                'slim_seo_title',
                'slim_seo_description',
                'slim_seo_facebook_title',
                'slim_seo_facebook_description',
                'slim_seo_twitter_title',
                'slim_seo_twitter_description',
                'slim_seo_keywords',

                // Squirrly SEO.
                '_sq_title',
                '_sq_description',
                '_sq_keywords',
                '_sq_og_title',
                '_sq_og_description',
                '_sq_tw_title',
                '_sq_tw_description',

                // The SEO Framework.
                '_genesis_title',
                '_genesis_description',
                '_open_graph_title',
                '_open_graph_description',
                '_twitter_title',
                '_twitter_description',
                '_genesis_keywords',
                '_tsf_title_no_blogname',
            ),
        );
    }

    /**
     * Get the available fields for translation for a specific post.
     *
     * @param int $id The post ID.
     * @return array
     */
    public static function get_available_fields_for( int $id ): array {
        return \apply_filters(
            'pllat_available_post_meta_translation_fields_for',
            self::get_available_fields(),
            $id,
        );
    }
}
