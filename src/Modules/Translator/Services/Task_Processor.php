<?php
/**
 * Task_Processor class file.
 *
 * @package Polylang AI Automatic Translation
 * @subpackage Translator
 */

declare(strict_types=1);

namespace PLLAT\Translator\Services;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
use PLLAT\Translator\Models\Task;

/**
 * Processes translation tasks by selecting the appropriate translator.
 *
 * Factory/dispatcher pattern that determines which translator to use
 * based on task type (text, JSON, custom) via filter hooks.
 *
 * This service is only available in BYOK mode when API keys are configured.
 */
class Task_Processor {
    /**
     * Constructor.
     *
     * @param Translator|null $text_translator Standard text translator.
     */
    public function __construct(
        private Translator $text_translator,
    ) {
    }

    /**
     * Process a translation task.
     *
     * Determines translator type via filter hook and delegates to appropriate translator.
     *
     * @param Task   $task    The task to process.
     * @param string $from    Source language code.
     * @param string $to      Target language code.
     * @param array  $context Additional context.
     * @return string Translated text.
     * @throws \Exception If translator not available or translation fails.
     */
    public function process_task( Task $task, string $from, string $to, array $context = array() ): string {
        // Determine translator type via filter hook.
        $translator_type = $this->get_translator_type( $task );

        // Get appropriate translator.
        $translator = $this->get_translator( $translator_type );

        // Build context with task info.
        $translation_context = \array_merge(
            $context,
            array(
                'content_id'   => $context['content_id'] ?? 0,
                'content_type' => $context['content_type'] ?? '',
                'reference'    => $task->get_reference(),
                'task_id'      => $task->get_id(),
            ),
        );

        // Translate using selected translator.
        return $translator->translate_single(
            $task->get_value(),
            $from,
            $to,
            $translation_context,
        );
    }

    /**
     * Get translator type for a task via filter hook.
     *
     * @param Task $task The task.
     * @return string Translator type ('text', 'elementor', 'json', etc.).
     */
    private function get_translator_type( Task $task ): string {
        /**
         * Filter the translator type for a task.
         *
         * Allows integrations to specify custom translator types.
         * For example, Elementor integration changes 'text' to 'elementor'
         * for _elementor_data meta fields.
         *
         * @param string $type Default type 'text'.
         * @param Task   $task The task being processed.
         * @return string Translator type.
         */
        return \apply_filters( 'pllat_task_translator_type', 'text', $task );
    }

    /**
     * Get translator instance by type.
     *
     * @param string $type Translator type ('text' or 'json').
     */
    private function get_translator( string $type ): Translator {
        return match ( $type ) {
            'text' => $this->text_translator,
            default => $this->text_translator, // Safe fallback for unknown types.
        };
    }
}
