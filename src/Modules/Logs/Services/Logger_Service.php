<?php
declare(strict_types=1);

namespace PLLAT\Logs\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\Logger;
use PLLAT\Translator\Models\Job;
use PLLAT\Translator\Models\Run;

/**
 * Logger service using Monolog.
 *
 * Writes translation events to daily rotating JSON log files.
 */
class Logger_Service {
    /**
     * Monolog logger instance.
     *
     * @var Logger|null
     */
    private ?Logger $logger = null;

    /**
     * Log directory path.
     *
     * @var string
     */
    private string $log_dir;

    /**
     * Maximum number of log files to keep.
     *
     * @var int
     */
    private int $max_files = 30;

    /**
     * Constructor.
     */
    public function __construct() {
        $upload_dir    = \wp_upload_dir();
        $this->log_dir = $upload_dir['basedir'] . '/pllat-logs';

        // Ensure log directory exists
        $this->ensure_log_directory();
    }

    /**
     * Get the log directory path.
     *
     * @return string The log directory path.
     */
    public function get_log_dir(): string {
        return $this->log_dir;
    }

    /**
     * Log when a run is created/initiated by user.
     *
     * @param Run $run The run that was created.
     * @return void
     */
    public function log_run_created( Run $run ): void {
        $config = $run->get_config();

        $this->get_logger()->info(
            \sprintf( 'Translation run #%d created', $run->get_id() ),
            array(
                'event_type' => 'run_created',
                'forced'     => $config->is_forced(),
                'langs_to'   => \array_map( 'strtoupper', $config->get_langs_to() ),
                'lang_from'  => \strtoupper( $config->get_lang_from() ),
                'post_types' => $config->get_post_types(),
                'run_id'     => $run->get_id(),
                'taxonomies' => $config->get_taxonomies(),
            ),
        );
    }

    /**
     * Log when a run processing actually starts (picked up by processor).
     *
     * @param Run $run The run that started processing.
     * @return void
     */
    public function log_run_processing_started( Run $run ): void {
        $this->get_logger()->info(
            \sprintf( 'Translation run #%d processing started', $run->get_id() ),
            array(
                'event_type' => 'run_processing_started',
                'run_id'     => $run->get_id(),
            ),
        );
    }

    /**
     * Log when a run completes.
     *
     * @param Run $run The run that completed.
     * @return void
     */
    public function log_run_completed( Run $run ): void {
        $this->get_logger()->info(
            \sprintf( 'Translation run #%d completed successfully', $run->get_id() ),
            array(
                'duration'   => \time() - $run->get_started_at(),
                'event_type' => 'run_completed',
                'run_id'     => $run->get_id(),
            ),
        );
    }

    /**
     * Log when a run fails.
     *
     * @param Run    $run   The run that failed.
     * @param string $error The error message.
     * @return void
     */
    public function log_run_failed( Run $run, string $error ): void {
        $this->get_logger()->error(
            \sprintf( 'Translation run #%d failed: %s', $run->get_id(), $error ),
            array(
                'error'      => $error,
                'event_type' => 'run_failed',
                'run_id'     => $run->get_id(),
            ),
        );
    }

    /**
     * Log when a run is cancelled.
     *
     * @param Run $run The run that was cancelled.
     * @return void
     */
    public function log_run_cancelled( Run $run ): void {
        $this->get_logger()->warning(
            \sprintf( 'Translation run #%d was cancelled', $run->get_id() ),
            array(
                'event_type' => 'run_cancelled',
                'run_id'     => $run->get_id(),
            ),
        );
    }

    /**
     * Log when a job completes successfully.
     *
     * @param Job $job The job that completed.
     * @return void
     */
    public function log_job_completed( Job $job ): void {
        try {
            $content_type  = $job->get_content_type();
            $content_title = $this->get_content_title( $job );
            $duration      = $job->get_completed_at() > 0 && $job->get_started_at() > 0
                ? $job->get_completed_at() - $job->get_started_at()
                : 0;

            $this->get_logger()->info(
                \sprintf(
                    'Successfully translated %s #%d (%s) from %s to %s',
                    $content_type,
                    $job->get_id_from(),
                    $content_title,
                    \strtoupper( $job->get_lang_from() ),
                    \strtoupper( $job->get_lang_to() ),
                ),
                array(
                    'content_id'    => $job->get_id_from(),
                    'content_title' => $content_title,
                    'content_type'  => $content_type,
                    'duration'      => $duration,
                    'event_type'    => 'job_completed',
                    'job_id'        => $job->get_id(),
                    'lang_from'     => \strtoupper( $job->get_lang_from() ),
                    'lang_to'       => \strtoupper( $job->get_lang_to() ),
                    'run_id'        => $job->get_run_id(),
                    'type'          => $job->get_type(),
                ),
            );
        } catch ( \Error $e ) {
            // Fallback for uninitialized properties
            $this->get_logger()->info(
                \sprintf(
                    'Job #%d completed (details unavailable: %s)',
                    $job->get_id(),
                    $e->getMessage(),
                ),
                array(
                    'event_type' => 'job_completed',
                    'job_id'     => $job->get_id(),
                    'run_id'     => $job->get_run_id() ?? null,
                    'error'      => 'Property access error: ' . $e->getMessage(),
                ),
            );
        }
    }

