<?php
namespace PLLAT\Translator\Models\Translatables;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
use PLLAT\Common\Interfaces\Language_Manager;
use PLLAT\Content\Services\Content_Service;
use PLLAT\Translator\Enums\JobStatus;
use PLLAT\Translator\Enums\TranslatableMetaKey as MetaKey;
use PLLAT\Translator\Models\Interfaces\Translatable;
use PLLAT\Translator\Repositories\Job_Repository;
use PLLAT\Translator\Repositories\Task_Repository;

/**
 * Abstract class for translatable entities (terms and posts).
 */
abstract class Base_Translatable implements Translatable {
    /**
     * Get the instance of the translatable.
     *
     * @param int $id
     * @return static
     */
    public static function get_instance( int $id ): static {
        return new static(
            $id,
            \xwp_app( 'pllat' )->get( Language_Manager::class ),
            \xwp_app( 'pllat' )->get( Job_Repository::class ),
            \xwp_app( 'pllat' )->get( Task_Repository::class ),
        );
    }

    /**
     * Constructor.
     *
     * @param int              $id The entity ID.
     * @param Language_Manager $language_manager The language manager.
     * @param Job_Repository   $job_repository The job repository.
     * @param Task_Repository  $task_repository The task repository.
     */
    public function __construct(
        protected int $id,
        protected Language_Manager $language_manager,
        protected Job_Repository $job_repository,
        protected Task_Repository $task_repository,
    ) {
    }

    /**
     * Set whether this entity is excluded from translation.
     *
     * @param bool $excluded Whether to exclude from translation.
     * @return void
     */
    abstract public function set_excluded_from_translation( bool $excluded ): void;

    /**
     * Get the ID of the post or term.
     *
     * @return int
     */
    public function get_id(): int {
        return $this->id;
    }

    /**
     * Returns the translations that are missing for the entity.
     *
     * @return array<string>
     */
    public function get_missing_languages(): array {
        return \array_diff(
            $this->get_available_languages(),
            \array_keys( $this->get_translations() ),
            array( $this->get_language() ),
        );
    }

    /**
     * Collects the missing translations by converting them into translation tasks.
     *
     * @return void
     */
    public function collect_translation_tasks_for( array $fields = array(), array $meta_fields = array() ): void {
        foreach ( $this->get_available_languages() as $lang ) {
            $this->collect_field_tasks_for_language( $lang, $fields );
            $this->collect_meta_field_tasks_for_language( $lang, $meta_fields );
        }
    }

    /**
     * Collects the missing translations by converting them into translation tasks.
     *
     * @return void
     */
    public function collect_missing_translation_tasks(): void {
        foreach ( $this->get_missing_languages() as $lang ) {
            $this->collect_field_tasks_for_language( $lang, $this->get_available_fields() );
            $this->collect_meta_field_tasks_for_language( $lang, $this->get_available_meta_fields() );
        }
    }

    /**
     * Collect translation tasks for specific target languages.
     * Used for single translation where we only want specific languages.
     *
     * @param array $target_languages The specific languages to create tasks for.
     * @param bool  $force Whether to create tasks even if translations already exist.
     * @return void
     */
    public function collect_tasks_for_languages( array $target_languages, bool $force = false ): void {
        // Determine which languages to process.
        if ( $force ) {
            $languages_to_process = $target_languages;
        } else {
            // Only process languages without existing translations.
            $existing_translations = $this->get_translations();
            $languages_to_process  = \array_diff( $target_languages, \array_keys( $existing_translations ) );
        }

        // Skip if no languages to process.
        if ( 0 === \count( $languages_to_process ) ) {
            return;
        }

        // Get all available fields and meta fields.
        $fields      = $this->get_available_fields();
        $meta_fields = $this->get_available_meta_fields();

        // Create tasks for each language.
        foreach ( $languages_to_process as $lang ) {
            $this->collect_field_tasks_for_language( $lang, $fields );
            $this->collect_meta_field_tasks_for_language( $lang, $meta_fields );
        }
    }

    /**
     * Get the last processed timestamp.
     *
     * @return int|null
     */
    public function get_last_processed(): ?int {
        return (int) $this->get_meta( MetaKey::Processed->value, true );
    }

    /**
     * Checks if the term is excluded from translation.
     *
     * @return bool True if the term is excluded from translation, false otherwise.
     */
    public function is_excluded_from_translation(): bool {
        return true === $this->get_meta( MetaKey::Exclude->value, true );
    }

    /**
     * Get the available languages for translation for this entity.
     * Always excludes the current entity's language to prevent same-language translation jobs.
     *
     * @return array<string>
     */
    public function get_available_languages(): array {
        $all_languages = $this->language_manager->get_available_languages( false );
        return \array_diff( $all_languages, array( $this->get_language() ) );
    }

    /**
     * Get the ID of the translation of this entity for a given language.
     *
     * @param string $language The language to get the translation for.
     * @return int|null
     */
    public function get_translation_by_language( string $language ): ?int {
        $translations = $this->get_translations();
        return $translations[ $language ] ?? null;
    }

