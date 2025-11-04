<?php
/**
 * Single_Translation_REST_Controller class file.
 *
 * @package Polylang AI Automatic Translation
 * @subpackage Single_Translator
 */

declare(strict_types=1);

namespace PLLAT\Single_Translator\Controllers;

use PLLAT\Single_Translator\Services\Single_Translation_Service;
use PLLAT\Translator\Repositories\Task_Repository;
use XWP\DI\Decorators\REST_Handler;
use XWP\DI\Decorators\REST_Route;

/**
 * REST controller for single item translation operations.
 *
 * Provides endpoints for translating individual posts and terms
 * from their edit pages.
 */
#[REST_Handler( namespace: 'pllat/v1', basename: 'single-translator' )]
class Single_Translation_REST_Controller extends \XWP_REST_Controller {
    /**
     * Constructor.
     *
     * @param Single_Translation_Service $translation_service The single translation service.
     * @param Task_Repository            $task_repository     The task repository.
     */
    public function __construct(
        protected Single_Translation_Service $translation_service,
        protected Task_Repository $task_repository,
    ) {
    }

    /**
     * Permission check for endpoints (must be able to edit the content).
     *
     * @param \WP_REST_Request $request The request.
     * @return bool Whether the user has permission.
     */
    public function get_status_permissions_check( \WP_REST_Request $request ): bool {
        $type = $request->get_param( 'type' );
        $id   = (int) $request->get_param( 'id' );

        return $this->can_edit_content( $type, $id );
    }

    /**
     * Permission check for translate endpoint.
     *
     * @param \WP_REST_Request $request The request.
     * @return bool Whether the user has permission.
     */
    public function translate_permissions_check( \WP_REST_Request $request ): bool {
        $type = $request->get_param( 'type' );
        $id   = (int) $request->get_param( 'id' );

        return $this->can_edit_content( $type, $id );
    }

    /**
     * Permission check for exclusion endpoint.
     *
     * @param \WP_REST_Request $request The request.
     * @return bool Whether the user has permission.
     */
    public function set_exclusion_permissions_check( \WP_REST_Request $request ): bool {
        $type = $request->get_param( 'type' );
        $id   = (int) $request->get_param( 'id' );

        return $this->can_edit_content( $type, $id );
    }

    /**
     * Permission check for job tasks endpoint.
     *
     * @return bool Whether the user has permission.
     */
    public function get_job_tasks_permissions_check(): bool {
        return \current_user_can( 'edit_posts' ) || \current_user_can( 'edit_pages' );
    }

    /**
     * Permission check for cancel endpoint.
     *
     * @param \WP_REST_Request $request The request.
     * @return bool Whether the user has permission.
     */
    public function cancel_translation_permissions_check( \WP_REST_Request $request ): bool {
        $type = $request->get_param( 'type' );
        $id   = (int) $request->get_param( 'id' );

        return $this->can_edit_content( $type, $id );
    }

    /**
     * Get translation status for a content item.
     *
     * @param \WP_REST_Request $request The request.
     * @return \WP_REST_Response The response.
     */
    #[REST_Route( route: 'status/(?P<type>post|term)/(?P<id>\d+)', methods: 'GET' )]
    public function get_status( \WP_REST_Request $request ): \WP_REST_Response {
        $type = $request->get_param( 'type' );
        $id   = (int) $request->get_param( 'id' );

        try {
            $status = $this->translation_service->get_translation_status( $type, $id );

            // Add failed task details to help users understand what went wrong.
            if ( isset( $status['languages'] ) && \is_array( $status['languages'] ) ) {
                foreach ( $status['languages'] as &$lang_status ) {
                    if ( isset( $lang_status['status'] ) && 'failed' === $lang_status['status'] && isset( $lang_status['job_id'] ) ) {
                        $lang_status['failed_tasks'] = $this->get_failed_task_details( (int) $lang_status['job_id'] );
                    }
                }
            }

            return $this->success_response( $status );
        } catch ( \Exception $e ) {
            return $this->error_response( $e->getMessage(), 500 );
        }
    }

    /**
     * Start translation for a content item.
     *
     * @param \WP_REST_Request $request The request.
     * @return \WP_REST_Response The response.
     */
    #[REST_Route( route: 'translate/(?P<type>post|term)/(?P<id>\d+)', methods: 'POST' )]
    public function translate( \WP_REST_Request $request ): \WP_REST_Response {
        $type             = $request->get_param( 'type' );
        $id               = (int) $request->get_param( 'id' );
        $target_languages = $request->get_param( 'target_languages' );
        $force            = (bool) $request->get_param( 'force' );
        $instructions     = $request->get_param( 'instructions' );

        // Validate target languages.
        if ( ! \is_array( $target_languages ) || 0 === \count( $target_languages ) ) {
            return $this->error_response( 'Target languages are required.', 400 );
        }

        try {
            $run_id = $this->translation_service->create_translation_run(
                $type,
                $id,
                $target_languages,
                $force,
                $instructions,
            );

            return $this->success_response(
                array(
                    'message' => \__( 'Translation started successfully.', 'polylang-ai-autotranslate' ),
                    'run_id'  => $run_id,
                ),
            );
        } catch ( \Exception $e ) {
            return $this->error_response( $e->getMessage(), 400 );
        }
    }

