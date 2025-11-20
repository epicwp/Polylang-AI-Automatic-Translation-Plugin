<?php
// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
// phpcs:disable PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext
namespace PLLAT\Translator\Enums;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
enum JobStatus: string {
    case Pending    = 'pending';
    case InProgress = 'in_progress';
    case Completed  = 'completed';
    case Failed     = 'failed';
    case Cancelled  = 'cancelled';

    /**
     * Get statuses that can be picked up by new runs.
     * These are processable IF not in active runs.
     * Note: Failed jobs are excluded - they require manual retry.
     *
     * @return array<string> Array of status values.
     */
    public static function getProcessableStatuses(): array {
        return array(
            self::Pending->value,
            // self::Failed->value, // Excluded: Never retry automatically
        );
    }

    /**
     * Get statuses that are never processable by new runs.
     * These jobs stay with their current state/run.
     *
     * @return array<string> Array of status values.
     */
    public static function getUnprocessableStatuses(): array {
        return array(
            self::Completed->value,
            self::InProgress->value,
            self::Cancelled->value,
        );
    }

    /**
     * Get statuses representing incomplete work (for stats).
     * These should be visible in run statistics.
     *
     * @return array<string> Array of status values.
     */
    public static function getIncompleteStatuses(): array {
        return array(
            self::Pending->value,
            self::InProgress->value,
            self::Failed->value,
        );
    }

    /**
     * Get statuses that represent final/terminal states.
     *
     * @return array<string> Array of status values.
     */
    public static function getFinalStatuses(): array {
        return array(
            self::Completed->value,
            self::Failed->value,
            self::Cancelled->value,
        );
    }

    public static function getAllStatuses(): array {
        return array(
            self::Pending->value,
            self::InProgress->value,
            self::Completed->value,
            self::Failed->value,
            self::Cancelled->value,
        );
    }

    /**
     * Get statuses that count toward translation coverage.
     * Used by discovery to check if content has job coverage.
     * Excludes failed/cancelled jobs as they don't represent valid coverage.
     *
     * @return array<string> Array of status values.
     */
    public static function getCoverageStatuses(): array {
        return array(
            self::Completed->value,
            self::Pending->value,
            self::InProgress->value,
        );
    }

    /**
     * Check if this status can be processed by new runs.
     *
     * @return bool True if job can be processed.
     */
    public function isProcessable(): bool {
        return match ( $this ) {
            self::Pending, self::Failed => true,
            default => false,
        };
    }

    /**
     * Check if this status represents incomplete work.
     *
     * @return bool True if job is incomplete.
     */
    public function isIncomplete(): bool {
        return match ( $this ) {
            self::Pending, self::InProgress, self::Failed => true,
            default => false,
        };
    }

    /**
     * Check if this status represents successful completion.
     *
     * @return bool True if job completed successfully.
     */
    public function isCompleted(): bool {
        return self::Completed === $this;
    }

    /**
     * Check if this status represents a failure.
     *
     * @return bool True if job failed.
     */
    public function isFailed(): bool {
        return self::Failed === $this;
    }

    /**
     * Check if this status is a terminal state (no further processing).
     *
     * @return bool True if status is terminal.
     */
    public function isTerminal(): bool {
        return match ( $this ) {
            self::Completed, self::Cancelled => true,
            default => false,
        };
    }
}
