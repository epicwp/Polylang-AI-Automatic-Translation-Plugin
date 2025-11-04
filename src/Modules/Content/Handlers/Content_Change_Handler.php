<?php
namespace PLLAT\Content\Handlers;

use PLLAT\Common\Helpers;
use PLLAT\Common\Interfaces\Language_Manager;
use PLLAT\Translator\Models\Translatables\Translatable_Post;
use PLLAT\Translator\Models\Translatables\Translatable_Term;
use XWP\DI\Decorators\Action;
use XWP\DI\Decorators\Handler;

/**
 * Handles the detection of changes in posts and terms.
 *
 * Note: Cleanup on content deletion moved to Sync/Handlers/Cleanup_Handler.
 */
#[Handler( tag: 'init', priority: 11 )]
class Content_Change_Handler {
    /**
     * Constructor.
     *
     * @param Language_Manager $language_manager The language manager.
     * @return void
     */
    public function __construct(
        protected Language_Manager $language_manager,
    ) {
    }

    /**
     * Save meta state BEFORE post update happens.
     * This runs before any changes are made to the post or its meta.
     *
     * @param int   $post_ID Post ID.
     * @param array $data Array of unslashed post data.
     */
    #[Action( tag: 'pre_post_update', priority: 5 )]
    public function save_meta_before_update( int $post_ID, array $data ) {
        // Ensure we have a valid post object.
        $post = $this->ensure_valid_post_object( $post_ID );
        if ( ! $post ) {
            return;
        }

        // Check if we should skip this post.
        if ( $this->should_skip_post_translation( $post ) ) {
            return;
        }

        // Don't overwrite existing transient - it contains the state from before changes.
        $existing = $this->get_transient_post_meta( $post_ID );
        if ( false !== $existing && ! \count( $existing ) > 0 ) {
            return;
        }

        // Save current meta state before any updates.
        $this->transient_post_meta( $post_ID );
    }

    /**
     * Collects translation tasks during post update.
     *
     * @param int      $post_id Post ID.
     * @param \WP_Post $post_before Post object before the update.
     * @param \WP_Post $post_after Post object after the update.
     */
    #[Action( tag: 'post_updated', priority: 99 )]
    public function before_post_update( int $post_id, $post_before = null, $post_after = null ) {
        // Ensure we have the post objects.
        if ( ! $post_before || ! $post_after ) {
            return;
        }

        // Check if we should skip this post.
        if ( $this->should_skip_post_translation( $post_after ) ) {
            return;
        }

        // Only create automatic jobs for default language posts.
        if ( ! $this->is_default_language_post( $post_id ) ) {
            return;
        }

        $translatable_post = Translatable_Post::get_instance( $post_id );

        // Get the changes from before and after the update based on the allowed fields.
        $changes = Helpers::get_changed_fields(
            $post_before->to_array(),
            $post_after->to_array(),
            $translatable_post->get_available_fields(),
        );

        // If there are changes, store the translation tasks for each change.
        if ( \count( $changes ) <= 0 ) {
            return;
        }

        // Create translation tasks for the changes.
        $translatable_post->collect_translation_tasks_for( fields: $changes );
    }

    /**
     * Creates jobs and tasks for a post meta change
     *
     * @param int      $post_id Post ID.
     * @param \WP_Post $post Post object.
     * @param bool     $update Whether the post is being updated.
     */
    #[Action( tag: 'wp_insert_post', priority: 98 )]
    public function create_tasks_for_post_meta_changes( int $post_id, $post = null, $update = false ) {
        // Ensure we have a valid post object.
        $post = $this->ensure_valid_post_object( $post_id );
        if ( ! $post ) {
            return;
        }

        // Check if we should skip this post.
        if ( $this->should_skip_post_translation( $post ) ) {
            return;
        }

        // Only create automatic jobs for default language posts.
        if ( ! $this->is_default_language_post( $post_id ) ) {
            return;
        }

        // Get the translatable post instance.
        $translatable_post = Translatable_Post::get_instance( $post_id );

        // Get the fields that are allowed to be translated.
        $available_fields = $translatable_post->get_available_meta_fields();

        // Get the post meta data before updating from the transient earlier stored in another hook.
        $pre_post_meta = $this->get_transient_post_meta( $post_id );

        // If there is no post meta data before updating, return.
        if ( ! $pre_post_meta ) {
            return;
        }

        // Get the post meta data after updating and compare it with the previous post meta data.
        $post_meta = Helpers::get_flatten_post_meta( $post_id );
        $changes   = Helpers::get_changed_fields( $pre_post_meta, $post_meta, $available_fields );

        // If there are no changes, return.
        if ( 0 === \count( $changes ) ) {
            return;
        }

        // Create translation tasks for the changes.
        $translatable_post->collect_translation_tasks_for( meta_fields: $changes );

        // Delete the transient post meta data.
        $this->delete_transient_post_meta( $post_id );
    }

