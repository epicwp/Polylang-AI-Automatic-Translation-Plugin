<?php
/**
 * Translation_Stats_Service class file.
 *
 * @package Polylang AI Automatic Translation
 * @subpackage Translator
 */

declare(strict_types=1);

namespace PLLAT\Translator\Services;

use PLLAT\Common\Helpers;
use PLLAT\Common\Interfaces\Language_Manager;
use PLLAT\Translator\Repositories\Job_Stats_Repository;

/**
 * General-purpose translation statistics service.
 * Calculates translation status across all content types and languages.
 */
class Translation_Stats_Service {
    /**
     * Constructor.
     *
     * @param Language_Manager     $language_manager The language manager.
     * @param Job_Stats_Repository $job_stats_repository The job stats repository.
     */
    public function __construct(
        private Language_Manager $language_manager,
        private Job_Stats_Repository $job_stats_repository,
    ) {
    }

    /**
     * Get comprehensive translation statistics.
     *
     * @param array<string, mixed> $filters Optional filters (content_types, languages, etc.).
     * @return array<string, mixed> Statistics data.
     */
    public function get_stats( array $filters = array() ): array {
        $cache_key = 'pllat_stats_' . \md5( \wp_json_encode( $filters ) );
        $cached    = \get_transient( $cache_key );

        if ( false !== $cached && \is_array( $cached ) ) {
            return $cached;
        }

        $lang_from = $this->language_manager->get_default_language();
        $langs_to  = $this->apply_filter(
            $this->language_manager->get_available_languages( true ),
            $filters,
            'target_languages',
        );

        $post_types = $this->apply_filter( Helpers::get_available_post_types(), $filters, 'post_types' );
        $taxonomies = $this->apply_filter( Helpers::get_available_taxonomies(), $filters, 'taxonomies' );

        $stats = array(
            'posts'            => $this->get_stats_for_type( 'post', $post_types, $lang_from, $langs_to ),
            'source_language'  => $lang_from,
            'target_languages' => \array_values( $langs_to ),
            'terms'            => $this->get_stats_for_type( 'term', $taxonomies, $lang_from, $langs_to ),
        );

        // Cache for 5 seconds.
        \set_transient( $cache_key, $stats, 5 );

        return $stats;
    }

    /**
     * Get stats for specific content type and language pair.
     *
     * @param string   $type The type (post or term).
     * @param string   $content_type The content type (post type or taxonomy).
     * @param string   $lang_from The source language.
     * @param string   $lang_to The target language.
     * @param int|null $cached_total Optional pre-calculated total to avoid duplicate queries.
     * @return array<string, mixed> Statistics array.
     */
    public function get_stats_for_content_language(
        string $type,
        string $content_type,
        string $lang_from,
        string $lang_to,
        ?int $cached_total = null,
    ): array {
        // Data source 1: Total in source language (WordPress) - use cached if provided.
        $total = $cached_total ?? $this->count_total( $type, $content_type, $lang_from );

        // Data source 2: Translated count from Polylang (hybrid architecture).
        // Polylang is authoritative for "translation exists" (manual + plugin translations).
        $translated = 'post' === $type
            ? $this->count_translated_posts( $content_type, $lang_to )
            : $this->count_translated_terms( $content_type, $lang_to );

        // Data source 3: Waiting (Our Jobs) - single query for both count and breakdown.
        $waiting_stats = $this->job_stats_repository->get_waiting_stats(
            $type,
            $content_type,
            $lang_from,
            $lang_to,
        );

        return array(
            'breakdown'        => $waiting_stats['breakdown'],
            'never_translated' => \max( 0, $total - \min( $translated, $total ) - $waiting_stats['count'] ),
            'total'            => $total,
            'translated'       => \min( $translated, $total ),
            'waiting'          => $waiting_stats['count'],
        );
    }

    /**
     * Get overall translation progress across all content.
     *
     * @return array<string, mixed> Overall progress statistics.
     */
    public function get_overall_progress(): array {
        $stats = $this->get_stats();

        $totals = $this->accumulate_totals( $stats );

        $target_language_count       = \count( $stats['target_languages'] );
        $total_possible_translations = $totals['items'] * $target_language_count;
        $total_never_translated      = $total_possible_translations - $totals['translated'] - $totals['waiting'];

        $percentage_complete = $this->calculate_percentage(
            $totals['translated'],
            $total_possible_translations,
        );

        return array(
            'percentage_complete'         => $percentage_complete,
            'total_items'                 => $totals['items'],
            'total_never_translated'      => $total_never_translated,
            'total_possible_translations' => $total_possible_translations,
            'total_translated'            => $totals['translated'],
            'total_waiting'               => $totals['waiting'],
        );
    }

