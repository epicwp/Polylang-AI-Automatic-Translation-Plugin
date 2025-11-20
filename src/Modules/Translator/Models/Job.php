<?php
declare(strict_types=1);

namespace PLLAT\Translator\Models;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
use PLLAT\Translator\Enums\JobStatus;
use PLLAT\Translator\Models\Task;
use PLLAT\Translator\Repositories\Task_Repository;

/**
 * A job to translate a post or term.
 * Pure entity - no database operations.
 */
class Job {
    /**
     * The table name for the translation jobs.
     *
     * @var string
     */
    const TABLE_NAME = 'pllat_jobs';

    /**
     * Post or term.
     *
     * @var string
     */
    protected string $type;

    /**
     * The content type (post_type or taxonomy).
     *
     * @var string
     */
    protected string $content_type;

    /**
     * The ID of the post or term that has been tasked (source).
     *
     * @var int
     */
    protected int $id_from;

    /**
     * The ID of the target post or term (set on completion).
     *
     * @var int|null
     */
    protected int|null $id_to = null;

    /**
     * The language code from which the field has been tasked.
     *
     * @var string
     */
    protected string $lang_from;

    /**
     * The language code to which the field has been tasked.
     *
     * @var string
     */
    protected string $lang_to;

    /**
     * The ID of the run that is processing the job.
     *
     * @var int|null
     */
    protected int|null $run_id = null;

    /**
     * The status of the job.
     *
     * @var JobStatus
     */
    protected JobStatus $status;

    /**
     * The tasks for the job.
     *
     * @var array
     */
    protected array $tasks = array();

    /**
     * Whether the tasks have been loaded from the database.
     *
     * @var bool
     */
    private bool $tasks_loaded = false;

    /**
     * The created timestamp of the job.
     *
     * @var int
     */
    private int $created_at;

    /**
     * The started timestamp of the job (0 if not started).
     *
     * @var int
     */
    private int $started_at = 0;

    /**
     * The completed timestamp of the job (0 if not completed).
     *
     * @var int
     */
    private int $completed_at = 0;

    /**
     * Create a new job instance.
     * Note: This constructor does NOT load from database.
     * Use Job_Repository::find() to load an existing job.
     *
     * @param int                  $id              The ID of the job.
     * @param Task_Repository|null $task_repository The task repository (optional, auto-wired).
     */
    public function __construct(
        protected int $id,
        protected ?Task_Repository $task_repository = null,
    ) {
        // Constructor intentionally empty - repository will hydrate properties
        if ( null !== $this->task_repository ) {
            return;
        }

        $this->task_repository = \xwp_app( 'pllat' )->get( Task_Repository::class );
    }

    /**
     * Get the ID of the job.
     *
     * @return int The ID.
     */
    public function get_id(): int {
        return $this->id;
    }

    /**
     * Get the type of the job (post or term).
     *
     * @return string The type.
     */
    public function get_type(): string {
        return $this->type;
    }

    /**
     * Get the content type (post_type or taxonomy).
     *
     * @return string The content type.
     */
    public function get_content_type(): string {
        return $this->content_type;
    }

    /**
     * Get the ID of the post or term.
     *
     * @return int The ID.
     */
    public function get_id_from(): int {
        return $this->id_from;
    }

    /**
     * Get the target ID (set on completion).
     *
     * @return int|null The target ID.
     */
    public function get_id_to(): int|null {
        return $this->id_to;
    }

    /**
     * Set the target ID (used on completion).
     *
     * @param int|null $id_to The target ID.
     * @return void
     */
    public function set_id_to( ?int $id_to ): void {
        $this->id_to = $id_to;
    }

    /**
     * Get the language code from which the job is.
     *
     * @return string The language code.
     */
    public function get_lang_from(): string {
        return $this->lang_from;
    }

    /**
     * Get the language code to which the job is.
     *
     * @return string The language code.
     */
    public function get_lang_to(): string {
        return $this->lang_to;
    }

    /**
     * Get the status of the job.
     *
     * @return JobStatus The status.
     */
    public function get_status(): JobStatus {
        return $this->status;
    }

