<?php
/**
 * Sync_Service class file.
 *
 * @package Polylang AI Automatic Translation
 * @subpackage Sync
 */

namespace PLLAT\Sync\Services;

use PLLAT\Common\Interfaces\Language_Manager;
use PLLAT\Translator\Enums\JobStatus;
use PLLAT\Translator\Models\Interfaces\Translatable;
use PLLAT\Translator\Models\Job;
use PLLAT\Translator\Models\Translatables\Translatable_Post;
use PLLAT\Translator\Models\Translatables\Translatable_Term;

/**
 * Discovery orchestrator for hybrid architecture.
 * Finds content without complete translation coverage and creates jobs.
 *
 * Always-on global processor:
 * - Runs every 30 seconds
 * - Fast check if work exists (LIMIT 1 query)
 * - Processes all work or times out gracefully
 * - No status lifecycle complexity
 *
 * Hybrid approach:
 * - Polylang = Source of truth for "translation exists"
 * - Jobs = Work queue + discovery marker
 * - Completed jobs created for existing Polylang translations (prevents re-discovery)
 */
class Sync_Service {
    const ACTION          = 'pllat_discovery_process';
    const INTERVAL        = 30; // 30 seconds (fast discovery cycles).
    const TIMEOUT_PROCESS = 25; // 25 seconds processing timeout.
    const BATCH_SIZE      = 300; // Process 300 items per cycle (prevents memory overload).

    /**
     * Constructor.
     *
     * @param Language_Manager $language_manager The language manager.
     */
    public function __construct( protected Language_Manager $language_manager ) {
    }

    /**
     * Ensure the global discovery processor is scheduled.
     * This should be called once during plugin initialization.
     *
     * @return void
     */
    public function ensure_global_processor_scheduled(): void {
        // Check if already scheduled (checks ALL statuses: pending, running, scheduled).
        if ( \as_has_scheduled_action( self::ACTION, array(), 'pllat-sync' ) ) {
            return;
        }

        // Schedule recurring action every 30 seconds.
        \as_schedule_recurring_action(
            \time(),
            self::INTERVAL,
            self::ACTION,
            array(),
            'pllat-sync',
            true,
            10,
        );
    }

    /**
     * Process discovery cycle (global processor).
     * Called by Action Scheduler every 30 seconds.
     *
     * - Fast check if work exists (LIMIT 1)
     * - Process in batches with timeout protection
     * - Next iteration continues if timeout reached
     *
     * @return void
     */
    public function process_cycle(): void {
        // Fast check if work exists (LIMIT 1 for performance).
        $has_posts = array() !== $this->get_posts_needing_jobs( 1 );
        $has_terms = array() !== $this->get_terms_needing_jobs( 1 );

        if ( ! $has_posts && ! $has_terms ) {
            // No work - fast return.
            return;
        }

        // Work exists - process in batches with timeout protection.
        $start_time      = \time();
        $posts_found     = 0;
        $terms_found     = 0;
        $posts_processed = 0;
        $terms_processed = 0;

        // Process posts in batches.
        if ( $has_posts ) {
            $post_ids    = $this->get_posts_needing_jobs( self::BATCH_SIZE );
            $posts_found = \count( $post_ids );

            foreach ( $post_ids as $post_id ) {
                if ( \time() - $start_time > self::TIMEOUT_PROCESS ) {
                    // Timeout approaching - next iteration will continue.
                    break;
                }
                $this->process_post( $post_id );
                ++$posts_processed;
            }
        }

        // Process terms in batches.
        if ( $has_terms ) {
            $term_ids    = $this->get_terms_needing_jobs( self::BATCH_SIZE );
            $terms_found = \count( $term_ids );

            foreach ( $term_ids as $term_id ) {
                if ( \time() - $start_time > self::TIMEOUT_PROCESS ) {
                    // Timeout approaching - next iteration will continue.
                    break;
                }
                $this->process_term( $term_id );
                ++$terms_processed;
            }
        }

        // Log discovery results if content was processed.
        if ( $posts_processed > 0 || $terms_processed > 0 ) {
            /**
             * Action fired when discovery cycle finds and queues content for translation.
             *
             * @param int $posts_found     Number of posts found needing translation.
             * @param int $terms_found     Number of terms found needing translation.
             * @param int $posts_processed Number of posts processed in this cycle.
             * @param int $terms_processed Number of terms processed in this cycle.
             */
            \do_action(
                'pllat_discovery_cycle_completed',
                $posts_found,
                $terms_found,
                $posts_processed,
                $terms_processed,
            );
        }

        // Batch completed - next cycle will process more if needed.
    }

