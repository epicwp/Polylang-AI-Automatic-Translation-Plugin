<?php
/**
 * Cleanup_Service class file.
 *
 * @package Polylang AI Automatic Translation
 * @subpackage Sync
 */

declare(strict_types=1);

namespace PLLAT\Sync\Services;

use PLLAT\Translator\Repositories\Job_Repository;

/**
 * Handles cleanup of jobs when content is deleted.
 *
 * Responsibilities:
 * - Remove jobs when source content is deleted (id_from)
 * - Remove jobs when target content is deleted (id_to)
 * - Archive old completed runs (future: monthly cleanup)
 */
class Cleanup_Service {
    /**
     * Constructor.
     *
     * @param Job_Repository $job_repository Job repository.
     */
    public function __construct(
        private Job_Repository $job_repository,
    ) {
    }

    /**
     * Clean up all jobs related to deleted content.
     *
     * Bidirectional cleanup:
     * - Removes jobs FROM deleted content (id_from)
     * - Removes jobs TO deleted content (id_to)
     *
     * @param string $type Content type (post or term).
     * @param int    $id   Content ID being deleted.
     * @return int Number of jobs deleted.
     */
    public function cleanup_jobs_for_content( string $type, int $id ): int {
        $deleted_count = 0;

        $deleted_count += $this->cleanup_jobs_from_content( $type, $id );
        $deleted_count += $this->cleanup_jobs_to_content( $type, $id );

        return $deleted_count;
    }

    /**
     * Clean up jobs FROM deleted content (id_from).
     *
     * Example: Post 123 is deleted → remove all jobs translating FROM post 123.
     *
     * @param string $type Content type.
     * @param int    $id   Content ID.
     * @return int Number of jobs deleted.
     */
    private function cleanup_jobs_from_content( string $type, int $id ): int {
        $jobs = $this->job_repository->find_all_by_content( $type, $id );

        foreach ( $jobs as $job ) {
            $this->job_repository->delete( $job );
        }

        return \count( $jobs );
    }

    /**
     * Clean up jobs TO deleted content (id_to).
     *
     * Example: Post 456 is deleted → remove all jobs translating TO post 456.
     *
     * @param string $type Content type.
     * @param int    $id   Content ID.
     * @return int Number of jobs deleted.
     */
    private function cleanup_jobs_to_content( string $type, int $id ): int {
        $jobs = $this->job_repository->find_all_by_target_id( $type, $id );

        foreach ( $jobs as $job ) {
            $this->job_repository->delete( $job );
        }

        return \count( $jobs );
    }
}
