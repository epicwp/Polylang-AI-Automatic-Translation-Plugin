<?php
/**
 * Sync_Constants class file.
 *
 * @package Polylang AI Automatic Translation
 * @subpackage Sync
 */

declare(strict_types=1);

namespace PLLAT\Sync\Constants;

/**
 * Centralized constants for Sync module.
 * All timeouts, limits, and configuration values in one place.
 *
 * This is a constants-only class - cannot be instantiated or extended.
 */
final class Sync_Constants {
    /**
     * Stale detection timeouts (in seconds).
     *
     * These define when jobs/runs are considered "stale" (no activity).
     */
    public const STALE_JOB_TIMEOUT = 600;  // 10 minutes without updates = stale job.
    public const STALE_RUN_TIMEOUT = 900;  // 15 minutes without heartbeat = stale run.

    /**
     * Task retry limits.
     *
     * Maximum attempts before a task is considered "exhausted" (permanently failed).
     */
    public const MAX_TASK_ATTEMPTS = 3;

    /**
     * Dashboard cache configuration.
     *
     * Cache keys and TTL values for dashboard statistics.
     */
    public const DASHBOARD_CACHE_KEY        = 'pllat_dashboard_stats_v1';
    public const DASHBOARD_CACHE_TTL_ACTIVE = 3;   // 3 seconds when runs are active.
    public const DASHBOARD_CACHE_TTL_IDLE   = 60;  // 60 seconds when system is idle.

    /**
     * Cron schedule intervals.
     *
     * How often scheduled maintenance tasks run.
     */
    public const RECOVERY_CRON_INTERVAL    = HOUR_IN_SECONDS;              // Hourly recovery checks.
    public const CLEANUP_OLD_RUNS_INTERVAL = DAY_IN_SECONDS * 30;          // Monthly old run cleanup.

    /**
     * Data retention policies.
     *
     * How long to keep old data before archiving/deletion.
     */
    public const OLD_RUN_RETENTION_DAYS = 90;  // Keep completed runs for 90 days.

    /**
     * WordPress action/filter hook names.
     *
     * Standardized hook names for sync events.
     */
    public const HOOK_RECOVERY_CRON      = 'pllat_sync_recovery';
    public const HOOK_CLEANUP_OLD_RUNS   = 'pllat_sync_cleanup_old_runs';
    public const HOOK_JOB_COMPLETED      = 'pllat_sync_job_completed';
    public const HOOK_RECOVERY_COMPLETED = 'pllat_sync_recovery_completed';

    /**
     * Private constructor - prevent instantiation.
     *
     * This is a constants-only class and should never be instantiated.
     */
    private function __construct() {
        // Intentionally empty - prevent instantiation.
    }
}