    /**
     * Cleanup all translation accounting (jobs and tasks) for this entity.
     *
     * @return void
     */
    public function cleanup_jobs_and_tasks(): void {
        $job_query = $this->job_repository->query( $this->get_type() );
        $job_query->set_id_from( $this->id );
        $jobs = $this->job_repository->find_by( $job_query );

        foreach ( $jobs as $job ) {
            try {
                $this->job_repository->delete( $job );
            } catch ( \Throwable $e ) {
                \error_log( 'Error deleting job for translatable: ' . $e->getMessage() );
            }
        }
    }

    /**
     * Adds a translation task for a specific field.
     *
     * @param string $lang_to The language to translate to.
     * @param string $reference The reference key.
     * @param string $value The value to translate.
     */
    public function add_translation_task( string $lang_to, string $reference, string $value ): void {
        if ( $this->is_excluded_from_translation() ) {
            return;
        }

        $job = $this->job_repository->exists(
            $this->get_type(),
            $this->id,
            $this->get_language(),
            $lang_to,
            JobStatus::Pending,
        );
        if ( ! $job ) {
            $job = $this->job_repository->create(
                $this->get_type(),
                $this->id,
                $this->get_language(),
                $lang_to,
                $this->get_content_type(),
            );
        }

        // Check if a pending task already exists for this job and reference.
        $existing_task = $this->task_repository->find_by_job_and_reference( $job->get_id(), $reference );

        if ( $existing_task ) {
            // If task exists but value has changed, update it.
            if ( $existing_task->get_value() !== $value ) {
                $this->task_repository->update_value( $existing_task, $value );
            }
        } else {
            // Create new task if none exists.
            $this->task_repository->create(
                reference: $reference,
                value: $value,
                job_id: $job->get_id(),
            );
        }
    }

    /**
     * Check if a job exists for the given target language.
     * Used during discovery to prevent duplicate job creation.
     * Only checks for jobs with "coverage statuses" (pending, in_progress, completed).
     * This allows discovery to create new jobs for failed/cancelled translations.
     * Optimized to use single query instead of N+1 status checks.
     *
     * @param string $lang_to Target language code.
     * @return bool True if job exists with coverage status, false otherwise.
     */
    public function has_job_for_language( string $lang_to ): bool {
        $lang_from = $this->get_language();

        // Only check for jobs with coverage statuses to allow recreating failed jobs
        return $this->job_repository->has_jobs_for_content_language(
            $this->get_type(),
            $this->id,
            $lang_from,
            $lang_to,
            \PLLAT\Translator\Enums\JobStatus::getCoverageStatuses(),
        );
    }

    /**
     * Get translation ID for the given target language.
     * Returns the Polylang translation ID if it exists.
     *
     * @param string $lang_to Target language code.
     * @return int Translation ID, or 0 if no translation exists.
     */
    public function get_translation_id( string $lang_to ): int {
        $translations = $this->get_translations();
        return $translations[ $lang_to ] ?? 0;
    }

    /**
     * Create a pending job for missing translation.
     * Used during discovery to mark content needing translation.
     *
     * @param string $lang_to Target language code.
     * @return void
     */
    public function create_pending_job( string $lang_to ): void {
        $lang_from = $this->get_language();

        $this->job_repository->create(
            $this->get_type(),
            $this->id,
            $lang_from,
            $lang_to,
            $this->get_content_type(),
        );
    }

    /**
     * Create a completed job for existing Polylang translation.
     * Used during discovery to mark already-translated content (prevents re-discovery).
     *
     * @param string $lang_to Target language code.
     * @param int    $translation_id Translation ID in target language.
     * @return void
     */
    public function create_completed_job( string $lang_to, int $translation_id ): void {
        $lang_from = $this->get_language();

        // Create job via repository.
        $job = $this->job_repository->create(
            $this->get_type(),
            $this->id,
            $lang_from,
            $lang_to,
            $this->get_content_type(),
        );

        // Mark as completed with translation ID.
        $job->set_id_to( $translation_id );
        $job->complete();
        $this->job_repository->save( $job );
    }

    /**
     * Collect field translation tasks for a specific language.
     *
     * @param string $lang The target language.
     * @param array  $fields The fields to process.
     * @return void
     */
    private function collect_field_tasks_for_language( string $lang, array $fields ): void {
        foreach ( $fields as $field ) {
            $value = $this->get_data( $field );
            if ( ! $value ) {
                continue;
            }
            $this->add_translation_task(
                $lang,
                Content_Service::create_reference_key( $field ),
                $value,
            );
        }
    }

    /**
     * Collect meta field translation tasks for a specific language.
     *
     * @param string $lang The target language.
     * @param array  $meta_fields The meta fields to process.
     * @return void
     */
    private function collect_meta_field_tasks_for_language( string $lang, array $meta_fields ): void {
        foreach ( $meta_fields as $meta_field ) {
            $value = $this->get_meta( $meta_field, true );
            if ( ! $value || ! \is_string( $value ) ) {
                continue;
            }
            $this->add_translation_task(
                $lang,
                Content_Service::create_reference_key( $meta_field, 'meta' ),
                $value,
            );
        }
    }
}
