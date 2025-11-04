<?php
/**
 * Sync_Status enum file.
 *
 * @package Polylang AI Automatic Translation
 * @subpackage Sync
 */

namespace PLLAT\Sync\Enums;

/**
 * Sync status enum.
 * Represents the discovery status (hybrid architecture, no import).
 */
enum Sync_Status: string {
    case NOT_STARTED = 'not_started';
    case DISCOVERING = 'discovering';
    case READY       = 'ready';

    /**
     * Create enum from string value (safe).
     *
     * @param string $value The string value.
     * @return self|null Returns enum case or null if invalid.
     */
    public static function try_from_string( string $value ): ?self {
        return self::tryFrom( $value );
    }

    /**
     * Get human-readable label for this status.
     *
     * @return string
     */
    public function label(): string {
        return match ( $this ) {
            self::NOT_STARTED => \__( 'Not Started', 'polylang-ai-autotranslate' ),
            self::DISCOVERING => \__( 'Discovering', 'polylang-ai-autotranslate' ),
            self::READY       => \__( 'Ready', 'polylang-ai-autotranslate' ),
        };
    }

    /**
     * Get detailed message for this status.
     *
     * @return string
     */
    public function message(): string {
        return match ( $this ) {
            self::NOT_STARTED => \__( 'Content discovery pending.', 'polylang-ai-autotranslate' ),
            self::DISCOVERING => \__(
                'Discovering content that needs translation. Please wait.',
                'polylang-ai-autotranslate',
            ),
            self::READY       => \__( 'System is ready for translation runs.', 'polylang-ai-autotranslate' ),
        };
    }

    /**
     * Check if this status indicates the system is ready.
     *
     * @return bool
     */
    public function is_ready(): bool {
        return self::READY === $this;
    }

    /**
     * Check if this status indicates work in progress.
     *
     * @return bool
     */
    public function is_syncing(): bool {
        return self::DISCOVERING === $this;
    }

    /**
     * Check if this status indicates discovering phase.
     *
     * @return bool
     */
    public function is_discovering(): bool {
        return self::DISCOVERING === $this;
    }
}
