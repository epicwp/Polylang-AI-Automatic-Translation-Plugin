<?php
declare(strict_types=1);

namespace PLLAT\Translator\Repositories;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
use PLLAT\Translator\Models\Job;

/**
 * Repository for Job statistics and aggregation queries.
 */
class Job_Stats_Repository {
    /**
     * Get waiting statistics (count + breakdown) for specific content type and language pair.
     *
     * @param string $type The type (post or term).
     * @param string $content_type The content type (post type or taxonomy).
     * @param string $lang_from The source language.
     * @param string $lang_to The target language.
     * @return array{count: int, breakdown: array{pending: int, failed: int}} Statistics.
     */
    public function get_waiting_stats(
        string $type,
        string $content_type,
        string $lang_from,
        string $lang_to,
    ): array {
        global $wpdb;

        $jobs_table          = $wpdb->prefix . Job::TABLE_NAME;
        $posts_table         = $wpdb->posts;
        $term_taxonomy_table = $wpdb->term_taxonomy;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $query = "
			SELECT j.status, COUNT(*) as count
			FROM {$jobs_table} j
			LEFT JOIN {$posts_table} p ON j.type = 'post' AND j.id_from = p.ID
			LEFT JOIN {$term_taxonomy_table} tt ON j.type = 'term' AND j.id_from = tt.term_id
			WHERE j.type = %s
				AND j.lang_from = %s
				AND j.lang_to = %s
				AND j.status IN ('pending', 'failed')
				AND COALESCE(p.post_type, tt.taxonomy) = %s
			GROUP BY j.status
		";

        $results = $wpdb->get_results(
            $wpdb->prepare(
                $query,
                $type,
                $lang_from,
                $lang_to,
                $content_type,
            ),
            ARRAY_A,
        );

        $breakdown = array(
            'failed'  => 0,
            'pending' => 0,
        );

        $total = 0;
        foreach ( $results as $row ) {
            $count                       = (int) $row['count'];
            $breakdown[ $row['status'] ] = $count;
            $total                      += $count;
        }

        return array(
            'breakdown' => $breakdown,
            'count'     => $total,
        );
    }

    /**
     * Get waiting statistics for multiple content types and languages in one batch query.
     * Eliminates N+1 query problem.
     *
     * @param string             $type The type (post or term).
     * @param array<int, string> $content_types The content types.
     * @param string             $lang_from The source language.
     * @param array<int, string> $langs_to The target languages.
     * @return array<string, array<string, array{count: int, breakdown: array{pending: int, failed: int}}>> Nested stats.
     */
    public function get_waiting_stats_batch(
        string $type,
        array $content_types,
        string $lang_from,
        array $langs_to,
    ): array {
        if ( 0 === \count( $content_types ) || 0 === \count( $langs_to ) ) {
            return array();
        }

        global $wpdb;

        $jobs_table                 = $wpdb->prefix . Job::TABLE_NAME;
        $content_types_placeholders = \implode( ',', \array_fill( 0, \count( $content_types ), '%s' ) );
        $langs_to_placeholders      = \implode( ',', \array_fill( 0, \count( $langs_to ), '%s' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $query = "
			SELECT
				j.content_type,
				j.lang_to,
				j.status,
				COUNT(*) as count
			FROM {$jobs_table} j
			WHERE j.type = %s
				AND j.lang_from = %s
				AND j.lang_to IN ({$langs_to_placeholders})
				AND j.status IN ('pending', 'failed')
				AND j.content_type IN ({$content_types_placeholders})
			GROUP BY j.content_type, j.lang_to, j.status
		";

        $prepare_args = \array_merge( array( $type, $lang_from ), $langs_to, $content_types );

        $results = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->prepare( $query, $prepare_args ),
            ARRAY_A,
        );

        return $this->build_stats_structure( $results, $content_types, $langs_to );
    }

    /**
     * Build stats structure from query results.
     *
     * @param array<int, array<string, mixed>> $results Query results.
     * @param array<int, string>               $content_types Content types.
     * @param array<int, string>               $langs_to Target languages.
     * @return array<string, array<string, array{count: int, breakdown: array{pending: int, failed: int}}>> Stats structure.
     */
    private function build_stats_structure( array $results, array $content_types, array $langs_to ): array {
        // Initialize empty structure for all combinations.
        $stats = $this->initialize_stats_structure( $content_types, $langs_to );

        // Fill in actual data from query results.
        foreach ( $results as $row ) {
            $content_type = $row['content_type'];
            $lang_to      = $row['lang_to'];
            $status       = $row['status'];
            $count        = (int) $row['count'];

            $stats[ $content_type ][ $lang_to ]['breakdown'][ $status ] = $count;
            $stats[ $content_type ][ $lang_to ]['count']               += $count;
        }

        return $stats;
    }

    /**
     * Initialize empty stats structure.
     *
     * @param array<int, string> $content_types Content types.
     * @param array<int, string> $langs_to Target languages.
     * @return array<string, array<string, array{count: int, breakdown: array{pending: int, failed: int}}>> Empty structure.
     */
    private function initialize_stats_structure( array $content_types, array $langs_to ): array {
        $stats = array();
        foreach ( $content_types as $content_type ) {
            foreach ( $langs_to as $lang_to ) {
                $stats[ $content_type ][ $lang_to ] = array(
                    'breakdown' => array(
                        'failed'  => 0,
                        'pending' => 0,
                    ),
                    'count'     => 0,
                );
            }
        }
        return $stats;
    }

    /**
     * Get progress statistics for a specific run.
     * Returns job counts broken down by status for jobs in this run only.
     *
     * @param int $run_id The run ID.
     * @return array{total: int, pending: int, in_progress: int, completed: int, failed: int} Job counts by status.
     */
    public function get_run_progress( int $run_id ): array {
        global $wpdb;

        $jobs_table = $wpdb->prefix . Job::TABLE_NAME;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $query = "
			SELECT status, COUNT(*) as count
			FROM {$jobs_table}
			WHERE run_id = %d
			GROUP BY status
		";

        $results = $wpdb->get_results(
            $wpdb->prepare( $query, $run_id ),
            ARRAY_A,
        );

        $progress = array(
            'completed'   => 0,
            'failed'      => 0,
            'in_progress' => 0,
            'pending'     => 0,
            'total'       => 0,
        );

        foreach ( $results as $row ) {
            $count                       = (int) $row['count'];
            $progress[ $row['status'] ]  = $count;
            $progress['total']          += $count;
        }

        return $progress;
    }
}