    /**
     * Check if system is ready for translation runs.
     * Always returns true now (no status lifecycle).
     *
     * @return bool
     */
    public function is_ready(): bool {
        return true;
    }

    /**
     * Check if ready for translation (alias for BC).
     *
     * @return bool
     */
    public function is_ready_for_translation(): bool {
        return true;
    }

    /**
     * Fast check if content analysis is needed.
     * Uses LIMIT 1 queries for maximum efficiency (~5-50ms).
     * Safe to call on every dashboard load.
     *
     * @return array{needed: bool, has_posts: bool, has_terms: bool, discovering: bool} Discovery status information.
     */
    public function check_discovery_needed(): array {
        $post_ids = $this->get_posts_needing_jobs( 1 );
        $term_ids = $this->get_terms_needing_jobs( 1 );

        $has_posts = array() !== $post_ids;
        $has_terms = array() !== $term_ids;
        $needed    = $has_posts || $has_terms;

        return array(
            'discovering' => $needed,
            'has_posts'   => $has_posts,
            'has_terms'   => $has_terms,
            'needed'      => $needed,
        );
    }

    /**
     * Get posts without complete job coverage.
     * Finds posts where job count < target language count.
     *
     * @param int|null $limit Optional limit for quick checks.
     * @return array<int> Array of post IDs.
     */
    private function get_posts_needing_jobs( ?int $limit = null ): array {
        global $wpdb;

        $lang_from         = $this->language_manager->get_default_language();
        $langs_to          = $this->language_manager->get_available_languages( true );
        $target_count      = \count( $langs_to );
        $active_types      = $this->language_manager->get_active_post_types();
        $coverage_statuses = JobStatus::getCoverageStatuses();
        $jobs_table        = $wpdb->prefix . Job::TABLE_NAME;
        $lang_tax_id       = $this->get_language_term_id( $lang_from );
        $types_ph          = $this->build_placeholders( $active_types );
        $langs_ph          = $this->build_placeholders( $langs_to );
        $statuses_ph       = $this->build_placeholders( $coverage_statuses );
        $limit_sql         = $limit ? 'LIMIT ' . (int) $limit : '';

        if ( 0 === $target_count || 0 === $lang_tax_id ) {
            return array();
        }

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $query = $wpdb->prepare(
            "
			SELECT p.ID
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->term_relationships} tr_lang
				ON tr_lang.object_id = p.ID
			INNER JOIN {$wpdb->term_taxonomy} tt_lang
				ON tt_lang.term_taxonomy_id = tr_lang.term_taxonomy_id
				AND tt_lang.taxonomy = 'language'
				AND tt_lang.term_id = %d
			LEFT JOIN {$jobs_table} j
				ON j.type = 'post'
				AND j.id_from = p.ID
				AND j.lang_from = %s
				AND j.lang_to IN ($langs_ph)
				AND j.status IN ($statuses_ph)
			WHERE p.post_status = 'publish'
				AND p.post_type IN ($types_ph)
			GROUP BY p.ID
			HAVING COUNT(DISTINCT j.lang_to) < %d
			ORDER BY p.ID ASC
			$limit_sql
			",
            $lang_tax_id,
            $lang_from,
            ...\array_merge( $langs_to, $coverage_statuses, $active_types, array( $target_count ) ),
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        return \array_map(
            'intval',
            $wpdb->get_col( $query ),
        ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * Get terms without complete job coverage.
     * Finds terms where job count < target language count.
     *
     * @param int|null $limit Optional limit for quick checks.
     * @return array<int> Array of term IDs.
     */
    private function get_terms_needing_jobs( ?int $limit = null ): array {
        global $wpdb;

        $lang_from         = $this->language_manager->get_default_language();
        $langs_to          = $this->language_manager->get_available_languages( true );
        $target_count      = \count( $langs_to );
        $active_taxs       = $this->language_manager->get_available_taxonomies();
        $coverage_statuses = JobStatus::getCoverageStatuses();
        $jobs_table        = $wpdb->prefix . Job::TABLE_NAME;
        $lang_tax_id       = $this->get_language_term_id( $lang_from );
        $term_lang_id      = $lang_tax_id + 1; // Polylang convention.
        $taxs_ph           = $this->build_placeholders( $active_taxs );
        $langs_ph          = $this->build_placeholders( $langs_to );
        $statuses_ph       = $this->build_placeholders( $coverage_statuses );
        $limit_sql         = $limit ? 'LIMIT ' . (int) $limit : '';

        if ( 0 === $target_count || 0 === $lang_tax_id ) {
            return array();
        }

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $query = $wpdb->prepare(
            "
			SELECT t.term_id
			FROM {$wpdb->terms} t
			INNER JOIN {$wpdb->term_taxonomy} tt
				ON tt.term_id = t.term_id
				AND tt.taxonomy IN ($taxs_ph)
			INNER JOIN {$wpdb->term_relationships} tr_lang
				ON tr_lang.object_id = t.term_id
			INNER JOIN {$wpdb->term_taxonomy} tt_lang
				ON tt_lang.term_taxonomy_id = tr_lang.term_taxonomy_id
				AND tt_lang.taxonomy = 'term_language'
				AND tt_lang.term_id = %d
			LEFT JOIN {$jobs_table} j
				ON j.type = 'term'
				AND j.id_from = t.term_id
				AND j.lang_from = %s
				AND j.lang_to IN ($langs_ph)
				AND j.status IN ($statuses_ph)
			GROUP BY t.term_id
			HAVING COUNT(DISTINCT j.lang_to) < %d
			ORDER BY t.term_id ASC
			$limit_sql
			",
            ...\array_merge(
                $active_taxs,
                array( $term_lang_id, $lang_from ),
                $langs_to,
                $coverage_statuses,
                array( $target_count ),
            ),
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        return \array_map(
            'intval',
            $wpdb->get_col( $query ),
        ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * Process item (post or term) during discovery.
     * Creates completed jobs for existing Polylang translations.
     * Creates pending jobs for missing translations.
     *
     * @param Translatable $translatable Translatable item to process.
     * @return void
     */
    private function process_item( Translatable $translatable ): void {
        $target_languages = $this->language_manager->get_available_languages( true );

        foreach ( $target_languages as $lang_to ) {
            // Skip if job already exists.
            if ( $translatable->has_job_for_language( $lang_to ) ) {
                continue;
            }

            // Check if Polylang translation exists.
            $translation_id = $translatable->get_translation_id( $lang_to );

            if ( $translation_id > 0 ) {
                // Translation exists → create completed job (prevents re-discovery).
                $translatable->create_completed_job( $lang_to, $translation_id );
            } else {
                // Translation missing → create pending job with tasks (needs translation).
                $translatable->create_pending_job( $lang_to );
                $translatable->collect_tasks_for_languages( array( $lang_to ) );
            }
        }
    }

    /**
     * Process post during discovery.
     * Creates completed jobs for existing Polylang translations.
     * Creates pending jobs for missing translations.
     *
     * @param int $post_id Post ID to process.
     * @return void
     *
     * phpcs:disable SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh
     */
    private function process_post( int $post_id ): void {
        // Only create jobs for default language posts.
        $post_lang    = $this->language_manager->get_post_language( $post_id );
        $default_lang = $this->language_manager->get_default_language();

        if ( $post_lang !== $default_lang ) {
            return; // Skip non-default language posts.
        }

        $translatable = Translatable_Post::get_instance( $post_id );
        $this->process_item( $translatable );
    }

    /**
     * Process term during discovery.
     * Creates completed jobs for existing Polylang translations.
     * Creates pending jobs for missing translations.
     *
     * @param int $term_id Term ID to process.
     * @return void
     */
    private function process_term( int $term_id ): void {
        // Only create jobs for default language terms.
        $term_lang    = $this->language_manager->get_term_language( $term_id );
        $default_lang = $this->language_manager->get_default_language();

        if ( $term_lang !== $default_lang ) {
            return; // Skip non-default language terms.
        }

        $translatable = Translatable_Term::get_instance( $term_id );
        $this->process_item( $translatable );
    }

    /**
     * Get Polylang language term ID for a language slug.
     *
     * @param string $lang_slug Language slug (e.g., 'en', 'de').
     * @return int Language term ID, or 0 if not found.
     */
    private function get_language_term_id( string $lang_slug ): int {
        $term = \get_term_by( 'slug', $lang_slug, 'language' );
        return $term instanceof \WP_Term ? $term->term_id : 0;
    }

    /**
     * Build placeholders for a given array.
     *
     * @param array $array The array to build placeholders for.
     * @return string The placeholders.
     */
    private function build_placeholders( array $array ): string {
        return \implode( ',', \array_fill( 0, \count( $array ), '%s' ) );
    }
}
