<?php
/**
 * Single_Translation_Service class file.
 *
 * @package Polylang AI Automatic Translation
 * @subpackage Single_Translator
 */

declare(strict_types=1);

namespace PLLAT\Single_Translator\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use PLLAT\Common\Interfaces\Language_Manager;
use PLLAT\Sync\Services\Sync_Service;
use PLLAT\Translator\Enums\JobStatus;
use PLLAT\Translator\Enums\TranslatableMetaKey;
use PLLAT\Translator\Models\Job;
use PLLAT\Translator\Models\Translatables\Translatable_Post;
use PLLAT\Translator\Models\Translatables\Translatable_Term;
use PLLAT\Translator\Models\Translation_Config;
use PLLAT\Translator\Repositories\Job_Repository;
use PLLAT\Translator\Repositories\Run_Repository;
use PLLAT\Translator\Services\Translation_Run_Service;

/**
 * Service for single item translation from edit pages.
 */
class Single_Translation_Service {
    /**
     * Constructor.
     *
     * @param Language_Manager               $language_manager          The language manager.
     * @param Job_Repository                 $job_repository            The job repository.
     * @param Run_Repository                 $run_repository            The run repository.
     * @param Translation_Run_Service        $translation_run_service   The translation run service.
     * @param Sync_Service                   $sync_service              The sync service.
     * @param Async_Job_Dispatcher_Service   $async_job_dispatcher      The async job dispatcher service.
     */
    public function __construct(
        private Language_Manager $language_manager,
        private Job_Repository $job_repository,
        private Run_Repository $run_repository,
        private Translation_Run_Service $translation_run_service,
        private Sync_Service $sync_service,
        private Async_Job_Dispatcher_Service $async_job_dispatcher,
    ) {
    }

    /**
     * Get translation status for a specific content item.
     *
     * @param string $type Content type (post or term).
     * @param int    $id Content ID.
     * @return array Status data including system status and per-language status.
     */
    public function get_translation_status( string $type, int $id ): array {
        $lang_from           = $this->get_content_language( $type, $id );
        $available_languages = $this->get_target_languages( $lang_from );
        $all_jobs            = $this->job_repository->find_all_by_content( $type, $id );
        $language_names      = $this->build_language_names_map();

        // Build per-language status.
        $languages = array();
        foreach ( $available_languages as $lang_to ) {
            $languages[] = $this->build_language_status( $type, $id, $lang_from, $lang_to, $language_names );
        }

        $timing_flags = $this->analyze_job_timing( $all_jobs );

        return array(
            'has_active'         => $timing_flags['has_active'],
            'has_recent_error'   => $timing_flags['has_recent_error'],
            'has_recent_success' => $timing_flags['has_recent_success'],
            'is_discovered'      => 0 !== \count( $all_jobs ),
            'is_excluded'        => $this->is_excluded( $type, $id ),
            'languages'          => $languages,
        );
    }

    /**
     * Create a translation run for a specific content item.
     *
     * @param string      $type             Content type (post or term).
     * @param int         $id               Content ID.
     * @param array       $target_languages Target language codes.
     * @param bool        $force            Force re-translation.
     * @param string|null $instructions     Custom AI instructions.
     * @return int Run ID.
     * @throws \Exception If validation fails or system not ready.
     */
    public function create_translation_run(
        string $type,
        int $id,
        array $target_languages,
        bool $force = false,
        ?string $instructions = null,
    ): int {
        if ( $this->is_excluded( $type, $id ) ) {
            throw new \Exception(
                \__( // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                    'This content is excluded from AI translation.',
                    'epicwp-ai-translation-for-polylang',
                ),
            );
        }

        // Discovery - force mode creates jobs for ALL languages, normal mode only missing.
        if ( $force ) {
            $this->discover_item_for_languages( $type, $id, $target_languages, true );
        } else {
            $this->discover_item( $type, $id );
            $this->validate_languages_need_translation( $type, $id, $target_languages );
        }

        // Create mini-run with specific content.
        $lang_from = $this->get_content_language( $type, $id );

        $config = new Translation_Config(
            lang_from: $lang_from,
            langs_to: $target_languages,
            post_types: array(),
            taxonomies: array(),
            string_groups: array(),
            terms: array(),
            specific_posts: 'post' === $type ? array( $id ) : array(),
            specific_terms: 'term' === $type ? array( $id ) : array(),
            instructions: $instructions ?? '',
            forced: $force,
        );

        $run = $this->run_repository->create( $config );

        // Free version: Always use local async processing (no external processor).
        $should_use_external = false;

        // Connect jobs and conditionally trigger external processor.
        $this->translation_run_service->create_translation_run( $run, $should_use_external );

        // Free version: Always enqueue async actions for immediate processing.
        $this->async_job_dispatcher->enqueue_jobs_for_run( $run );

        return $run->get_id();
    }

