<?php
/**
 * Discovery_REST_Controller class file.
 *
 * @package Polylang AI Automatic Translation
 * @subpackage Sync
 */

declare(strict_types=1);

namespace PLLAT\Sync\Controllers;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
use PLLAT\Sync\Services\Sync_Service;
use XWP\DI\Decorators\REST_Handler;
use XWP\DI\Decorators\REST_Route;

/**
 * REST API controller for content analysis/discovery.
 * Provides endpoints for checking and triggering content analysis.
 */
#[REST_Handler( namespace: 'pllat/v1', basename: 'discovery' )]
class Discovery_REST_Controller extends \XWP_REST_Controller {
    /**
     * Constructor.
     *
     * @param Sync_Service $sync_service The sync service.
     */
    public function __construct(
        private Sync_Service $sync_service,
    ) {
    }

    /**
     * Get current discovery status.
     * Fast check using LIMIT 1 queries (~5-50ms).
     *
     * @return \WP_REST_Response Discovery status information.
     */
    #[REST_Route( route: 'status', methods: 'GET', guard: 'check_permission' )]
    public function get_status(): \WP_REST_Response {
        $status = $this->sync_service->check_discovery_needed();
        return new \WP_REST_Response( $status, 200 );
    }

    /**
     * Check if current user has permission to access discovery endpoints.
     *
     * @return bool True if user has permission.
     */
    public function check_permission(): bool {
        return \current_user_can( 'manage_options' );
    }
}