    /**
     * Apply filter to array of items.
     *
     * @param array<int, string>   $items Available items.
     * @param array<string, mixed> $filters Filter array.
     * @param string               $filter_key Key in $filters array to check.
     * @return array<int, string> Filtered items.
     */
    private function apply_filter( array $items, array $filters, string $filter_key ): array {
        if ( ! isset( $filters[ $filter_key ] ) || ! \is_array( $filters[ $filter_key ] ) ) {
            return $items;
        }

        if ( 0 === \count( $filters[ $filter_key ] ) ) {
            return $items;
        }

        return \array_intersect( $items, $filters[ $filter_key ] );
    }

    /**
     * Get stats for a type (post or term) across all content types and languages.
     *
     * @param string             $type The type (post or term).
     * @param array<int, string> $content_types The content types to process.
     * @param string             $lang_from The source language.
     * @param array<int, string> $langs_to The target languages.
     * @return array<string, mixed> Statistics by content type.
     */
    private function get_stats_for_type(
        string $type,
        array $content_types,
        string $lang_from,
        array $langs_to,
    ): array {
        // Fetch all required data in batch queries upfront.
        $batch_data = $this->fetch_all_batch_data( $type, $content_types, $lang_from, $langs_to );

        // Build stats structure from batch data.
        return $this->build_stats_from_batch_data( $type, $content_types, $lang_from, $langs_to, $batch_data );
    }

    /**
     * Fetch all required batch data for stats calculation.
     *
     * @param string             $type The type (post or term).
     * @param array<int, string> $content_types The content types to process.
     * @param string             $lang_from The source language.
     * @param array<int, string> $langs_to The target languages.
     * @return array{job_stats: array} Batch data.
     */
    private function fetch_all_batch_data(
        string $type,
        array $content_types,
        string $lang_from,
        array $langs_to,
    ): array {
        return array(
            'job_stats' => $this->job_stats_repository->get_waiting_stats_batch(
                $type,
                $content_types,
                $lang_from,
                $langs_to,
            ),
        );
    }

    /**
     * Build stats structure from pre-fetched batch data.
     *
     * @param string             $type The type (post or term).
     * @param array<int, string> $content_types The content types to process.
     * @param string             $lang_from The source language.
     * @param array<int, string> $langs_to The target languages.
     * @param array              $batch_data Pre-fetched batch data.
     * @return array<string, mixed> Statistics by content type.
     */
    private function build_stats_from_batch_data(
        string $type,
        array $content_types,
        string $lang_from,
        array $langs_to,
        array $batch_data,
    ): array {
        $stats = array();

        foreach ( $content_types as $content_type ) {
            $total = $this->count_total( $type, $content_type, $lang_from );

            if ( 0 === $total ) {
                continue; // Skip empty content types.
            }

            $by_language = $this->calculate_language_stats(
                $type,
                $content_type,
                $total,
                $langs_to,
                $batch_data,
            );

            $stats[ $content_type ] = array(
                'by_language' => $by_language,
                'total'       => $total,
            );
        }

        return $stats;
    }

    /**
     * Calculate stats for all target languages of a content type.
     *
     * @param string             $type The type (post or term).
     * @param string             $content_type The content type.
     * @param int                $total Total items in source language.
     * @param array<int, string> $langs_to Target languages.
     * @param array              $batch_data Pre-fetched batch data.
     * @return array<string, mixed> Stats by language.
     */
    private function calculate_language_stats(
        string $type,
        string $content_type,
        int $total,
        array $langs_to,
        array $batch_data,
    ): array {
        $by_language = array();

        foreach ( $langs_to as $lang_to ) {
            // Get pre-fetched job stats (default to empty if not found).
            $job_stats = $batch_data['job_stats'][ $content_type ][ $lang_to ] ?? array(
                'breakdown' => array(
                    'failed'  => 0,
                    'pending' => 0,
                ),
                'count'     => 0,
            );

            // Hybrid architecture: Use Polylang for "translation exists" count.
            // Polylang is authoritative (tracks manual + plugin translations).
            $translated = 'post' === $type
                ? $this->count_translated_posts( $content_type, $lang_to )
                : $this->count_translated_terms( $content_type, $lang_to );

            $by_language[ $lang_to ] = array(
                'breakdown'        => $job_stats['breakdown'],
                'never_translated' => \max( 0, $total - \min( $translated, $total ) - $job_stats['count'] ),
                'total'            => $total,
                'translated'       => \min( $translated, $total ),
                'waiting'          => $job_stats['count'],
            );
        }

        return $by_language;
    }

