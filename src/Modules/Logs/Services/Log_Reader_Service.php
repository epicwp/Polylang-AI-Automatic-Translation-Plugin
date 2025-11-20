<?php
declare(strict_types=1);

namespace PLLAT\Logs\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use PLLAT\Translator\Models\Run;

/**
 * Log reader service.
 *
 * Reads and parses log files created by Logger_Service.
 */
class Log_Reader_Service {
    /**
     * Logger service.
     *
     * @var Logger_Service
     */
    private Logger_Service $logger_service;

    /**
     * Constructor.
     *
     * @param Logger_Service $logger_service The logger service.
     */
    public function __construct( Logger_Service $logger_service ) {
        $this->logger_service = $logger_service;
    }

    /**
     * Get recent logs (for dashboard display).
     *
     * @param int         $limit   Maximum number of logs to return.
     * @param int         $offset  Offset for pagination.
     * @param string|null $type    Filter by log level (info, warning, error, success).
     * @param int|null    $run_id  Filter by run ID.
     * @return array<int, array<string, mixed>> Array of log entries.
     */
    public function get_recent_logs( int $limit = 50, int $offset = 0, ?string $type = null, ?int $run_id = null ): array {
        $log_files = $this->get_log_files();
        $all_logs  = array();

        // Parse log files (newest first)
        foreach ( $log_files as $log_file ) {
            $file_logs = $this->parse_log_file( $log_file );
            $all_logs  = \array_merge( $all_logs, $file_logs );
        }

        // Sort by timestamp (newest first)
        \usort(
            $all_logs,
            static fn( $a, $b ) => \strtotime( $b['timestamp'] ) - \strtotime( $a['timestamp'] ),
        );

        // Filter by type
        if ( $type && 'all' !== $type ) {
            $all_logs = \array_filter(
                $all_logs,
                static function ( $log ) use ( $type ) {
                    // Map 'success' to 'info' level for completed events
                    if ( 'success' === $type ) {
                        return 'INFO' === $log['level'] &&
                            ( 'job_completed' === $log['event_type'] || 'run_completed' === $log['event_type'] );
                    }

                    // Map type to Monolog levels
                    $level_map = array(
                        'info'    => 'INFO',
                        'warning' => 'WARNING',
                        'error'   => 'ERROR',
                    );

                    return isset( $level_map[ $type ] ) && $level_map[ $type ] === $log['level'];
                },
            );
        }

        // Filter by run_id
        if ( $run_id ) {
            $all_logs = \array_filter(
                $all_logs,
                static fn( $log ) => isset( $log['run_id'] ) && (int) $log['run_id'] === $run_id,
            );
        }

        // Apply pagination
        $all_logs = \array_slice( $all_logs, $offset, $limit );

        // Transform for frontend
        return \array_map( array( $this, 'transform_log_for_frontend' ), $all_logs );
    }

    /**
     * Get logs for a specific run.
     *
     * @param Run $run The run to get logs for.
     * @return array<int, array<string, mixed>> The logs.
     */
    public function get_logs( Run $run ): array {
        return $this->get_recent_logs( 1000, 0, null, $run->get_id() );
    }

    /**
     * Get logs for a specific date.
     *
     * @param string      $date   Date in YYYY-MM-DD format.
     * @param string|null $type   Filter by log level (info, warning, error, success).
     * @param int|null    $run_id Filter by run ID.
     * @return array<int, array<string, mixed>> Array of log entries.
     */
    public function get_logs_for_date( string $date, ?string $type = null, ?int $run_id = null ): array {
        $log_file = $this->get_log_file_for_date( $date );

        if ( ! $log_file || ! \file_exists( $log_file ) ) {
            return array();
        }

        $logs = $this->parse_log_file( $log_file );

        // Sort by timestamp (newest first)
        \usort(
            $logs,
            static fn( $a, $b ) => \strtotime( $b['timestamp'] ) - \strtotime( $a['timestamp'] ),
        );

        // Filter by type
        if ( $type && 'all' !== $type ) {
            $logs = \array_filter(
                $logs,
                static function ( $log ) use ( $type ) {
                    if ( 'success' === $type ) {
                        return 'INFO' === $log['level'] &&
                            ( 'job_completed' === $log['event_type'] || 'run_completed' === $log['event_type'] );
                    }

                    $level_map = array(
                        'info'    => 'INFO',
                        'warning' => 'WARNING',
                        'error'   => 'ERROR',
                    );

                    return isset( $level_map[ $type ] ) && $level_map[ $type ] === $log['level'];
                },
            );
        }

        // Filter by run_id
        if ( $run_id ) {
            $logs = \array_filter(
                $logs,
                static fn( $log ) => isset( $log['run_id'] ) && (int) $log['run_id'] === $run_id,
            );
        }

        return \array_map( array( $this, 'transform_log_for_frontend' ), \array_values( $logs ) );
    }

    /**
     * Get list of available log dates.
     *
     * @return array<int, string> Array of dates in YYYY-MM-DD format (newest first).
     */
    public function get_available_log_dates(): array {
        $log_files = $this->get_log_files();
        $dates     = array();

        foreach ( $log_files as $log_file ) {
            $filename = \basename( $log_file );
            // Extract date from filename: translation-YYYY-MM-DD.log
            if ( \preg_match( '/translation-(\d{4}-\d{2}-\d{2})\.log$/', $filename, $matches ) ) {
                $dates[] = $matches[1];
            }
        }

        return $dates;
    }

