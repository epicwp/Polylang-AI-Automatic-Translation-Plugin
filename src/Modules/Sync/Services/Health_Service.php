<?php
/**
 * Health_Service class file.
 *
 * @package Polylang AI Automatic Translation
 * @subpackage Sync
 */

declare(strict_types=1);

namespace PLLAT\Sync\Services;

use PLLAT\Sync\Constants\Sync_Constants;

/**
 * Tracks system health metrics for monitoring.
 *
 * Responsibilities:
 * - Record recovery events
 * - Emit hooks for external monitoring
 * - Track system health indicators
 */
class Health_Service {
    /**
     * Record a recovery event.
     *
     * Emits hook with recovery statistics for external monitoring.
     *
     * @param int                $run_id The run that was recovered.
     * @param array<string, int> $stats  Recovery statistics (finished, reset, failed counts).
     * @return void
     */
    public function record_recovery_event( int $run_id, array $stats ): void {
        \do_action( Sync_Constants::HOOK_RECOVERY_COMPLETED, $run_id, $stats );
    }
}
