<?php
namespace PLLAT\Translator\Models;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
use PLLAT\Translator\Enums\TaskStatus;

/**
 * A translation task.
 * Pure entity - no database operations.
 */
class Task {
    /**
     * The table name for the Tasks.
     *
     * @var string
     */
    const TABLE_NAME = 'pllat_tasks';

    /**
     * The maximum number of attempts it may take to process the Task.
     *
     * @var int
     */
    const MAX_ATTEMPTS = 3;

    /**
     * The reference to the field that has been Taskd.
     *
     * @var string
     */
    protected string $reference;

    /**
     * The value of the field that has been Taskd.
     *
     * @var mixed
     */
    protected mixed $value;

    /**
     * The translation of the value.
     *
     * @var mixed
     */
    protected string|null $translation = null;

    /**
     * The status of the Task.
     *
     * @var string
     */
    protected TaskStatus $status;

    /**
     * The ID of the job that is processing the Task.
     *
     * @var int|null
     */
    protected int|null $job_id;

    /**
     * The number of attempts it took to process the Task.
     *
     * @var int
     */
    protected int $attempts = 0;

    /**
     * The issue that occurred while processing the Task.
     *
     * @var string|null
     */
    protected string|null $issue;

    /**
     * Constructor.
     *
     * @param int $id The ID of the Task.
     */
    public function __construct(
        protected int $id,
    ) {
        // Constructor intentionally does not load - repository will hydrate properties
    }

    /**
     * Check if the reference is a meta reference.
     *
     * @return bool
     */
    public function has_meta_reference(): bool {
        return \str_starts_with( $this->reference, '_meta|' );
    }

    /**
     * Check if the reference is a custom data reference.
     *
     * @return bool
     */
    public function has_custom_data_reference(): bool {
        return \str_starts_with( $this->reference, '_custom_data|' );
    }

    /**
     * Get the ID of the Task.
     *
     * @return int The ID of the Task.
     */
    public function get_id(): int {
        return $this->id;
    }

    /**
     * Get the reference to the field that has been Taskd.
     *
     * @return string The reference.
     */
    public function get_reference(): string {
        return $this->reference;
    }

    /**
     * Get the value of the field that has been Taskd.
     *
     * @return mixed The value.
     */
    public function get_value(): mixed {
        return $this->value;
    }

    /**
     * Get the number of attempts it took to process the Task.
     *
     * @return int The number of attempts.
     */
    public function get_attempts(): int {
        return $this->attempts;
    }

    /**
     * Get the issue that occurred while processing the Task.
     *
     * @return string|null The issue.
     */
    public function get_issue(): string|null {
        return $this->issue;
    }

    /**
     * Get the ID of the job that is processing the Task.
     *
     * @return int|null The ID of the job.
     */
    public function get_job_id(): int|null {
        return $this->job_id;
    }

    /**
     * Get the status of the Task.
     *
     * @return TaskStatus The status.
     */
    public function get_status(): TaskStatus {
        return $this->status;
    }

    /**
     * Get the translation of the Task.
     *
     * @return string|null The translation.
     */
    public function get_translation(): string|null {
        return $this->translation;
    }


    /**
     * Check if the Task is pending and can still be processed.
     *
     * @return bool
     */
    public function is_pending_process(): bool {
        $status = $this->get_status();
        return ! $status->isCompleted() && ! $this->reached_max_attempts() && ! $this->get_translation();
    }

    /**
     * Check if the Task is failed and has reached the maximum number of attempts.
     *
     * @return bool
     */
    public function is_exhausted(): bool {
        return $this->get_status()->isFailed() && $this->reached_max_attempts() && ! $this->get_translation();
    }

    /**
     * Check if the Task has reached the maximum number of attempts.
     *
     * @return bool
     */
    public function reached_max_attempts(): bool {
        return $this->get_attempts() >= self::MAX_ATTEMPTS;
    }

    /**
     * Check if the Task is failed.
     *
     * @return bool
     */
    public function is_failed(): bool {
        return $this->get_status()->isFailed();
    }

    /**
     * Check if the Task is completed.
     *
     * @return bool
     */
    public function is_completed(): bool {
        return $this->get_status()->isCompleted();
    }

    /**
     * Check if the Task is pending.
     *
     * @return bool
     */
    public function is_pending(): bool {
        return $this->get_status()->isPending();
    }

    /**
     * Check if the Task has a translation.
     *
     * @return bool
     */
    public function has_translation(): bool {
        return null !== $this->translation;
    }

    /**
     * Increment the attempts of the Task.
     *
     * @return void
     */
    public function increment_attempts(): void {
        ++$this->attempts;
    }

    /**
     * Complete the Task.
     *
     * @return void
     */
    public function complete(): void {
        $this->set_status( TaskStatus::Completed );
    }

    /**
     * Fail the Task.
     *
     * @param string|null $issue The issue that occurred while processing the Task.
     * @return void
     */
    public function fail( ?string $issue ): void {
        $this->set_status( TaskStatus::Failed );
        $this->set_issue( $issue );
    }

    /**
     * Set the status of the Task.
     *
     * @param TaskStatus $status The status.
     * @return void
     */
    public function set_status( TaskStatus $status ): void {
        $this->status = $status;
    }

    /**
     * Set the issue of the Task.
     *
     * @param string|null $issue The issue that occurred while processing the Task.
     * @return void
     */
    public function set_issue( ?string $issue ): void {
        // Truncate issue to 1000 characters to prevent database errors.
        // TEXT fields can hold 65KB, but we limit to 1000 chars for reasonable error messages.
        if ( null !== $issue && \mb_strlen( $issue ) > 1000 ) {
            $this->issue = \mb_substr( $issue, 0, 1000 ) . '... [truncated]';
        } else {
            $this->issue = $issue;
        }
    }

    /**
     * Set the translation of the Task.
     *
     * @param string|null $translation The translation.
     * @return void
     */
    public function set_translation( ?string $translation ): void {
        $this->translation = $translation;
    }

}