    /**
     * Get total count of logs (for pagination).
     *
     * @param string|null $type   Filter by log level.
     * @param int|null    $run_id Filter by run ID.
     * @return int Total number of logs.
     */
    public function get_total_logs_count( ?string $type = null, ?int $run_id = null ): int {
        $log_files = $this->get_log_files();
        $count     = 0;

        foreach ( $log_files as $log_file ) {
            $file_logs = $this->parse_log_file( $log_file );

            // Filter by type
            if ( $type && 'all' !== $type ) {
                $file_logs = \array_filter(
                    $file_logs,
                    static function ( $log ) use ( $type ) {
                        if ( 'success' === $type ) {
                            return 'INFO' === $log['level'] &&
                                ( 'job_completed' === $log['event_type'] || 'run_completed' === $log['event_type'] );
                        }

                        $level_map = array(
                            'info'    => 'INFO',
                            'warning' => 'WARNING',
                            'error'   => 'ERROR',
                        );

                        return isset( $level_map[ $type ] ) && $level_map[ $type ] === $log['level'];
                    },
                );
            }

            // Filter by run_id
            if ( $run_id ) {
                $file_logs = \array_filter(
                    $file_logs,
                    static fn( $log ) => isset( $log['run_id'] ) && (int) $log['run_id'] === $run_id,
                );
            }

            $count += \count( $file_logs );
        }

        return $count;
    }

    /**
     * Clear old log files.
     *
     * @param int $days Keep logs for this many days (default: 30).
     * @return int Number of files deleted.
     */
    public function clear_old_logs( int $days = 30 ): int {
        $log_files = $this->get_log_files();
        $deleted   = 0;
        $cutoff    = \time() - ( $days * DAY_IN_SECONDS );

        foreach ( $log_files as $log_file ) {
            if ( \filemtime( $log_file ) >= $cutoff ) {
                continue;
            }

            if ( ! \unlink( $log_file ) ) {
                continue;
            }

            ++$deleted;
        }

        return $deleted;
    }

    /**
     * Get list of log files (newest first).
     *
     * @return array<int, string> Array of log file paths.
     */
    private function get_log_files(): array {
        $log_dir   = $this->logger_service->get_log_dir();
        $log_files = \glob( $log_dir . '/translation-*.log' );

        if ( false === $log_files ) {
            return array();
        }

        // Sort by modification time (newest first)
        \usort(
            $log_files,
            static fn( $a, $b ) => \filemtime( $b ) - \filemtime( $a ),
        );

        return $log_files;
    }

    /**
     * Get log file path for a specific date.
     *
     * @param string $date Date in YYYY-MM-DD format.
     * @return string|null The log file path, or null if not found.
     */
    private function get_log_file_for_date( string $date ): ?string {
        $log_dir  = $this->logger_service->get_log_dir();
        $filename = \sprintf( '%s/translation-%s.log', $log_dir, $date );

        return \file_exists( $filename ) ? $filename : null;
    }

    /**
     * Parse a log file (JSON format from Monolog).
     *
     * @param string $file_path Path to the log file.
     * @return array<int, array<string, mixed>> Array of parsed log entries.
     */
    private function parse_log_file( string $file_path ): array {
        if ( ! \file_exists( $file_path ) ) {
            return array();
        }

        $lines = \file( $file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
        if ( false === $lines ) {
            return array();
        }

        $logs = array();
        foreach ( $lines as $line ) {
            $decoded = \json_decode( $line, true );
            if ( ! $decoded || ! \is_array( $decoded ) ) {
                continue;
            }

            // Extract context data
            $context = $decoded['context'] ?? array();

            // Build log entry
            $log_entry = array(
                'event_type' => $context['event_type'] ?? 'unknown',
                'level'      => $decoded['level_name'] ?? '',
                'message'    => $decoded['message'] ?? '',
                'timestamp'  => $decoded['datetime'] ?? '',
            );

            // Merge context data
            $log_entry = \array_merge( $log_entry, $context );

            $logs[] = $log_entry;
        }

        return $logs;
    }

    /**
     * Transform log entry for frontend consumption.
     *
     * @param array<string, mixed> $log The log entry.
     * @return array<string, mixed> Transformed log entry.
     */
    private function transform_log_for_frontend( array $log ): array {
        // Determine log type for frontend
        $type = 'info';
        if ( 'ERROR' === $log['level'] ) {
            $type = 'error';
        } elseif ( 'WARNING' === $log['level'] ) {
            $type = 'warning';
        } elseif ( \in_array( $log['event_type'], array( 'job_completed', 'run_completed' ), true ) ) {
            $type = 'success';
        }

        // Format content type label
        $content_type_label = 'System';
        if ( isset( $log['content_type'] ) ) {
            $content_type_label = \ucfirst( $log['content_type'] );
        } elseif ( isset( $log['type'] ) ) {
            $content_type_label = \ucfirst( $log['type'] );
        }

        // Format language label
        $language_label = '-';
        if ( isset( $log['lang_to'] ) && \function_exists( 'pll_languages_list' ) ) {
            $languages      = \pll_languages_list( array( 'fields' => 'name' ) );
            $lang_codes     = \pll_languages_list( array( 'fields' => 'slug' ) );
            $index          = \array_search( $log['lang_to'], $lang_codes, true );
            $language_label = false !== $index && isset( $languages[ $index ] ) ? $languages[ $index ] : \strtoupper(
                $log['lang_to'],
            );
        }

        return array(
            'contentType' => $content_type_label,
            'id'          => \md5( $log['timestamp'] . $log['message'] ),
            'job_id'      => $log['job_id'] ?? null,
            'language'    => $language_label,
            'message'     => $log['message'],
            'run_id'      => $log['run_id'] ?? null,
            'timestamp'   => $log['timestamp'],
            'type'        => $type,
        );
    }
}
