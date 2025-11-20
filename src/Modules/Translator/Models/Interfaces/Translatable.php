<?php
namespace PLLAT\Translator\Models\Interfaces;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
use PLLAT\Common\Interfaces\Language_Manager;
use PLLAT\Translator\Repositories\Job_Repository;
use PLLAT\Translator\Repositories\Task_Repository;

/**
 * Interface for translatable entities (terms and posts).
 */
interface Translatable {
    /**
     * Get the instance of the translatable.
     *
     * @param int $id
     * @return static
     */
    public static function get_instance( int $id ): static;

    /**
     * Constructor.
     *
     * @param int              $id
     * @param Language_Manager $language_manager
     * @param Job_Repository   $job_repository
     * @param Task_Repository  $task_repository
     */
    public function __construct( int $id, Language_Manager $language_manager, Job_Repository $job_repository, Task_Repository $task_repository );

    /**
     * Get the ID of the post or term.
     *
     * @return int
     */
    public function get_id(): int;

    /**
     * Get the language code of the post or term.
     *
     * @return string
     */
    public function get_language(): string;

    /**
     * Get the type classification for job creation.
     * Returns 'post' for all post types (post, page, custom post types).
     * Returns 'term' for all taxonomies (category, post_tag, custom taxonomies).
     *
     * @return string 'post' or 'term'
     */
    public function get_type(): string;

    /**
     * Get the WordPress content type.
     * Returns the post_type for posts or taxonomy for terms.
     *
     * @return string The WordPress post_type (e.g., 'post', 'page', 'product') or taxonomy (e.g., 'category', 'post_tag')
     */
    public function get_content_type(): string;

    /**
     * Get the last processed timestamp of the post or term.
     *
     * @return int|null
     */
    public function get_last_processed(): ?int;

    /**
     * Get the title of the post or term.
     *
     * @return string
     */
    public function get_title(): string;

    /**
     * Get the translations of the post or term.
     *
     * @return array<string, int>
     */
    public function get_translations(): array;

    /**
     * Get the missing translations of the post or term.
     *
     * @return array<string>
     */
    public function get_missing_languages(): array;

    /**
     * Get the meta data of the post or term.
     *
     * @param string $key
     * @param bool   $single
     * @return mixed
     */
    public function get_meta( string $key, bool $single = false );

    /**
     * Check if this post or term is excluded from translation.
     *
     * @return bool
     */
    public function is_excluded_from_translation(): bool;

    /**
     * Get the translation ID of the post or term for a specific language.
     *
     * @param string $language
     * @return int|null
     */
    public function get_translation_by_language( string $language ): ?int;

    /**
     * Get the available fields for the post or term.
     *
     * @return array<string>
     */
    public function get_available_fields(): array;

    /**
     * Get the available meta fields for the post or term.
     *
     * @return array<string>
     */
    public function get_available_meta_fields(): array;

    /**
     * Get the available languages for this translatable.
     *
     * @return array<string>
     */
    public function get_available_languages(): array;

    /**
     * Get the data underlying post or term data.
     *
     * @param string $field The field to get the data for.
     * @return string|null The value of the field.
     */
    public function get_data( string $field ): ?string;

    /**
     * Get all the data underlying post or term data as an array.
     *
     * @return array<string, mixed> The data underlying post or term data as an array.
     */
    public function get_all_data(): array;

    /**
     * Get the meta data underlying post or term meta data as an array.
     *
     * @return array<string, mixed>
     */
    public function get_all_meta( bool $flatten = true ): array;

    /**
     * Adds a translation task for a specific field.
     *
     * @param string $lang_to The language to translate to.
     * @param string $reference The reference key.
     * @param string $value The value to translate.
     */
    public function add_translation_task( string $lang_to, string $reference, string $value ): void;

    /**
     * Check if a job exists for the given target language.
     *
     * @param string $lang_to Target language code.
     * @return bool True if job exists, false otherwise.
     */
    public function has_job_for_language( string $lang_to ): bool;

    /**
     * Get the translation ID for the given target language.
     *
     * @param string $lang_to Target language code.
     * @return int Translation ID, or 0 if no translation exists.
     */
    public function get_translation_id( string $lang_to ): int;

    /**
     * Create a pending job for missing translation.
     *
     * @param string $lang_to Target language code.
     * @return void
     */
    public function create_pending_job( string $lang_to ): void;

    /**
     * Create a completed job for existing Polylang translation.
     *
     * @param string $lang_to Target language code.
     * @param int    $translation_id Translation ID in target language.
     * @return void
     */
    public function create_completed_job( string $lang_to, int $translation_id ): void;

    /**
     * Collect translation tasks for specific target languages.
     *
     * @param array $target_languages The specific languages to create tasks for.
     * @param bool  $force Whether to create tasks even if translations already exist.
     * @return void
     */
    public function collect_tasks_for_languages( array $target_languages, bool $force = false ): void;
}
