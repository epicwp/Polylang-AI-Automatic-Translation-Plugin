<?php
declare(strict_types=1);

namespace PLLAT\Content\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use PLLAT\Content\Handlers\Content_Change_Handler;
use PLLAT\Content\Services\Interfaces\Content_Service as Content_Service_Interface;
use PLLAT\Translator\Models\Job;

use function apply_filters;
use function do_action;

/**
 * Content Service for processing translations and updating content fields.
 * Handles reference-based field updates with full hook integration.
 */
class Content_Service implements Content_Service_Interface {
    use Traits\Reference_Parsing_Trait;

    /**
     * Create a normalized reference key for a field and type.
     * Types: 'meta', 'custom_data', default core when empty.
     * Extensible via 'pllat_reference_prefixes' and 'pllat_content_reference_prefix'.
     *
     * @param string $field Field name.
     * @param string $type  Field type ('meta'|'custom_data'|'').
     * @return string Reference key.
     */
    public static function create_reference_key( string $field, string $type = '' ): string {
        $prefix_map = array(
            'custom_data' => '_custom_data|',
            'meta'        => '_meta|',
        );

        /**
         * Filter reference key prefixes.
         *
         * @param array $prefix_map Map of type => prefix.
         */
        $prefix_map = \apply_filters( 'pllat_reference_prefixes', $prefix_map );

        $prefix = $prefix_map[ $type ] ?? '';

        /**
         * Filter individual prefix for type.
         *
         * @param string $prefix The prefix for this type.
         * @param string $type   The field type.
         */
        $prefix = \apply_filters( 'pllat_content_reference_prefix', $prefix, $type );

        return \strtolower( $prefix . \str_replace( ' ', '_', $field ) );
    }

    /**
     * Constructor.
     *
     * @param Post_Content_Service $post_content_service Post service.
     * @param Term_Content_Service $term_content_service Term service.
     */
    public function __construct(
        private Post_Content_Service $post_content_service,
        private Term_Content_Service $term_content_service,
        private Content_Change_Handler $content_change_handler,
    ) {
    }

    /**
     * Process a completed job and persist translated fields.
     * Fires before/after hooks for integrations.
     *
     * @param Job $job The completed job.
     * @return void
     */
    public function process_job( Job $job ): void {
        $content_id   = $this->get_target_content_id( $job );
        $content_type = $job->get_type();

        /**
         * Action before processing job translations.
         *
         * @param Job    $job          The job being processed.
         * @param int    $content_id   Target content ID.
         * @param string $content_type Content type (post|term).
         */
        \do_action( 'pllat_before_process_job', $job, $content_id, $content_type );

        $this->suspend_hooks();

        try {
            $this->process_job_tasks( $job, $content_id, $content_type );
        } finally {
            $this->resume_hooks(); // Always restored, even on exception.
        }

        /**
         * Action after processing job translations.
         *
         * @param Job    $job          The processed job.
         * @param int    $content_id   Target content ID.
         * @param string $content_type Content type (post|term).
         */
        \do_action( 'pllat_after_process_job', $job, $content_id, $content_type );
    }

    /**
     * Get target content ID for a job (public wrapper for Job_Processor).
     *
     * @param Job $job Job instance.
     * @return int Target content ID.
     */
    public function get_target_content_id_for_job( Job $job ): int {
        return $this->get_target_content_id( $job );
    }

    /**
     * Suspend content change hooks to prevent recursive job creation.
     *
     * @return void
     */
    private function suspend_hooks(): void {
        \remove_action( 'post_updated', array( $this->content_change_handler, 'before_post_update' ), 99 );
        \remove_action(
            'wp_insert_post',
            array( $this->content_change_handler, 'create_tasks_for_post_meta_changes' ),
            98,
        );
    }

    /**
     * Resume content change hooks after translation processing.
     *
     * @return void
     */
    private function resume_hooks(): void {
        \add_action( 'post_updated', array( $this->content_change_handler, 'before_post_update' ), 99 );
        \add_action(
            'wp_insert_post',
            array( $this->content_change_handler, 'create_tasks_for_post_meta_changes' ),
            98,
        );
    }

    /**
     * Process all completed tasks for a job.
     *
     * @param Job    $job          The job being processed.
     * @param int    $content_id   Target content ID.
     * @param string $content_type Content type (post|term).
     * @return void
     */
    private function process_job_tasks( Job $job, int $content_id, string $content_type ): void {
        foreach ( $job->get_tasks() as $task ) {
            if ( ! $task->is_completed() || ! $task->has_translation() ) {
                continue;
            }

            $this->update_content_field(
                $content_id,
                $content_type,
                $task->get_reference(),
                $task->get_translation(),
            );
        }
    }

    /**
     * Update a content field based on reference key.
     *
     * @param int    $content_id   Content ID.
     * @param string $content_type Content type (post|term).
     * @param string $reference    Field reference key.
     * @param string $translation  Translated value.
     * @return void
     */
    private function update_content_field( int $content_id, string $content_type, string $reference, string $translation ): void {
        /**
         * Allow integrations to handle field updates.
         *
         * @param bool   $handled      Whether the update was handled.
         * @param int    $content_id   Content ID.
         * @param string $content_type Content type (post|term).
         * @param string $reference    Field reference key.
         * @param string $translation  Translated value.
         */
        $handled = \apply_filters(
            'pllat_handle_field_update',
            false,
            $content_id,
            $content_type,
            $reference,
            $translation,
        );

        if ( $handled ) {
            return;
        }

        // Core field handling delegates
        if ( 'post' === $content_type ) {
            $this->post_content_service->update_post_field( $content_id, $reference, $translation );
        } elseif ( 'term' === $content_type ) {
            $this->term_content_service->update_term_field( $content_id, $reference, $translation );
        }

        /**
         * Action after field update.
         *
         * @param int    $content_id   Content ID.
         * @param string $content_type Content type.
         * @param string $reference    Field reference.
         * @param string $translation  Translation value.
         */
        \do_action( 'pllat_field_updated', $content_id, $content_type, $reference, $translation );
    }

    /**
     * Resolve the target content ID for the given job/context.
     * Delegates to the post/term services; falls back to source ID.
     *
     * @param Job $job Job context.
     * @return int Target content ID.
     */
    private function get_target_content_id( Job $job ): int {
        $source_id    = $job->get_id_from();
        $target_lang  = $job->get_lang_to();
        $content_type = $job->get_type();

        if ( 'post' === $content_type ) {
            return $this->post_content_service->resolve_target_id( $source_id, $target_lang );
        }
        if ( 'term' === $content_type ) {
            return $this->term_content_service->resolve_target_id( $source_id, $target_lang );
        }
        return $source_id;
    }
}