    /**
     * Log when a job fails.
     *
     * @param Job    $job   The job that failed.
     * @param string $error The error message.
     * @return void
     */
    public function log_job_failed( Job $job, string $error ): void {
        try {
            $content_type  = $job->get_content_type();
            $content_title = $this->get_content_title( $job );

            $this->get_logger()->error(
                \sprintf(
                    'Translation failed for %s #%d (%s) from %s to %s: %s',
                    $content_type,
                    $job->get_id_from(),
                    $content_title,
                    \strtoupper( $job->get_lang_from() ),
                    \strtoupper( $job->get_lang_to() ),
                    $error,
                ),
                array(
                    'content_id'    => $job->get_id_from(),
                    'content_title' => $content_title,
                    'content_type'  => $content_type,
                    'error'         => $error,
                    'event_type'    => 'job_failed',
                    'job_id'        => $job->get_id(),
                    'lang_from'     => \strtoupper( $job->get_lang_from() ),
                    'lang_to'       => \strtoupper( $job->get_lang_to() ),
                    'run_id'        => $job->get_run_id(),
                    'type'          => $job->get_type(),
                ),
            );
        } catch ( \Error $e ) {
            // Fallback for uninitialized properties
            $this->get_logger()->error(
                \sprintf(
                    'Job #%d failed: %s (details unavailable: %s)',
                    $job->get_id(),
                    $error,
                    $e->getMessage(),
                ),
                array(
                    'event_type' => 'job_failed',
                    'job_id'     => $job->get_id(),
                    'run_id'     => $job->get_run_id() ?? null,
                    'error'      => $error,
                    'property_error' => $e->getMessage(),
                ),
            );
        }
    }

    /**
     * Log when discovery cycle finds new content.
     *
     * @param int $posts_found     Number of posts found.
     * @param int $terms_found     Number of terms found.
     * @param int $posts_processed Number of posts processed.
     * @param int $terms_processed Number of terms processed.
     * @return void
     */
    public function log_discovery_cycle( int $posts_found, int $terms_found, int $posts_processed, int $terms_processed ): void {
        $content_parts = array();
        if ( $posts_processed > 0 ) {
            $content_parts[] = \sprintf( '%d post(s)', $posts_processed );
        }
        if ( $terms_processed > 0 ) {
            $content_parts[] = \sprintf( '%d term(s)', $terms_processed );
        }

        $message = \sprintf(
            'New content queued for translation: %s',
            \implode( ', ', $content_parts ),
        );

        $this->get_logger()->info(
            $message,
            array(
                'event_type'       => 'discovery_cycle',
                'posts_found'      => $posts_found,
                'posts_processed'  => $posts_processed,
                'terms_found'      => $terms_found,
                'terms_processed'  => $terms_processed,
            ),
        );
    }

    /**
     * Log a generic warning.
     *
     * @param string               $message The warning message.
     * @param array<string, mixed> $context Additional context.
     * @return void
     */
    public function log_warning( string $message, array $context = array() ): void {
        $context['event_type'] = 'warning';
        $this->get_logger()->warning( $message, $context );
    }

    /**
     * Log a generic error.
     *
     * @param string               $message The error message.
     * @param array<string, mixed> $context Additional context.
     * @return void
     */
    public function log_error( string $message, array $context = array() ): void {
        $context['event_type'] = 'error';
        $this->get_logger()->error( $message, $context );
    }

    /**
     * Get the Monolog logger instance.
     *
     * @return Logger The logger instance.
     */
    private function get_logger(): Logger {
        if ( null === $this->logger ) {
            $this->logger = new Logger( 'pllat-translation' );

            // Daily rotating file handler
            $handler = new RotatingFileHandler(
                $this->log_dir . '/translation.log',
                $this->max_files,
                Level::Info,
            );

            // JSON formatter for structured logs
            $formatter = new JsonFormatter();
            $handler->setFormatter( $formatter );

            $this->logger->pushHandler( $handler );
        }

        return $this->logger;
    }

    /**
     * Ensure log directory exists and is protected.
     *
     * @return void
     */
    private function ensure_log_directory(): void {
        if ( ! \file_exists( $this->log_dir ) ) {
            \wp_mkdir_p( $this->log_dir );
        }

        // Add .htaccess to prevent direct access
        $htaccess = $this->log_dir . '/.htaccess';
        if ( ! \file_exists( $htaccess ) ) {
            \file_put_contents( $htaccess, "Deny from all\n" );
        }

        // Add index.php to prevent directory listing
        $index = $this->log_dir . '/index.php';
        if ( \file_exists( $index ) ) {
            return;
        }

        \file_put_contents( $index, "<?php\n// Silence is golden.\n" );
    }

    /**
     * Get the title of the content being translated.
     *
     * @param Job $job The job.
     * @return string The content title.
     */
    private function get_content_title( Job $job ): string {
        if ( 'post' === $job->get_type() ) {
            $post = \get_post( $job->get_id_from() );
            return $post ? $post->post_title : 'Unknown';
        }

        $term = \get_term( $job->get_id_from() );
        return $term && ! \is_wp_error( $term ) ? $term->name : 'Unknown';
    }
}