    /**
     * Save term meta state BEFORE term update happens.
     * This runs before any changes are made to the term or its meta.
     *
     * @param int    $term_id Term ID.
     * @param int    $tt_id   Term taxonomy ID.
     * @param string $taxonomy Taxonomy slug.
     */
    #[Action( tag: 'edit_term', priority: 5 )]
    public function save_term_meta_before_update( int $term_id, int $tt_id, string $taxonomy ) {
        // Check if we should skip this term.
        if ( $this->should_skip_term_translation( $term_id ) ) {
            return;
        }

        // Don't overwrite existing transient - it contains the state from before changes.
        $existing = $this->get_transient_term_meta( $term_id );
        if ( false !== $existing && ! \count( $existing ) > 0 ) {
            return;
        }

        // Save current meta state before any updates.
        $this->transient_term_meta( $term_id );
    }

    /**
     * Collects translation tasks during term update.
     *
     * @param int    $term_id Term ID.
     * @param int    $tt_id   Term taxonomy ID.
     * @param string $taxonomy Taxonomy slug.
     * @param array  $args    Arguments passed to wp_update_term().
     */
    #[Action( tag: 'edited_term', priority: 99 )]
    public function after_term_update( int $term_id, int $tt_id, string $taxonomy, array $args = array() ) {
        // Check if we should skip this term.
        if ( $this->should_skip_term_translation( $term_id ) ) {
            return;
        }

        // Only create automatic jobs for default language terms.
        if ( ! $this->is_default_language_term( $term_id ) ) {
            return;
        }

        // Get the translatable term instance.
        $translatable_term = Translatable_Term::get_instance( $term_id );

        // Get the term before and after update.
        $term = \get_term( $term_id, $taxonomy );
        if ( ! $term || \is_wp_error( $term ) ) {
            return;
        }

        // Check for changes in term fields.
        $available_fields = $translatable_term->get_available_fields();
        $changes          = array();

        // Check each available field for changes.
        foreach ( $available_fields as $field ) {
            if ( ! isset( $args[ $field ] ) ) {
                continue;
            }

            // Field was updated.
            $changes[] = $field;
        }

        // If there are changes, store the translation tasks for each change.
        if ( \count( $changes ) > 0 ) {
            $translatable_term->collect_translation_tasks_for( fields: $changes );
        }

        // Process meta changes.
        $this->create_tasks_for_term_meta_changes( $term_id, $taxonomy );
    }

    /**
     * Ensures a valid post object is returned.
     *
     * @param int $post_id
     * @return \WP_Post
     */
    private function ensure_valid_post_object( int $post_id ): ?\WP_Post {
        $post = \get_post( $post_id );
        if ( ! $post ) {
            return null;
        }
        return $post;
    }

