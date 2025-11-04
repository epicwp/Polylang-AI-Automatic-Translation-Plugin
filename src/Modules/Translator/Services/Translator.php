<?php
declare(strict_types=1);

namespace PLLAT\Translator\Services;

use PLLAT\Translator\Services\AI_Client;

/**
 * Translation service with hookable prompts and basic tool calling.
 */
class Translator {
    /**
     * Constructor.
     *
     * @param AI_Client $ai_client The AI client.
     */
    public function __construct(
        private AI_Client $ai_client,
    ) {
    }

    /**
     * Translate single text with optional context.
     *
     * @param string $text    Text to translate.
     * @param string $from    Source language code.
     * @param string $to      Target language code.
     * @param array  $context Context data including reference, content_type, content_id.
     * @return string Translated text.
     */
    public function translate_single( string $text, string $from, string $to, array $context = array() ): string {
        return $this->translate( $text, $from, $to, $context );
    }

    /**
     * Build system prompt for translation.
     * Hookable for customization.
     *
     * @param string $from    Source language.
     * @param string $to      Target language.
     * @param array  $context Context data.
     * @return string System prompt.
     */
    protected function build_system_prompt( string $from, string $to, array $context ): string {
        // Build base system prompt. "You are a translator...".
        $prompt = $this->build_system_prompt_base( $from, $to );

        // Add website context if provided.
        $prompt = $this->build_system_prompt_website_context( $prompt, $context );

        // Add custom instructions if provided.
        $prompt = $this->build_system_prompt_instructions( $prompt, $context );

        /**
         * Filter the system prompt for translations.
         *
         * @param string $prompt  System prompt.
         * @param string $from    Source language.
         * @param string $to      Target language.
         * @param array  $context Context data.
         */
        return \apply_filters( 'pllat_translation_system_prompt', $prompt, $from, $to, $context );
    }

    /**
     * Build system prompt base.
     *
     * @param string $from Source language.
     * @param string $to   Target language.
     * @return string System prompt base.
     */
    protected function build_system_prompt_base( string $from, string $to ): string {
        return "You are a professional translator. Translate content from {$from} to {$to} accurately while maintaining the original tone and meaning.";
    }

    /**
     * Build system prompt instructions.
     *
     * @param string $prompt The system prompt.
     * @param array  $context Context data.
     * @return string System prompt.
     */
    protected function build_system_prompt_instructions( string $prompt, array $context ): string {
        if ( isset( $context['instructions'] ) && '' !== $context['instructions'] ) {
            $sanitized_instructions = $this->sanitize_instructions( $context['instructions'] );
            if ( '' !== $sanitized_instructions ) {
                $prompt .= "\n\nAdditional instructions: " . $sanitized_instructions;
            }
        }
        return $prompt;
    }

    /**
     * Build system prompt website context.
     *
     * @param string $prompt The system prompt.
     * @param array  $context Context data.
     * @return string System prompt.
     */
    protected function build_system_prompt_website_context( string $prompt, array $context ): string {
        if ( isset( $context['website_context'] ) && '' !== $context['website_context'] ) {
            $sanitized_context = $this->sanitize_instructions( $context['website_context'] );
            if ( '' !== $sanitized_context ) {
                $prompt .= "\n\nWebsite context: " . $sanitized_context;
            }
        }
        return $prompt;
    }

    /**
     * Core translation method.
     *
     * @param string $text    Text to translate.
     * @param string $from    Source language code.
     * @param string $to      Target language code.
     * @param array  $context Context data.
     * @return string Translated text.
     */
    private function translate( string $text, string $from, string $to, array $context = array() ): string {
        // Build messages for translation.
        $messages = $this->build_messages( $text, $from, $to, $context );

        // Execute chat completion with tool calling.
        $response = $this->ai_client->chat_completion( $messages );

        // Extract translation from response.
        return $this->extract_translation( $response );
    }