    /**
     * Set exclusion status for a content item.
     * Cancels pending jobs when excluding, resets cancelled jobs when un-excluding.
     *
     * @param string $type     Content type (post or term).
     * @param int    $id       Content ID.
     * @param bool   $excluded Whether to exclude from translation.
     * @return void
     */
    public function set_exclusion( string $type, int $id, bool $excluded ): void {
        $this->update_exclusion_meta( $type, $id, $excluded );

        $lang_from           = $this->get_content_language( $type, $id );
        $available_languages = $this->get_target_languages( $lang_from );

        foreach ( $available_languages as $lang_to ) {
            $latest_job = $this->job_repository->find_latest_by_content_and_language(
                $type,
                $id,
                $lang_from,
                $lang_to,
            );

            if ( ! $latest_job ) {
                continue;
            }

            if ( $excluded && JobStatus::Pending === $latest_job->get_status() ) {
                // When excluding: cancel pending jobs (Layer 5 of 6-layer defense).
                $latest_job->cancel();
                $this->job_repository->save( $latest_job );
            } elseif ( ! $excluded && JobStatus::Cancelled === $latest_job->get_status() ) {
                // When un-excluding: reset cancelled jobs to pending so they can be translated.
                $latest_job->set_status( JobStatus::Pending );
                $this->job_repository->save( $latest_job );
            }
        }
    }

    /**
     * Cancel active translation for a content item.
     * Finds the active run and cancels it along with all its jobs.
     *
     * @param string $type Content type (post or term).
     * @param int    $id   Content ID.
     * @return int The run ID that was cancelled.
     * @throws \Exception If no active translation found.
     */
    public function cancel_active_translation( string $type, int $id ): int {
        // Find all jobs for this content item.
        $all_jobs = $this->job_repository->find_all_by_content( $type, $id );

        // Find an active job (in_progress or pending with run_id).
        $active_job = null;
        foreach ( $all_jobs as $job ) {
            if ( JobStatus::InProgress === $job->get_status() || ( JobStatus::Pending === $job->get_status() && null !== $job->get_run_id() ) ) {
                $active_job = $job;
                break;
            }
        }

        if ( ! $active_job || null === $active_job->get_run_id() ) {
            throw new \Exception(
                \__( // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                    'No active translation found for this content.',
                    'epicwp-ai-translation-for-polylang',
                ),
            );
        }

        $run_id = $active_job->get_run_id();
        $run    = $this->run_repository->find( $run_id );

        if ( ! $run ) {
            throw new \Exception(
                \__( // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                    'Translation run not found.',
                    'epicwp-ai-translation-for-polylang',
                ),
            );
        }

        // Cancel all jobs in this run (pending and in_progress).
        $run_jobs = $this->job_repository->find_by_run_and_statuses(
            $run_id,
            array( JobStatus::Pending, JobStatus::InProgress ),
        );

        foreach ( $run_jobs as $job ) {
            $job->cancel();
            $this->job_repository->save( $job );
        }

        // Cancel the run itself.
        $this->translation_run_service->cancel_run( $run );

        // Free version: Always cancel pending async actions.
        $this->async_job_dispatcher->cancel_actions_for_run( $run_id );

        return $run_id;
    }

