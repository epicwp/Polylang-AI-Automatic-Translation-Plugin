<?php
namespace PLLAT\Translator\Services\Interfaces;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
use PLLAT\Translator\Models\Run;

/**
 * Interface for query services.
 */
interface Query_Service {
    /**
     * Get the type of the query service.
     *
     * @return string The type of the query service.
     */
    public function get_type(): string;

    /**
     * Get the jobs for a run.
     *
     * @param Run $run The run to get jobs for.
     * @return array Array of jobs.
     */
    public function get_jobs_for_run( Run $run ): array;
}