    /**
     * Accumulate totals from stats array.
     *
     * @param array<string, mixed> $stats Statistics array.
     * @return array{items: int, translated: int, waiting: int} Accumulated totals.
     */
    private function accumulate_totals( array $stats ): array {
        $totals = array(
            'items'      => 0,
            'translated' => 0,
            'waiting'    => 0,
        );

        $this->accumulate_from_content_type( $stats['posts'], $totals );
        $this->accumulate_from_content_type( $stats['terms'], $totals );

        return $totals;
    }

    /**
     * Accumulate values from content type data.
     *
     * @param array<string, mixed> $content_type_data Content type statistics.
     * @param array<string, int>   $totals Totals accumulator (passed by reference).
     */
    private function accumulate_from_content_type( array $content_type_data, array &$totals ): void {
        foreach ( $content_type_data as $data ) {
            $totals['items'] += $data['total'];
            foreach ( $data['by_language'] as $lang_data ) {
                $totals['translated'] += $lang_data['translated'];
                $totals['waiting']    += $lang_data['waiting'];
            }
        }
    }

    /**
     * Calculate completion percentage.
     *
     * @param int $translated Number of translated items.
     * @param int $total Total possible translations.
     * @return float Percentage rounded to 2 decimals.
     */
    private function calculate_percentage( int $translated, int $total ): float {
        if ( 0 === $total ) {
            return 0.0;
        }

        return \round( $translated / $total * 100, 2 );
    }

    /**
     * Count total items in source language (WordPress).
     *
     * @param string $type The type (post or term).
     * @param string $content_type The content type.
     * @param string $lang_from The source language.
     * @return int The count.
     */
    private function count_total( string $type, string $content_type, string $lang_from ): int {
        if ( 'post' === $type ) {
            return $this->count_posts( $content_type, $lang_from );
        }

        return $this->count_terms( $content_type, $lang_from );
    }

    /**
     * Count posts in source language.
     *
     * @param string $post_type The post type.
     * @param string $lang_from The source language.
     * @return int The count.
     */
    private function count_posts( string $post_type, string $lang_from ): int {
        $posts = \get_posts(
            array(
                'fields'           => 'ids',
                'lang'             => $lang_from,
                'posts_per_page'   => -1,
                'post_status'      => 'publish',
                'post_type'        => $post_type,
                'suppress_filters' => false,
            ),
        );

        return \count( $posts );
    }

    /**
     * Count terms in source language.
     *
     * @param string $taxonomy The taxonomy.
     * @param string $lang_from The source language.
     * @return int The count.
     */
    private function count_terms( string $taxonomy, string $lang_from ): int {
        $terms = \get_terms(
            array(
                'fields'     => 'ids',
                'hide_empty' => false,
                'lang'       => $lang_from,
                'taxonomy'   => $taxonomy,
            ),
        );

        if ( \is_wp_error( $terms ) || ! \is_array( $terms ) ) {
            return 0;
        }

        return \count( $terms );
    }

    /**
     * Count translated posts using Polylang (memory-efficient).
     * Uses WP_Query->found_posts to avoid loading post data into memory.
     *
     * @param string $post_type The post type.
     * @param string $lang_to The target language.
     * @return int The count.
     */
    private function count_translated_posts( string $post_type, string $lang_to ): int {
        $query = new \WP_Query(
            array(
                'fields'           => 'ids',
                'lang'             => $lang_to,
                'no_found_rows'    => false, // Enable SQL_CALC_FOUND_ROWS.
                'posts_per_page'   => 1, // Only load 1 post ID.
                'post_status'      => 'publish',
                'post_type'        => $post_type,
                'suppress_filters' => false, // Allow Polylang filtering.
            ),
        );

        return $query->found_posts;
    }

    /**
     * Count translated terms using Polylang (memory-efficient).
     * Uses get_terms with fields=count to return integer directly.
     *
     * @param string $taxonomy The taxonomy.
     * @param string $lang_to The target language.
     * @return int The count.
     */
    private function count_translated_terms( string $taxonomy, string $lang_to ): int {
        $count = \get_terms(
            array(
                'fields'     => 'count', // Returns integer directly!
                'hide_empty' => false,
                'lang'       => $lang_to,
                'taxonomy'   => $taxonomy,
            ),
        );

        return \is_numeric( $count ) ? (int) $count : 0;
    }
}
