<?php
/**
 * Cron_Status_Service class file.
 *
 * @package Polylang AI Automatic Translation
 * @subpackage Status
 */

namespace PLLAT\Status\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Service for detecting WordPress cron configuration.
 */
class Cron_Status_Service {
    /**
     * Check if WordPress is using internal or external cron.
     *
     * @return array{type: string, is_external: bool, constant_defined: bool}
     */
    public function get_cron_type(): array {
        $constant_defined = \defined( 'DISABLE_WP_CRON' );
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
        $is_disabled = $constant_defined && \constant( 'DISABLE_WP_CRON' );

        return array(
            'constant_defined' => $constant_defined,
            'is_external'      => (bool) $is_disabled,
            'type'             => $is_disabled ? 'external' : 'internal',
        );
    }

    /**
     * Check if cron is running properly.
     * Looks for recent Action Scheduler activity.
     *
     * @return array{is_working: bool, last_run: int|null}
     */
    public function check_cron_health(): array {
        global $wpdb;

        // Check for recent Action Scheduler runs.
        $last_run = $wpdb->get_var(
            "SELECT MAX(last_attempt_gmt)
			FROM {$wpdb->prefix}actionscheduler_actions
			WHERE status IN ('complete', 'failed')
			LIMIT 1",
        );

        $last_run_timestamp = null !== $last_run ? \strtotime( $last_run ) : null;
        $is_working         = null !== $last_run_timestamp && ( \time() - $last_run_timestamp ) < 300; // Active within 5 minutes.

        return array(
            'is_working' => $is_working,
            'last_run'   => $last_run_timestamp,
        );
    }

    /**
     * Get a human-readable cron status description.
     *
     * @return string
     */
    public function get_cron_description(): string {
        $cron_type = $this->get_cron_type();

        if ( $cron_type['is_external'] ) {
            return \__( 'External (Server Cron)', 'epicwp-ai-translation-for-polylang' );
        }

        return \__( 'Internal (WordPress Cron)', 'epicwp-ai-translation-for-polylang' );
    }
}
