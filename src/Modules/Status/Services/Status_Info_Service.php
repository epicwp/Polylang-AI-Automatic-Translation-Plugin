<?php
/**
 * Status_Info_Service class file.
 *
 * @package Polylang AI Automatic Translation
 * @subpackage Status
 */

namespace PLLAT\Status\Services;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
use PLLAT\Translator\Models\Job;
use PLLAT\Translator\Models\Task;

/**
 * Service for providing plugin status information.
 */
class Status_Info_Service {
    /**
     * Cron status service.
     *
     * @var Cron_Status_Service
     */
    private Cron_Status_Service $cron_status_service;

    /**
     * Constructor.
     *
     * @param Cron_Status_Service $cron_status_service The cron status service.
     */
    public function __construct( Cron_Status_Service $cron_status_service ) {
        $this->cron_status_service = $cron_status_service;
    }

    /**
     * Get debug information for WordPress Site Health.
     *
     * @return array<string,array<string,mixed>>
     */
    public function get_debug_info(): array {
        global $wpdb;

        $jobs_table  = $wpdb->prefix . Job::TABLE_NAME;
        $tasks_table = $wpdb->prefix . Task::TABLE_NAME;

        $jobs_count  = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$jobs_table}",
        ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $tasks_count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$tasks_table}",
        ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        $cron_type   = $this->cron_status_service->get_cron_type();
        $cron_health = $this->cron_status_service->check_cron_health();

        return array(
            'pllat-cron'        => array(
                'label' => \__( 'WP Cron Type', 'epicwp-ai-translation-for-polylang' ),
                'value' => $this->cron_status_service->get_cron_description(),
            ),
            'pllat-cron-health' => array(
                'label'   => \__( 'Cron Status', 'epicwp-ai-translation-for-polylang' ),
                'private' => false,
                'value'   => $cron_health['is_working']
                    ? \__( 'Active', 'epicwp-ai-translation-for-polylang' )
                    : \__( 'Inactive or Slow', 'epicwp-ai-translation-for-polylang' ),
            ),
            'pllat-jobs'        => array(
                'label' => \__( 'Total Jobs', 'epicwp-ai-translation-for-polylang' ),
                'value' => \number_format_i18n( $jobs_count ),
            ),
            'pllat-tasks'       => array(
                'label' => \__( 'Total Tasks', 'epicwp-ai-translation-for-polylang' ),
                'value' => \number_format_i18n( $tasks_count ),
            ),
        );
    }

    /**
     * Get jobs count.
     *
     * @return int
     */
    public function get_jobs_count(): int {
        global $wpdb;
        $jobs_table = $wpdb->prefix . Job::TABLE_NAME;

        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$jobs_table}",
        ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    /**
     * Get tasks count.
     *
     * @return int
     */
    public function get_tasks_count(): int {
        global $wpdb;
        $tasks_table = $wpdb->prefix . Task::TABLE_NAME;

        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$tasks_table}",
        ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }
}