    /**
     * Build chat messages for translation.
     * Hookable for customization.
     *
     * @param string $text    Text to translate.
     * @param string $from    Source language.
     * @param string $to      Target language.
     * @param array  $context Context data.
     * @return array Messages array.
     */
    private function build_messages( string $text, string $from, string $to, array $context ): array {
        $system_prompt = $this->build_system_prompt( $from, $to, $context );
        $user_prompt   = $this->build_user_prompt( $text, $context );

        $messages = array(
            array(
                'content' => $system_prompt,
                'role'    => 'system',
            ),
            array(
                'content' => $user_prompt,
                'role'    => 'user',
            ),
        );

        /**
         * Filter translation messages before sending to AI.
         *
         * @param array  $messages Messages array.
         * @param string $text     Text being translated.
         * @param string $from     Source language.
         * @param string $to       Target language.
         * @param array  $context  Context data.
         */
        return \apply_filters( 'pllat_translation_messages', $messages, $text, $from, $to, $context );
    }

    /**
     * Sanitize custom instructions for safe use in prompts.
     *
     * @param string $instructions Raw instructions from user input.
     * @return string Sanitized instructions.
     */
    private function sanitize_instructions( string $instructions ): string {
        // Trim whitespace.
        $instructions = \trim( $instructions );

        if ( '' === $instructions ) {
            return '';
        }

        // Limit length to prevent excessive prompt sizes.
        $max_length = 500;
        if ( \strlen( $instructions ) > $max_length ) {
            $instructions = \substr( $instructions, 0, $max_length );
        }

        // Remove excessive whitespace (multiple spaces/newlines).
        $instructions = \preg_replace( '/\s+/', ' ', $instructions );

        // Strip any HTML tags for security.
        $instructions = \wp_strip_all_tags( $instructions );

        return $instructions;
    }

    /**
     * Build user prompt with text and context.
     *
     * @param string $text    Text to translate.
     * @param array  $context Context data.
     * @return string User prompt.
     */
    private function build_user_prompt( string $text, array $context ): string {
        $prompt = "Translate: {$text}";

        /**
         * Filter the user prompt for translations.
         *
         * @param string $prompt  User prompt.
         * @param string $text    Text being translated.
         * @param array  $context Context data.
         */
        return \apply_filters( 'pllat_translation_user_prompt', $prompt, $text, $context );
    }

    /**
     * Extract translation from AI response.
     *
     * @param array $response AI response.
     * @return string Translated text.
     * @throws \Exception If response is invalid or empty.
     */
    private function extract_translation( array $response ): string {
        // Validate response structure.
        if ( ! $this->is_valid_ai_response( $response ) ) {
            throw new \Exception( 'Invalid AI response structure' );
        }

        // Extract translation from response.
        $content = $response['choices'][0]['message']['content'] ?? '';

        // Validate content.
        $validated_content = $this->validate_translation_content( $content );

        return $validated_content;
    }

    /**
     * Validate AI response structure.
     *
     * @param array $response The AI response.
     * @return bool True if response is valid.
     */
    private function is_valid_ai_response( array $response ): bool {
        return isset( $response['choices'] )
            && \is_array( $response['choices'] )
            && \count( $response['choices'] ) > 0
            && isset( $response['choices'][0]['message']['content'] );
    }

    /**
     * Validate and clean translation content.
     *
     * @param string $content The translation content.
     * @return string The validated content.
     * @throws \Exception If content is invalid.
     */
    private function validate_translation_content( string $content ): string {
        $content = \trim( $content );

        // Check for empty content.
        if ( '' === $content ) {
            throw new \Exception( 'AI returned empty translation' );
        }

        // Check for suspiciously long content (potential hallucination).
        // Default 100,000 chars (~70 pages) - still prevents extreme hallucinations while allowing large content.
        $max_length = \apply_filters( 'pllat_max_translation_length', 100000 );
        if ( \strlen( $content ) > $max_length ) {
            throw new \Exception( 'Translation exceeds maximum length (' . $max_length . ' characters)' );
        }

        /**
         * Filter to validate translation content.
         *
         * @param string $content The translation content.
         * @return string The validated content.
         * @throws \Exception If content is invalid.
         */
        return \apply_filters( 'pllat_validate_translation_content', $content );
    }
}
