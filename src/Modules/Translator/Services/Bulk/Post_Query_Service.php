<?php
namespace PLLAT\Translator\Services\Bulk;

use PLLAT\Translator\Enums\JobStatus;
use PLLAT\Translator\Models\Run;
use PLLAT\Translator\Repositories\Query\Job_Query;

/**
 * Service for querying posts with job data.
 */
class Post_Query_Service extends Base_Query_Service {
    /**
     * Get the type of the query service.
     *
     * @return string The type of the query service.
     */
    public function get_type(): string {
        return 'post';
    }

    /**
     * Build the job query for inactive runs.
     * Uses content_type column instead of JOIN for better performance (v2.0.0+).
     *
     * @param Job_Query $job_query The job query to set.
     * @param Run       $run       The run to query for.
     * @return Job_Query The job query.
     */
    protected function build_job_query_for_inactive_run( Job_Query $job_query, Run $run ): Job_Query {
        $config = $run->get_config();

        $job_query
            ->select( array( 'pllat_jobs.*' ) ) // All columns including content_type
            ->set_lang_from( $config->get_lang_from() )
            ->set_langs_to( $config->get_langs_to() );

        // Check if we have specific posts (single translation mode)
        if ( \count( $config->get_specific_posts() ) > 0 ) {
            // Specific mode: only query jobs for the specific post IDs
            $job_query->set_ids_from( $config->get_specific_posts() );
        } else {
            // Filter mode: use content_type column (no JOIN needed!)
            $job_query->set_content_types( $config->get_post_types() );
        }

        $job_query
            ->include_orphaned()
            ->include_from_inactive_runs();

        // Only filter by status if not forced retranslation
        // In force mode, we want ALL jobs regardless of status (including completed)
        if ( ! $config->is_forced() ) {
            $job_query->set_statuses( JobStatus::getProcessableStatuses() );
        }

        return $job_query;
    }
}