    /**
     * Set exclusion status for a content item.
     *
     * @param \WP_REST_Request $request The request.
     * @return \WP_REST_Response The response.
     */
    #[REST_Route( route: 'exclusion/(?P<type>post|term)/(?P<id>\d+)', methods: 'POST' )]
    public function set_exclusion( \WP_REST_Request $request ): \WP_REST_Response {
        $type     = $request->get_param( 'type' );
        $id       = (int) $request->get_param( 'id' );
        $excluded = (bool) $request->get_param( 'excluded' );

        try {
            $this->translation_service->set_exclusion( $type, $id, $excluded );

            return $this->success_response(
                array(
                    'message' => $excluded
                        ? \__( 'Content excluded from AI translation.', 'polylang-ai-autotranslate' )
                        : \__( 'Content included in AI translation.', 'polylang-ai-autotranslate' ),
                ),
            );
        } catch ( \Exception $e ) {
            return $this->error_response( $e->getMessage(), 500 );
        }
    }

    /**
     * Get task details for a job (for error inspection).
     *
     * @param \WP_REST_Request $request The request.
     * @return \WP_REST_Response The response.
     */
    #[REST_Route( route: 'job/(?P<job_id>\d+)/tasks', methods: 'GET' )]
    public function get_job_tasks( \WP_REST_Request $request ): \WP_REST_Response {
        $job_id = (int) $request->get_param( 'job_id' );

        try {
            $tasks = $this->task_repository->find_by_job_id( $job_id );

            $task_data = \array_map(
                static fn( $task ) => array(
                    'attempts'    => $task->get_attempts(),
                    'id'          => $task->get_id(),
                    'issue'       => $task->get_issue(),
                    'reference'   => $task->get_reference(),
                    'status'      => $task->get_status()->value,
                    'translation' => $task->get_translation(),
                    'value'       => $task->get_value(),
                ),
                $tasks,
            );

            return $this->success_response( array( 'tasks' => $task_data ) );
        } catch ( \Exception $e ) {
            return $this->error_response( $e->getMessage(), 500 );
        }
    }

    /**
     * Cancel active translation for a content item.
     *
     * @param \WP_REST_Request $request The request.
     * @return \WP_REST_Response The response.
     */
    #[REST_Route( route: 'cancel/(?P<type>post|term)/(?P<id>\d+)', methods: 'POST' )]
    public function cancel_translation( \WP_REST_Request $request ): \WP_REST_Response {
        $type = $request->get_param( 'type' );
        $id   = (int) $request->get_param( 'id' );

        try {
            $run_id = $this->translation_service->cancel_active_translation( $type, $id );

            return $this->success_response(
                array(
                    'message' => \__( 'Translation cancelled successfully.', 'polylang-ai-autotranslate' ),
                    'run_id'  => $run_id,
                ),
            );
        } catch ( \Exception $e ) {
            return $this->error_response( $e->getMessage(), 400 );
        }
    }

    /**
     * Get failed task details for a job.
     *
     * @param int $job_id Job ID.
     * @return array Array of failed task details.
     */
    private function get_failed_task_details( int $job_id ): array {
        try {
            $tasks  = $this->task_repository->find_by_job_id( $job_id );
            $failed = array();

            foreach ( $tasks as $task ) {
                if ( $task->is_failed() ) {
                    $failed[] = array(
                        'attempts'  => $task->get_attempts(),
                        'issue'     => $task->get_issue(),
                        'reference' => $task->get_reference(),
                    );
                }
            }

            return $failed;
        } catch ( \Exception $e ) {
            return array();
        }
    }

    /**
     * Check if the user can edit the content.
     *
     * @param string $type Content type (post or term).
     * @param int    $id   Content ID.
     * @return bool Whether the user can edit.
     */
    private function can_edit_content( string $type, int $id ): bool {
        if ( 'post' === $type ) {
            $post = \get_post( $id );
            return $post && \current_user_can( 'edit_post', $id );
        }

        $term = \get_term( $id );
        return $term && ! \is_wp_error( $term ) && \current_user_can( 'edit_term', $id );
    }

    /**
     * Return an error response.
     *
     * @param string $message The error message.
     * @param int    $code    The HTTP status code.
     * @return \WP_REST_Response The error response.
     */
    private function error_response( string $message, int $code ): \WP_REST_Response {
        return new \WP_REST_Response(
            array(
                'message' => $message,
                'success' => false,
            ),
            $code,
        );
    }

    /**
     * Return a success response.
     *
     * @param array $data The response data.
     * @return \WP_REST_Response The success response.
     */
    private function success_response( array $data ): \WP_REST_Response {
        return new \WP_REST_Response( \array_merge( array( 'success' => true ), $data ), 200 );
    }
}