    /**
     * Creates jobs and tasks for term meta changes.
     *
     * @param int    $term_id  Term ID.
     * @param string $taxonomy Taxonomy slug.
     */
    private function create_tasks_for_term_meta_changes( int $term_id, string $taxonomy ): void {
        // Get the translatable term instance.
        $translatable_term = Translatable_Term::get_instance( $term_id );

        // Get the fields that are allowed to be translated.
        $available_fields = $translatable_term->get_available_meta_fields();

        // Get the term meta data before updating from the transient.
        $pre_term_meta = $this->get_transient_term_meta( $term_id );

        // If there is no term meta data before updating, return.
        if ( ! $pre_term_meta ) {
            return;
        }

        // Get the term meta data after updating and compare it with the previous term meta data.
        $term_meta = Helpers::get_flatten_term_meta( $term_id );
        $changes   = Helpers::get_changed_fields( $pre_term_meta, $term_meta, $available_fields );

        // If there are no changes, return.
        if ( 0 === \count( $changes ) ) {
            return;
        }

        // Create translation tasks for the changes.
        $translatable_term->collect_translation_tasks_for( meta_fields: $changes );

        // Delete the transient term meta data.
        $this->delete_transient_term_meta( $term_id );
    }

    /**
     * Saves the post meta data before updating via transient
     *
     * @param int $post_id
     * @return void
     */
    private function transient_post_meta( $post_id ) {
        $post_meta = Helpers::get_flatten_post_meta( $post_id );
        \set_transient( 'pllat_pre_post_meta_' . $post_id, $post_meta, 60 * 60 * 24 );
    }

    /**
     * Gets the post meta data before updating via transient
     *
     * @param int $post_id
     * @return array
     */
    private function get_transient_post_meta( $post_id ) {
        return \get_transient( 'pllat_pre_post_meta_' . $post_id );
    }

    /**
     * Deletes the post meta data before updating via transient
     *
     * @param int $post_id
     * @return void
     */
    private function delete_transient_post_meta( $post_id ) {
        \delete_transient( 'pllat_pre_post_meta_' . $post_id );
    }

    /**
     * Saves the term meta data before updating via transient
     *
     * @param int $term_id
     * @return void
     */
    private function transient_term_meta( int $term_id ): void {
        $term_meta = Helpers::get_flatten_term_meta( $term_id );
        \set_transient( 'pllat_pre_term_meta_' . $term_id, $term_meta, 60 * 60 * 24 );
    }

    /**
     * Gets the term meta data before updating via transient
     *
     * @param int $term_id
     * @return array|false
     */
    private function get_transient_term_meta( int $term_id ) {
        return \get_transient( 'pllat_pre_term_meta_' . $term_id );
    }

    /**
     * Deletes the term meta data before updating via transient
     *
     * @param int $term_id
     * @return void
     */
    private function delete_transient_term_meta( int $term_id ): void {
        \delete_transient( 'pllat_pre_term_meta_' . $term_id );
    }

    /**
     * Determines if a post should be skipped for translation.
     *
     * @param \WP_Post $post The post object to check.
     * @return bool True if the post should be skipped, false otherwise.
     */
    private function should_skip_post_translation( $post ): bool {
        // Skip WordPress post revisions.
        if ( 'revision' === $post->post_type ) {
            return true;
        }

        // Skip auto-drafts and inherit status posts.
        if ( \in_array( $post->post_status, array( 'auto-draft', 'inherit' ), true ) ) {
            return true;
        }

        // Skip posts that don't have a language assigned.
        return ! $this->language_manager->get_post_language( $post->ID );
    }

    /**
     * Determines if a term should be skipped for translation.
     *
     * @param int $term_id The term ID to check.
     * @return bool True if the term should be skipped, false otherwise.
     */
    private function should_skip_term_translation( int $term_id ): bool {
        // Skip terms that don't have a language assigned.
        return ! $this->language_manager->get_term_language( $term_id );
    }

    /**
     * Checks if a term is in the default language.
     *
     * @param int $term_id The term ID to check.
     * @return bool True if term is in default language, false otherwise.
     */
    private function is_default_language_term( int $term_id ): bool {
        $term_language    = $this->language_manager->get_term_language( $term_id );
        $default_language = $this->language_manager->get_default_language();

        return $term_language === $default_language;
    }

    /**
     * Checks if a post is in the default language.
     *
     * @param int $post_id The post ID to check.
     * @return bool True if post is in default language, false otherwise.
     */
    private function is_default_language_post( int $post_id ): bool {
        $post_language    = $this->language_manager->get_post_language( $post_id );
        $default_language = $this->language_manager->get_default_language();

        return $post_language === $default_language;
    }
}
