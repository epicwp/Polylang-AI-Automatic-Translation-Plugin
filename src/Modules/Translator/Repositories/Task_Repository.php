<?php
declare(strict_types=1);

namespace PLLAT\Translator\Repositories;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
use PLLAT\Translator\Enums\TaskStatus;
use PLLAT\Translator\Models\Task;
use ReflectionClass;

/**
 * Repository for Task entities.
 * Handles all database operations for tasks.
 */
class Task_Repository {
    /**
     * Create a new task.
     *
     * @param string $reference The reference to the field.
     * @param mixed  $value     The value of the field.
     * @param int    $job_id    The ID of the job.
     * @return Task The created task.
     */
    public function create( string $reference, mixed $value, int $job_id ): Task {
        global $wpdb;

        $wpdb->insert(
            $this->get_table_name(),
            array(
                'job_id'      => $job_id,
                'reference'   => $reference,
                'status'      => TaskStatus::Pending->value,
                'translation' => null,
                'value'       => $value,
            ),
        );

        return $this->find( $wpdb->insert_id );
    }

    /**
     * Find a task by ID.
     *
     * @param int $id The task ID.
     * @return Task The task.
     * @throws \Exception If task not found.
     */
    public function find( int $id ): Task {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->get_table_name()} WHERE id = %d",
                $id,
            ),
            ARRAY_A,
        );

        if ( ! $row ) {
            throw new \Exception( 'Task row not found in database.' );
        }

        return $this->hydrate_task( $row );
    }

    /**
     * Find all tasks for a job.
     *
     * @param int $job_id The job ID.
     * @return array Array of Task objects.
     */
    public function find_by_job_id( int $job_id ): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->get_table_name()} WHERE job_id = %d",
                $job_id,
            ),
            ARRAY_A,
        );

        return \array_map( array( $this, 'hydrate_task' ), $rows );
    }

    /**
     * Find a task for a specific job and reference.
     *
     * @param int    $job_id    The job ID.
     * @param string $reference The reference field.
     * @return Task|null The task or null if not found.
     */
    public function find_by_job_and_reference( int $job_id, string $reference ): ?Task {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->get_table_name()} WHERE job_id = %d AND reference = %s AND status = %s",
                $job_id,
                $reference,
                TaskStatus::Pending->value,
            ),
            ARRAY_A,
        );

        return $row ? $this->hydrate_task( $row ) : null;
    }

    /**
     * Save a task to the database.
     *
     * @param Task $task The task to save.
     * @return void
     * @throws \Exception If the update fails.
     */
    public function save( Task $task ): void {
        global $wpdb;

        $data = array(
            'attempts'    => $task->get_attempts(),
            'issue'       => $task->get_issue(),
            'reference'   => $task->get_reference(),
            'status'      => $task->get_status()->value,
            'translation' => $task->get_translation(),
            'value'       => $task->get_value(),
        );

        $where = array( 'id' => $task->get_id() );

        $result = $wpdb->update(
            $this->get_table_name(),
            $data,
            $where,
        );

        // Check for database errors.
        if ( false === $result ) {
            $error_message = 'Failed to update task ' . $task->get_id() . ': ' . $wpdb->last_error;
            throw new \Exception( \esc_html( $error_message ) );
        }

        // Trigger cascade to update parent job/run statuses.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        \do_action( 'pllat_task_saved', $task );
    }

    /**
     * Update only the value of a task.
     *
     * @param Task   $task  The task to update.
     * @param string $value The new value.
     * @return void
     */
    public function update_value( Task $task, string $value ): void {
        global $wpdb;

        $wpdb->update(
            $this->get_table_name(),
            array( 'value' => $value ),
            array( 'id' => $task->get_id() ),
        );
    }

    /**
     * Delete a task from the database.
     *
     * @param Task $task The task to delete.
     * @return void
     */
    public function delete( Task $task ): void {
        global $wpdb;

        $wpdb->delete(
            $this->get_table_name(),
            array( 'id' => $task->get_id() ),
        );
    }

    /**
     * Get the table name.
     *
     * @return string The table name.
     */
    public function get_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'pllat_tasks';
    }

    /**
     * Hydrate a Task entity from database row.
     *
     * @param array $row Database row data.
     * @return Task Hydrated task.
     */
    private function hydrate_task( array $row ): Task {
        $task       = new Task( (int) $row['id'] );
        $reflection = new ReflectionClass( Task::class );

        $this->set_property( $reflection, $task, 'id', (int) $row['id'] );
        $this->set_property( $reflection, $task, 'reference', $row['reference'] );
        $this->set_property( $reflection, $task, 'value', $row['value'] );
        $this->set_property( $reflection, $task, 'translation', $row['translation'] );
        $this->set_property( $reflection, $task, 'status', TaskStatus::from( $row['status'] ) );
        $this->set_property( $reflection, $task, 'attempts', (int) $row['attempts'] );
        $this->set_property( $reflection, $task, 'issue', $row['issue'] );
        $this->set_property( $reflection, $task, 'job_id', (int) $row['job_id'] );

        return $task;
    }

    /**
     * Set a protected property using reflection.
     *
     * @param ReflectionClass $reflection The reflection class.
     * @param Task            $task       The task instance.
     * @param string          $property_name The property name.
     * @param mixed           $value      The value to set.
     * @return void
     */
    private function set_property( $reflection, Task $task, string $property_name, mixed $value ): void {
        $property = $reflection->getProperty( $property_name );
        $property->setAccessible( true );
        $property->setValue( $task, $value );
    }
}
