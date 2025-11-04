<?php

namespace PLLAT\Translator\Handlers;

use PLLAT\Content\Services\Content_Service;
use PLLAT\Settings\Services\Settings_Service;
use PLLAT\Translator\Models\Job;
use XWP\DI\Decorators\Action;
use XWP\DI\Decorators\Filter;
use XWP\DI\Decorators\Handler;

/**
 * Translator handler.
 */
#[Handler( tag: 'init', priority: 10 )]
class Translator_Handler {
    /**
     * Constructor.
     *
     * @param Settings_Service $settings_service The settings service.
     */
    public function __construct(
        private Settings_Service $settings_service,
        private Content_Service $content_service,
    ) {
    }

    /**
     * Filter into the system prompt for translations by adding website context from settings.
     *
     * @param string $prompt    System prompt.
     * @return string System prompt.
     */
    #[Filter( tag: 'pllat_translation_system_prompt', priority: 10 )]
    public function filter_translation_system_prompt( string $prompt ): string {
        return $this->add_system_prompt_context( $prompt );
    }

    /**
     * Processes a job before it is completed.
     *
     * @param Job $job The job to process.
     * @return void
     */
    #[Action( tag: 'pllat_before_job_completion', priority: 10 )]
    public function before_job_completion( Job $job ): void {
        $this->content_service->process_job( $job );
    }

    /**
     * Add website context to system prompt.
     *
     * @param string $prompt    System prompt.
     * @return string System prompt.
     */
    private function add_system_prompt_context( string $prompt ): string {
        $website_context = $this->settings_service->get_website_ai_context();
        if ( '' === $website_context ) {
            return $prompt;
        }
        return $prompt . "\n\nAdditional context provided by the owner of this website: " . $website_context . "\n\n";
    }
}