    /**
     * Check if content is excluded from translation.
     *
     * @param string $type Content type (post or term).
     * @param int    $id   Content ID.
     * @return bool True if excluded.
     */
    private function is_excluded( string $type, int $id ): bool {
        if ( 'post' === $type ) {
            return (bool) \get_post_meta( $id, TranslatableMetaKey::Exclude->value, true );
        }

        return (bool) \get_term_meta( $id, TranslatableMetaKey::Exclude->value, true );
    }

    /**
     * Discover a content item on-demand (create jobs).
     * Used when item hasn't been discovered yet by background process.
     *
     * @param string $type Content type (post or term).
     * @param int    $id   Content ID.
     * @return void
     */
    private function discover_item( string $type, int $id ): void {
        $translatable = 'post' === $type ? Translatable_Post::get_instance(
            $id,
        ) : Translatable_Term::get_instance( $id );

        $translatable->collect_missing_translation_tasks();
    }

    /**
     * Discover and create tasks for specific target languages.
     * Used for force mode to re-translate existing translations.
     *
     * @param string $type             Content type (post or term).
     * @param int    $id               Content ID.
     * @param array  $target_languages Target language codes.
     * @param bool   $force            Whether to create tasks even if translations exist.
     * @return void
     */
    private function discover_item_for_languages( string $type, int $id, array $target_languages, bool $force ): void {
        $translatable = 'post' === $type
            ? Translatable_Post::get_instance( $id )
            : Translatable_Term::get_instance( $id );

        $translatable->collect_tasks_for_languages( $target_languages, $force );
    }

    /**
     * Get the timestamp for when a translation was created/modified.
     * Priority: Job completed_at > WordPress modified time > current time.
     *
     * @param string   $type           Content type (post or term).
     * @param int      $translation_id The translation ID.
     * @param Job|null $latest_job     The latest job (may be null for manual translations).
     * @return int Timestamp or 0 if not available.
     */
    private function get_translation_timestamp( string $type, int $translation_id, ?Job $latest_job ): int {
        // Priority 1: Job completed timestamp (most accurate for plugin translations).
        if ( $latest_job && $latest_job->get_completed_at() > 0 ) {
            return $latest_job->get_completed_at();
        }

        // Priority 2: WordPress modified time (fallback for manual translations).
        if ( 'post' === $type ) {
            $post = \get_post( $translation_id );
            return $post ? \strtotime( $post->post_modified ) : 0;
        }

        // Terms don't have modified timestamp in WP core - use current time as fallback.
        $term = \get_term( $translation_id );
        return $term && ! \is_wp_error( $term ) ? \time() : 0;
    }

    /**
     * Validate that languages need translation (have processable jobs).
     * Throws exception if nothing to translate or content is excluded.
     *
     * @param string $type             Content type (post or term).
     * @param int    $id               Content ID.
     * @param array  $target_languages Target language codes.
     * @return void
     * @throws \Exception If no languages need translation or content excluded.
     */
    private function validate_languages_need_translation( string $type, int $id, array $target_languages ): void {
        // Check if content is excluded - takes precedence over job status.
        if ( $this->is_excluded( $type, $id ) ) {
            throw new \Exception(
                \__( // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                    'This content is excluded from AI translation. Please un-exclude it first.',
                    'epicwp-ai-translation-for-polylang',
                ),
            );
        }

        $lang_from         = $this->get_content_language( $type, $id );
        $needs_translation = false;

        foreach ( $target_languages as $lang_to ) {
            $latest_job = $this->job_repository->find_latest_by_content_and_language(
                $type,
                $id,
                $lang_from,
                $lang_to,
            );

            // Job is processable if:
            // - Doesn't exist (will be discovered on-demand)
            // - Is pending or failed (standard processable states)
            // - Is cancelled BUT content not excluded (stale state from previous exclusion).
            if ( null === $latest_job || $latest_job->get_status()->isProcessable() || JobStatus::Cancelled === $latest_job->get_status() ) {
                $needs_translation = true;
                break;
            }
        }

        if ( ! $needs_translation ) {
            throw new \Exception(
                \__( // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                    'All selected languages are already translated. Enable force mode to re-translate.',
                    'epicwp-ai-translation-for-polylang',
                ),
            );
        }
    }

