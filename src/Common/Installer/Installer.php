<?php
declare(strict_types=1);

namespace PLLAT\Common\Installer;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use PLLAT\Translator\Models\Run;
use PLLAT\Translator\Models\Task;

/**
 * Handles DB schema creation and upgrades for this plugin.
 */
class Installer {
    /**
     * Ensure current schema is installed.
     */
    public static function install(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $runs_table  = $wpdb->prefix . 'pllat_bulk_runs';
        $jobs_table  = $wpdb->prefix . 'pllat_jobs';
        $tasks_table = $wpdb->prefix . 'pllat_tasks';

        // Runs.
        $sql_runs = "CREATE TABLE {$runs_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            config LONGTEXT NOT NULL,
            created_at BIGINT UNSIGNED NOT NULL DEFAULT 0,
            started_at BIGINT UNSIGNED NOT NULL DEFAULT 0,
            last_heartbeat BIGINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY status (status)
        ) {$charset_collate};";

        // Jobs.
        $sql_jobs = "CREATE TABLE {$jobs_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            type VARCHAR(20) NOT NULL,
            content_type VARCHAR(50) NOT NULL DEFAULT '',
            id_from BIGINT UNSIGNED NOT NULL,
            id_to BIGINT UNSIGNED NULL,
            lang_from VARCHAR(10) NOT NULL,
            lang_to VARCHAR(10) NOT NULL,
            run_id BIGINT UNSIGNED NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at BIGINT UNSIGNED NOT NULL DEFAULT 0,
            started_at BIGINT UNSIGNED NOT NULL DEFAULT 0,
            completed_at BIGINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY run_id (run_id),
            KEY id_from (id_from),
            KEY id_to (id_to),
            KEY status (status),
            KEY idx_stats_lookup (type, content_type, lang_from, lang_to, status),
            KEY idx_item_status (type, id_from, lang_to, status),
            KEY idx_run_processing (run_id, status, id)
        ) {$charset_collate};";

        // Tasks.
        $sql_tasks = "CREATE TABLE {$tasks_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            job_id BIGINT UNSIGNED NOT NULL,
            reference VARCHAR(191) NOT NULL,
            value LONGTEXT NULL,
            translation LONGTEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            issue TEXT NULL,
            PRIMARY KEY  (id),
            KEY job_id (job_id),
            KEY status (status)
        ) {$charset_collate};";

        \dbDelta( $sql_runs );
        \dbDelta( $sql_jobs );
        \dbDelta( $sql_tasks );

        // Record DB version and timestamp.
        \update_option( 'pllat_db_version', \defined( 'PLLAT_DB_VERSION' ) ? PLLAT_DB_VERSION : '1.0.0' );
        \update_option( 'pllat_db_installed_at', \time() );
    }

    /**
     * Upgrade schema if needed.
     */
    public function maybe_upgrade(): void {
        $current = \get_option( 'pllat_db_version' );
        $target  = \defined( 'PLLAT_DB_VERSION' ) ? PLLAT_DB_VERSION : '1.0.0';
        if ( $current === $target ) {
            return;
        }

        // Run schema update.
        self::install();

        // Run migrations.
        self::run_migrations( $current, $target );
    }

    /**
     * Run migrations based on version changes.
     *
     * @param string $from_version The current version.
     * @param string $to_version The target version.
     * @return void
     */
    private static function run_migrations( string $from_version, string $to_version ): void {
        // Migration to 2.0.0 - Backfill content_type column.
        if ( version_compare( $from_version, '2.0.0', '<' ) && version_compare( $to_version, '2.0.0', '>=' ) ) {
            self::migrate_to_2_0_0();
        }

        // Migration to 2.2.0 - Add id_to column and backfill.
        if ( version_compare( $from_version, '2.2.0', '<' ) && version_compare( $to_version, '2.2.0', '>=' ) ) {
            self::migrate_to_2_2_0();
        }

        // Migration to 2.3.0 - Add unique indexes and CASCADE DELETE for stability.
        if ( version_compare( $from_version, '2.3.0', '<' ) && version_compare( $to_version, '2.3.0', '>=' ) ) {
            self::migrate_to_2_3_0();
        }
    }

    /**
     * Migration to 2.0.0: Backfill content_type column.
     *
     * @return void
     */
    private static function migrate_to_2_0_0(): void {
        global $wpdb;

        $jobs_table = $wpdb->prefix . 'pllat_jobs';

        // Backfill content_type for posts.
        $wpdb->query(
            "UPDATE {$jobs_table} j
            INNER JOIN {$wpdb->posts} p ON j.type = 'post' AND j.id_from = p.ID
            SET j.content_type = p.post_type
            WHERE j.type = 'post' AND j.content_type = ''"
        );

        // Backfill content_type for terms.
        $wpdb->query(
            "UPDATE {$jobs_table} j
            INNER JOIN {$wpdb->term_taxonomy} tt ON j.type = 'term' AND j.id_from = tt.term_id
            SET j.content_type = tt.taxonomy
            WHERE j.type = 'term' AND j.content_type = ''"
        );
    }

    /**
     * Migration to 2.2.0: Add id_to column.
     * The id_to column stores the target content ID for bidirectional job cleanup.
     * No backfill needed - tables will be cleared as plugin is not yet in production.
     *
     * @return void
     */
    private static function migrate_to_2_2_0(): void {
        global $wpdb;

        $jobs_table = $wpdb->prefix . 'pllat_jobs';
        $tasks_table = $wpdb->prefix . 'pllat_tasks';

        // Clear existing data - plugin not yet in production.
        $wpdb->query( "TRUNCATE TABLE {$tasks_table}" );
        $wpdb->query( "TRUNCATE TABLE {$jobs_table}" );
    }

    /**
     * Migration to 2.3.0: Add foreign key constraints and unique indexes for data integrity.
     * Orchestrates: orphan cleanup, foreign key constraints, and unique indexes.
     *
     * @return void
     */
    private static function migrate_to_2_3_0(): void {
        global $wpdb;

        $runs_table  = $wpdb->prefix . 'pllat_bulk_runs';
        $jobs_table  = $wpdb->prefix . 'pllat_jobs';
        $tasks_table = $wpdb->prefix . 'pllat_tasks';

        // Step 1: Clean up existing orphaned records.
        self::cleanup_orphaned_records( $runs_table, $jobs_table, $tasks_table );

        // Step 2: Add foreign key constraints for automatic future cleanup.
        self::add_foreign_key_constraints( $runs_table, $jobs_table, $tasks_table );

        // Step 3: Add unique indexes for duplicate prevention.
        self::add_unique_indexes( $jobs_table );
    }

    /**
     * Clean up orphaned tasks and jobs before adding constraints.
     *
     * @param string $runs_table Runs table name.
     * @param string $jobs_table Jobs table name.
     * @param string $tasks_table Tasks table name.
     * @return void
     */
    private static function cleanup_orphaned_records( string $runs_table, string $jobs_table, string $tasks_table ): void {
        global $wpdb;

        // Clean orphaned tasks (no parent job).
        $wpdb->query(
            "DELETE t FROM {$tasks_table} t
            LEFT JOIN {$jobs_table} j ON t.job_id = j.id
            WHERE j.id IS NULL"
        );

        // Clean orphaned jobs (no parent run, only if run_id is set).
        $wpdb->query(
            "DELETE j FROM {$jobs_table} j
            LEFT JOIN {$runs_table} r ON j.run_id = r.id
            WHERE j.run_id IS NOT NULL AND r.id IS NULL"
        );
    }

    /**
     * Add foreign key constraints for automatic orphan cleanup.
     *
     * @param string $runs_table Runs table name.
     * @param string $jobs_table Jobs table name.
     * @param string $tasks_table Tasks table name.
     * @return void
     */
    private static function add_foreign_key_constraints( string $runs_table, string $jobs_table, string $tasks_table ): void {
        global $wpdb;

        // Add FK: tasks.job_id -> jobs.id (CASCADE DELETE).
        if ( ! self::constraint_exists( $tasks_table, 'fk_tasks_job_id' ) ) {
            $wpdb->query(
                "ALTER TABLE {$tasks_table}
                ADD CONSTRAINT fk_tasks_job_id
                FOREIGN KEY (job_id) REFERENCES {$jobs_table}(id)
                ON DELETE CASCADE"
            );
        }

        // Add FK: jobs.run_id -> runs.id (SET NULL for history preservation).
        if ( ! self::constraint_exists( $jobs_table, 'fk_jobs_run_id' ) ) {
            $wpdb->query(
                "ALTER TABLE {$jobs_table}
                ADD CONSTRAINT fk_jobs_run_id
                FOREIGN KEY (run_id) REFERENCES {$runs_table}(id)
                ON DELETE SET NULL"
            );
        }
    }

    /**
     * Add unique indexes to prevent duplicate active jobs.
     *
     * @param string $jobs_table Jobs table name.
     * @return void
     */
    private static function add_unique_indexes( string $jobs_table ): void {
        global $wpdb;

        // Add unique index on (type, id_from, lang_to, status, run_id).
        if ( ! self::index_exists( $jobs_table, 'idx_unique_active_job' ) ) {
            $wpdb->query(
                "ALTER TABLE {$jobs_table}
                ADD UNIQUE KEY idx_unique_active_job (type, id_from, lang_to, status, run_id)"
            );
        }
    }

    /**
     * Check if a foreign key constraint exists.
     *
     * @param string $table_name Table name (without prefix).
     * @param string $constraint_name Constraint name.
     * @return bool True if constraint exists.
     */
    private static function constraint_exists( string $table_name, string $constraint_name ): bool {
        global $wpdb;

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                WHERE CONSTRAINT_SCHEMA = %s
                AND TABLE_NAME = %s
                AND CONSTRAINT_NAME = %s
                AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
                DB_NAME,
                $table_name,
                $constraint_name
            )
        );

        return $count > 0;
    }

    /**
     * Check if an index exists.
     *
     * @param string $table_name Table name (without prefix).
     * @param string $index_name Index name.
     * @return bool True if index exists.
     */
    private static function index_exists( string $table_name, string $index_name ): bool {
        global $wpdb;

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = %s
                AND TABLE_NAME = %s
                AND INDEX_NAME = %s",
                DB_NAME,
                $table_name,
                $index_name
            )
        );

        return $count > 0;
    }
}
