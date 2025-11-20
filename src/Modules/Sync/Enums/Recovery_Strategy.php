<?php
/**
 * Recovery_Strategy enum file.
 *
 * @package Polylang AI Automatic Translation
 * @subpackage Sync
 */

declare(strict_types=1);

namespace PLLAT\Sync\Enums;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Recovery strategies for stale jobs.
 *
 * Determines how to handle a job that has become stale (no activity for 10+ minutes).
 */
enum Recovery_Strategy: string {
	/**
	 * Finish the job - all tasks are completed, just apply translations.
	 */
	case Finish = 'finish';

	/**
	 * Reset the job to pending - has some progress, retry from current state.
	 */
	case Reset = 'reset';

	/**
	 * Fail the job - has exhausted tasks or too many failures.
	 */
	case Fail = 'fail';
}
