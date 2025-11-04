<?php
/**
 * Recovery_Result class file.
 *
 * @package Polylang AI Automatic Translation
 * @subpackage Sync
 */

declare(strict_types=1);

namespace PLLAT\Sync\Models;

use PLLAT\Sync\Enums\Recovery_Strategy;

/**
 * Value object for recovery operation results.
 *
 * Tracks how many jobs were recovered using each strategy.
 */
class Recovery_Result {
	/**
	 * Count of jobs that were finished (all tasks completed).
	 *
	 * @var int
	 */
	private int $finished = 0;

	/**
	 * Count of jobs that were reset to pending (for retry).
	 *
	 * @var int
	 */
	private int $reset = 0;

	/**
	 * Count of jobs that were marked as failed.
	 *
	 * @var int
	 */
	private int $failed = 0;

	/**
	 * Record a recovery operation.
	 *
	 * @param Recovery_Strategy $strategy The strategy that was used.
	 * @return void
	 */
	public function record( Recovery_Strategy $strategy ): void {
		match ( $strategy ) {
			Recovery_Strategy::Finish => ++$this->finished,
			Recovery_Strategy::Reset => ++$this->reset,
			Recovery_Strategy::Fail => ++$this->failed,
		};
	}

	/**
	 * Check if any changes were made.
	 *
	 * @return bool True if at least one job was recovered.
	 */
	public function has_changes(): bool {
		return ( $this->finished + $this->reset + $this->failed ) > 0;
	}

	/**
	 * Get total number of jobs recovered.
	 *
	 * @return int Total count.
	 */
	public function get_total(): int {
		return $this->finished + $this->reset + $this->failed;
	}

	/**
	 * Convert to array for logging/hooks.
	 *
	 * @return array<string, int> Recovery statistics.
	 */
	public function to_array(): array {
		return array(
			'failed'   => $this->failed,
			'finished' => $this->finished,
			'reset'    => $this->reset,
			'total'    => $this->get_total(),
		);
	}
}
