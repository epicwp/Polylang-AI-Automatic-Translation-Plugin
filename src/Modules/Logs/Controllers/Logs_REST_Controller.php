<?php
declare(strict_types=1);

namespace PLLAT\Logs\Controllers;

use PLLAT\Logs\Services\Log_Reader_Service;
use XWP\DI\Decorators\REST_Handler;
use XWP\DI\Decorators\REST_Route;
use XWP_REST_Controller;

/**
 * REST API controller for translation logs.
 * Provides endpoints for fetching and managing translation logs.
 */
#[REST_Handler( namespace: 'pllat/v1', basename: 'logs' )]
class Logs_REST_Controller extends \XWP_REST_Controller {
    /**
     * Constructor.
     *
     * @param Log_Reader_Service $log_service The log reader service.
     */
    public function __construct(
        private Log_Reader_Service $log_service,
    ) {
    }

    /**
     * Get translation logs with optional filtering.
     * Supports date-based filtering for efficient log retrieval.
     *
     * @param \WP_REST_Request<array<string, mixed>> $request The request object.
     * @return \WP_REST_Response The response.
     */
    #[REST_Route( route: '', methods: 'GET', guard: 'check_permission' )]
    public function get_logs( \WP_REST_Request $request ): \WP_REST_Response {
        $date   = $request->get_param( 'date' );
        $type   = $request->get_param( 'type' ) ?: 'all';
        $run_id = $request->get_param( 'run_id' ) ? (int) $request->get_param( 'run_id' ) : null;

        // If no date specified, use today
        if ( ! $date ) {
            $date = \gmdate( 'Y-m-d' );
        }

        // Get logs for specific date
        $logs = $this->log_service->get_logs_for_date( $date, $type, $run_id );

        return new \WP_REST_Response(
            array(
                'date'  => $date,
                'logs'  => $logs,
                'total' => \count( $logs ),
            ),
        );
    }

    /**
     * Get available log dates.
     *
     * @param \WP_REST_Request<array<string, mixed>> $request The request object.
     * @return \WP_REST_Response The response.
     */
    #[REST_Route( route: 'dates', methods: 'GET', guard: 'check_permission' )]
    public function get_log_dates( \WP_REST_Request $request ): \WP_REST_Response {
        $dates = $this->log_service->get_available_log_dates();

        return new \WP_REST_Response(
            array(
                'dates' => $dates,
                'total' => \count( $dates ),
            ),
        );
    }

    /**
     * Clear old log files.
     *
     * @param \WP_REST_Request<array<string, mixed>> $request The request object.
     * @return \WP_REST_Response The response.
     */
    #[REST_Route( route: 'clear', methods: 'POST', guard: 'check_request_authorization' )]
    public function clear_logs( \WP_REST_Request $request ): \WP_REST_Response {
        $days = (int) $request->get_param( 'days' ) ?: 30;

        try {
            $deleted = $this->log_service->clear_old_logs( $days );

            return new \WP_REST_Response(
                array(
                    'deleted' => $deleted,
                    'message' => \sprintf( 'Cleared %d old log file(s)', $deleted ),
                    'success' => true,
                ),
            );
        } catch ( \Exception $e ) {
            return new \WP_REST_Response(
                array(
                    'error'   => $e->getMessage(),
                    'success' => false,
                ),
                500,
            );
        }
    }

    /**
     * Check if the user has permission to view logs.
     *
     * @return bool True if the user has permission.
     */
    public function check_permission(): bool {
        return \current_user_can( 'manage_options' );
    }

    /**
     * Check if the request is authorized to perform write operations.
     *
     * @param \WP_REST_Request<array<string, mixed>> $request The request object.
     * @return bool True if authorized.
     */
    public function check_request_authorization( \WP_REST_Request $request ): bool {
        return \current_user_can( 'manage_options' ) && \wp_verify_nonce(
            $request->get_header( 'X-WP-Nonce' ),
            'wp_rest',
        );
    }
}
