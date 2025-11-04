<?php
/**
 * Uninstall script for Polylang Automatic AI Translation plugin
 *
 * This file is automatically executed by WordPress when the plugin is deleted.
 * It removes all database tables created by the plugin.
 *
 * @package PolylangAutomaticAITranslation
 */

// Exit if uninstall not called from WordPress
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Delete all plugin database tables for a single site
 *
 * @return void
 */
function pllat_delete_database_tables() {
    global $wpdb;

    // Get table names with proper prefix
    $tables = array(
        $wpdb->prefix . 'pllat_tasks',
        $wpdb->prefix . 'pllat_jobs',
        $wpdb->prefix . 'pllat_bulk_runs',
    );

    // Drop tables in correct order (respecting foreign key constraints)
    // Tasks must be dropped first (has FK to jobs), then jobs (has FK to runs), then runs
    foreach ( $tables as $table ) {
        $wpdb->query(
            "DROP TABLE IF EXISTS `{$table}`",
        ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    // Delete database version tracking options
    delete_option( 'pllat_db_version' );
    delete_option( 'pllat_db_installed_at' );
}

/**
 * Main uninstall routine
 */
if ( is_multisite() ) {
    // For multisite, clean up each site's tables
    global $wpdb;

    // Get all blog IDs
    $blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );

    foreach ( $blog_ids as $blog_id ) {
        switch_to_blog( $blog_id );
        pllat_delete_database_tables();
        restore_current_blog();
    }
} else {
    // Single site installation
    pllat_delete_database_tables();
}
