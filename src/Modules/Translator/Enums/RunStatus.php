<?php
// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
// phpcs:disable PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext
namespace PLLAT\Translator\Enums;

enum RunStatus: string {
    case Pending   = 'pending';
    case Running   = 'running';
    case Completed = 'completed';
    case Failed    = 'failed';
    case Cancelled = 'cancelled';

    /**
     * Get all statuses that are not running.
     *
     * @return array<string> The inactive statuses.
     */
    public static function getInactive(): array {
        $statuses = array( self::Pending, self::Failed, self::Cancelled, self::Completed );
        return $statuses;
    }

    /**
     * Get all statuses that are not running but not completed.
     *
     * @return array<string> The inactive statuses but not completed.
     */
    public static function getInactiveButNotCompleted(): array {
        $statuses = array( self::Pending, self::Failed, self::Cancelled );
        return $statuses;
    }

    /**
     * Check if the run is inactive.
     *
     * @return bool True if the run is inactive.
     */
    public function isInactive(): bool {
        return \in_array( $this, self::getInactive() );
    }

    /**
     * Check if the run is inactive but not completed.
     *
     * @return bool True if the run is inactive but not completed.
     */
    public function isInactiveButNotCompleted(): bool {
        return \in_array( $this, self::getInactiveButNotCompleted() );
    }

    /**
     * Check if the run is running.
     *
     * @return bool True if the run is running.
     */
    public function isRunning(): bool {
        return self::Running === $this;
    }

    /**
     * Check if the run is pending.
     *
     * @return bool True if the run is pending.
     */
    public function isPending(): bool {
        return self::Pending === $this;
    }

    /**
     * Check if the run is completed.
     *
     * @return bool True if the run is completed.
     */
    public function isCompleted(): bool {
        return self::Completed === $this;
    }

    /**
     * Check if the run is failed.
     *
     * @return bool True if the run is failed.
     */
    public function isFailed(): bool {
        return self::Failed === $this;
    }

    /**
     * Check if the run is cancelled.
     *
     * @return bool True if the run is cancelled.
     */
    public function isCancelled(): bool {
        return self::Cancelled === $this;
    }
}