    /**
     * Set the status of the job.
     *
     * @param JobStatus $status The status.
     */
    public function set_status( JobStatus $status ): void {
        $this->status = $status;
    }

    /**
     * Get the run ID of the job.
     *
     * @return int|null The run ID.
     */
    public function get_run_id(): int|null {
        return $this->run_id;
    }

    /**
     * Set the run ID of the job.
     *
     * @param int|null $run_id The ID of the run that is processing the job.
     */
    public function set_run_id( ?int $run_id ): void {
        $this->run_id = $run_id;
    }

    /**
     * Get the started timestamp.
     *
     * @return int The started timestamp.
     */
    public function get_started_at(): int {
        return $this->started_at;
    }

    /**
     * Set the started timestamp of the job.
     *
     * @param int $started_at The started timestamp.
     */
    public function set_started_at( int $started_at ): void {
        $this->started_at = $started_at;
    }

    /**
     * Get the created timestamp.
     *
     * @return int The created timestamp.
     */
    public function get_created_at(): int {
        return $this->created_at;
    }

    /**
     * Get the completed timestamp.
     *
     * @return int The completed timestamp.
     */
    public function get_completed_at(): int {
        return $this->completed_at;
    }

    /**
     * Set the completed timestamp of the job.
     *
     * @param int $completed_at The completed timestamp.
     */
    public function set_completed_at( int $completed_at ): void {
        $this->completed_at = $completed_at;
    }

    /**
     * Get the tasks for the job.
     * Note: Tasks are loaded lazily from database.
     *
     * @return array The tasks.
     */
    public function get_tasks(): array {
        if ( ! $this->tasks_loaded ) {
            $this->load_tasks();
        }
        return $this->tasks;
    }

    /**
     * Force refresh tasks from database.
     */
    public function refresh_tasks(): void {
        $this->tasks_loaded = false;
        $this->tasks        = array();
    }

    /**
     * Add a task to the job's task list.
     * Note: This does NOT save to database.
     *
     * @param Task $task The task to add.
     */
    public function add_task( Task $task ): void {
        $this->tasks[] = $task;
    }

    /**
     * Set the job to in progress (domain logic).
     * Note: Does NOT save to database - use Job_Repository::save()
     */
    public function start(): void {
        if ( $this->is_pending() ) {
            $this->set_started_at( \time() );
        }
        $this->set_status( JobStatus::InProgress );
    }

    /**
     * Set the job to completed (domain logic).
     * Note: Does NOT save to database - use Job_Repository::save()
     */
    public function complete(): void {
        $this->set_status( JobStatus::Completed );
        $this->set_completed_at( \time() );
    }

    /**
     * Set the job to failed (domain logic).
     * Note: Does NOT save to database - use Job_Repository::save()
     */
    public function fail(): void {
        $this->set_status( JobStatus::Failed );
    }

    /**
     * Set the job to cancelled (domain logic).
     * Note: Does NOT save to database - use Job_Repository::save()
     */
    public function cancel(): void {
        $this->set_status( JobStatus::Cancelled );
    }

    /**
     * Check if the job has failed.
     *
     * @return bool
     */
    public function is_failed(): bool {
        return $this->get_status()->isFailed();
    }

    /**
     * Check if the job has completed.
     *
     * @return bool
     */
    public function is_completed(): bool {
        return $this->get_status()->isCompleted();
    }

    /**
     * Check if the job is pending.
     *
     * @return bool
     */
    public function is_pending(): bool {
        return JobStatus::Pending === $this->get_status();
    }

    /**
     * Check if the job has all tasks completed.
     *
     * @return bool
     */
    public function all_tasks_completed(): bool {
        $tasks = $this->get_tasks();
        foreach ( $tasks as $task ) {
            if ( ! $task->is_completed() ) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if the job has any exhausted failed tasks.
     *
     * @return bool
     */
    public function has_any_exhausted_tasks(): bool {
        $tasks = $this->get_tasks();
        foreach ( $tasks as $task ) {
            if ( $task->is_exhausted() ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Load the tasks for the job from database.
     */
    private function load_tasks(): void {
        $this->tasks        = $this->task_repository->find_by_job_id( $this->id );
        $this->tasks_loaded = true;
    }
}
