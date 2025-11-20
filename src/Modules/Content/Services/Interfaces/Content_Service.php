<?php
declare(strict_types=1);

namespace PLLAT\Content\Services\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use PLLAT\Translator\Models\Job;

/**
 * Contract for content services that persist translations to WordPress content.
 */
interface Content_Service {
    /**
     * Process a completed job by updating all translated fields.
     *
     * @param Job $job The completed job with translated tasks.
     * @return void
     */
    public function process_job( Job $job ): void;

    /**
     * Get target content ID for a job (required for Job_Processor).
     *
     * @param Job $job Job instance.
     * @return int Target content ID.
     */
    public function get_target_content_id_for_job( Job $job ): int;

    /**
     * Parse a reference key into type and field components.
     *
     * @param string $reference The reference key to parse.
     * @return array{type:string,field:string} Parsed info with 'type' and 'field'.
     */
    public function parse_reference( string $reference ): array;
}