    /**
     * Get the source language for a content item.
     *
     * @param string $type Content type (post or term).
     * @param int    $id   Content ID.
     * @return string Language code.
     */
    private function get_content_language( string $type, int $id ): string {
        return 'post' === $type
            ? $this->language_manager->get_post_language( $id )
            : $this->language_manager->get_term_language( $id );
    }

    /**
     * Get available target languages (excludes source language).
     *
     * @param string $lang_from Source language code.
     * @return array Array of language codes.
     */
    private function get_target_languages( string $lang_from ): array {
        $available_languages = $this->language_manager->get_available_languages( false );
        return \array_diff( $available_languages, array( $lang_from ) );
    }

    /**
     * Get the translation ID for a content item in a target language.
     *
     * @param string $type    Content type (post or term).
     * @param int    $id      Source content ID.
     * @param string $lang_to Target language code.
     * @return int Translation ID or 0 if not found.
     */
    private function get_translation_id( string $type, int $id, string $lang_to ): int {
        if ( 'post' === $type ) {
            return $this->language_manager->get_post_translation( $id, $lang_to );
        }

        $translations = $this->language_manager->get_term_translations( $id );
        return $translations[ $lang_to ] ?? 0;
    }

    /**
     * Update exclusion meta for a content item.
     *
     * @param string $type     Content type (post or term).
     * @param int    $id       Content ID.
     * @param bool   $excluded Whether to exclude from translation.
     * @return void
     */
    private function update_exclusion_meta( string $type, int $id, bool $excluded ): void {
        if ( 'post' === $type ) {
            \update_post_meta( $id, TranslatableMetaKey::Exclude->value, $excluded );
        } else {
            \update_term_meta( $id, TranslatableMetaKey::Exclude->value, $excluded );
        }
    }

    /**
     * Build a map of language codes to display names.
     *
     * @return array Map of language slug => display name.
     */
    private function build_language_names_map(): array {
        $languages_data = $this->language_manager->get_languages_data();
        $language_names = array();

        foreach ( $languages_data as $lang_data ) {
            $slug   = \is_object( $lang_data ) ? $lang_data->slug : $lang_data['slug'];
            $name   = \is_object( $lang_data ) ? $lang_data->name : $lang_data['name'];
            $locale = \is_object( $lang_data ) ? ( $lang_data->locale ?? '' ) : ( $lang_data['locale'] ?? '' );

            // Format display name with locale: "Español (es_ES)".
            $display_name = '' !== $locale ? "{$name} ({$locale})" : $name;

            $language_names[ $slug ] = $display_name;
        }

        return $language_names;
    }

    /**
     * Add progress information to a status array for an in-progress job.
     *
     * @param array $status Status array to modify.
     * @param Job   $job    The job to analyze.
     * @return void
     */
    private function add_job_progress( array &$status, Job $job ): void {
        $tasks           = $job->get_tasks();
        $total_tasks     = \count( $tasks );
        $completed_tasks = 0;
        $failed_tasks    = 0;

        foreach ( $tasks as $task ) {
            if ( $task->is_completed() ) {
                ++$completed_tasks;
            } elseif ( $task->is_failed() ) {
                ++$failed_tasks;
            }
        }

        $status['progress'] = array(
            'completed' => $completed_tasks,
            'failed'    => $failed_tasks,
            'total'     => $total_tasks,
        );
    }

    /**
     * Add error information to a status array for a failed job.
     *
     * @param array $status Status array to modify.
     * @param Job   $job    The job to analyze.
     * @return void
     */
    private function add_job_errors( array &$status, Job $job ): void {
        $tasks       = $job->get_tasks();
        $error_count = 0;
        $first_error = null;

        foreach ( $tasks as $task ) {
            if ( ! $task->is_failed() && ! $task->is_exhausted() ) {
                continue;
            }

            ++$error_count;
            if ( null !== $first_error || ! $task->get_issue() ) {
                continue;
            }

            $first_error = $task->get_issue();
        }

        $status['error_count'] = $error_count;
        $status['first_error'] = $first_error;
    }

