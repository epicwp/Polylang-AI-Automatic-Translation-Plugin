<?php
namespace PLLAT\Translator\Services\Bulk;

use PLLAT\Translator\Models\Run;
use PLLAT\Translator\Repositories\Job_Repository;
use PLLAT\Translator\Repositories\Query\Job_Query;
use PLLAT\Translator\Services\Interfaces\Query_Service;

/**
 * Base query service.
 */
abstract class Base_Query_Service implements Query_Service {
    /**
     * Constructor.
     *
     * @param Job_Repository $job_repository The job repository.
     */
    public function __construct(
        protected Job_Repository $job_repository,
    ) {
    }

    /**
     * Get the type of the query service.
     *
     * @return string The type of the query service.
     */
    abstract public function get_type(): string;

    /**
     * Set the job query for inactive runs.
     *
     * @param Job_Query $job_query The job query to set.
     * @return void
     */
    abstract protected function build_job_query_for_inactive_run( Job_Query $job_query, Run $run ): Job_Query;

    /**
     * Get jobs with their content data for a specific run.
     *
     * @param Run $run The run to get jobs for.
     * @param int $offset The offset to start from.
     * @param int $limit The limit of jobs to fetch.
     * @return array Array of posts with job data included.
     */
    public function get_jobs_for_run( Run $run, int $offset = 0, int $limit = 1000 ): array {
        return $this->query_jobs_for_run( $run, $offset, $limit );
    }

    /**
     * Query jobs for the specified run.
     *
     * @param Run $run The run to fetch jobs for.
     * @param int $offset The offset to start from.
     * @param int $limit The limit of jobs to fetch.
     * @return array Array of term IDs.
     */
    protected function query_jobs_for_run( Run $run, int $offset = 0, int $limit = 1000 ): array {
        $run_status = $run->get_status();
        $job_query  = $this->job_repository->query( $this->get_type() );

        // When running only query by run id.
        if ( $run_status->isRunning() ) {
            $job_query->set_run_id( $run->get_id() );
        }

        // When not running query by lang from, langs to, and statuses.
        if ( ! $run_status->isRunning() ) {
            $job_query = $this->build_job_query_for_inactive_run( $job_query, $run );
        }

        // Add pagination.
        $job_query->limit( $limit, $offset );

        $jobs = $this->job_repository->find_by( $job_query );
        return $jobs;
    }
}