    /**
     * Analyze job timing to determine UI flags.
     *
     * @param array $jobs All jobs for the content item.
     * @return array Timing flags (has_active, has_recent_error, has_recent_success).
     */
    private function analyze_job_timing( array $jobs ): array {
        $has_recent_error   = false;
        $has_recent_success = false;
        $has_active         = false;
        $one_hour_ago       = \time() - 3600;

        foreach ( $jobs as $job ) {
            if ( JobStatus::InProgress === $job->get_status() ) {
                $has_active = true;
            }

            if ( JobStatus::Failed === $job->get_status() && $job->get_created_at() > $one_hour_ago ) {
                $has_recent_error = true;
            }

            if ( JobStatus::Completed !== $job->get_status() || $job->get_created_at() <= $one_hour_ago ) {
                continue;
            }

            $has_recent_success = true;
        }

        return array(
            'has_active'         => $has_active,
            'has_recent_error'   => $has_recent_error,
            'has_recent_success' => $has_recent_success,
        );
    }

    /**
     * Build status information for a single target language.
     *
     * @param string $type           Content type (post or term).
     * @param int    $id             Content ID.
     * @param string $lang_from      Source language code.
     * @param string $lang_to        Target language code.
     * @param array  $language_names Map of language codes to display names.
     * @return array Status information for the language.
     */
    private function build_language_status( string $type, int $id, string $lang_from, string $lang_to, array $language_names ): array {
        $latest_job = $this->job_repository->find_latest_by_content_and_language(
            $type,
            $id,
            $lang_from,
            $lang_to,
        );

        // Hybrid architecture: Check translation existence via Polylang.
        $translation_id     = $this->get_translation_id( $type, $id, $lang_to );
        $translation_exists = $translation_id > 0;

        $status = array(
            'created_at'     => $latest_job ? $latest_job->get_created_at() : null,
            'job_id'         => $latest_job ? $latest_job->get_id() : null,
            'language'       => $lang_to,
            'language_name'  => $language_names[ $lang_to ] ?? $lang_to,
            'run_id'         => $latest_job ? $latest_job->get_run_id() : null,
            'translation_id' => $translation_id,
        );

        if ( $latest_job && JobStatus::InProgress === $latest_job->get_status() ) {
            // Priority 1: Job actively processing.
            $status['status'] = 'in_progress';
        } elseif ( $latest_job && JobStatus::Pending === $latest_job->get_status() && null !== $latest_job->get_run_id() ) {
            // Priority 2: Job queued in active run.
            $status['status'] = 'queued';
        } elseif ( $latest_job && JobStatus::Completed === $latest_job->get_status() && $translation_exists ) {
            // Priority 3: Completed job with translation - show as translated with timestamp.
            $status['status']        = 'translated';
            $status['translated_at'] = $this->get_translation_timestamp( $type, $translation_id, $latest_job );
        } elseif ( $latest_job ) {
            // Priority 4: Any other job status (pending without run, failed, cancelled, etc.).
            // This ensures pending jobs (from content changes) show yellow instead of green.
            $status['status'] = $latest_job->get_status()->value;
        } elseif ( $translation_exists ) {
            // Priority 5: Translation exists, but no job (manual translation or old data).
            $status['status']        = 'translated';
            $status['translated_at'] = $this->get_translation_timestamp( $type, $translation_id, null );
        } else {
            // Priority 6: No job, no translation.
            $status['status'] = null;
        }

        // Add progress data for in-progress jobs.
        if ( $latest_job && JobStatus::InProgress === $latest_job->get_status() ) {
            $this->add_job_progress( $status, $latest_job );
        }

        // Add error data for failed jobs.
        if ( $latest_job && JobStatus::Failed === $latest_job->get_status() ) {
            $this->add_job_errors( $status, $latest_job );
        }

        return $status;
    }
}
