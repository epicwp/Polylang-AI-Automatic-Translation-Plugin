# Translation Workflow

Complete documentation of the translation workflow in Polylang Automatic Translation with AI (PLLAT).

**Related docs:**
- [Project Architecture](./project_architecture.md)
- [Database Schema](./database_schema.md)
- [XWP DI Framework](./xwp_di_framework.md)

---

## Table of Contents

1. [Overview](#overview)
2. [Bulk Translation Workflow](#bulk-translation-workflow)
3. [Single Translation Workflow](#single-translation-workflow)
4. [Auto-Translation Workflow](#auto-translation-workflow)
5. [Translation Processing](#translation-processing)
6. [AI Provider Integration](#ai-provider-integration)
7. [Error Handling](#error-handling)
8. [Status Transitions](#status-transitions)

---

## Overview

PLLAT supports **three translation workflows**:

1. **Bulk Translation:** Translate multiple content items in background
2. **Single Translation:** Translate one content item on-demand
3. **Auto-Translation:** Automatically translate when content is created/updated

All workflows use the same core translation engine but differ in job creation and execution timing.

---

## Bulk Translation Workflow

**Purpose:** Translate large batches of content in the background via Action Scheduler.

### Step-by-Step Flow

```
┌─────────────────────────────────────────────────────────┐
│ 1. User Creates Translation Run (Dashboard)             │
└────────────────┬────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────┐
│ 2. Translation_Run_Service::create_run($config)         │
│    - Validates configuration                             │
│    - Creates Run record (status: pending)                │
│    - Queries content via Post_Query_Service              │
│    - Creates Job records for each item                   │
│    - Schedules Action Scheduler recurring job            │
└────────────────┬────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────┐
│ 3. Action Scheduler Executes (Every 60s)                │
│    Hook: pllat_process_translation_run                   │
└────────────────┬────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────┐
│ 4. Run_Processor_Handler::process_run()                 │
│    - Fetches active runs                                 │
│    - For each run:                                       │
│      └─> Job_Processor::process_jobs($run_id, $limit)   │
└────────────────┬────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────┐
│ 5. Job_Processor::process_jobs($run_id, $limit)         │
│    - Fetches next batch of pending jobs (default: 5)    │
│    - For each job:                                       │
│      └─> process_single_job($job)                       │
└────────────────┬────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────┐
│ 6. Job_Processor::process_single_job($job)              │
│    - Updates job status: translating                     │
│    - Fetches content via Content_Service                 │
│    - Creates Task records for each field                 │
│    - Calls Translator::translate($tasks, $context)       │
│    - Updates job status: applying                        │
│    - Applies translations via Content_Service            │
│    - Updates job status: completed                       │
└────────────────┬────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────┐
│ 7. Run Progress Update                                  │
│    - Calculates completed/failed/pending counts          │
│    - Updates run status if all jobs finished             │
│    - Dashboard polls for updates via REST API            │
└─────────────────────────────────────────────────────────┘
```

### Code Implementation

**1. Create Run (Dashboard UI):**

```jsx
// React component: TranslationDashboard.jsx
const createRun = async (config) => {
  const response = await axios.post('/wp-json/pllat/v1/dashboard/runs', {
    content_type: 'post',
    post_types: ['post', 'page'],
    lang_from: 'en',
    lang_to: ['es', 'fr'],
    force: false,
    custom_instructions: '...',
  });
  return response.data; // { run_id: 123 }
};
```

**2. Create Run (Backend):**

```php
// Translation_Run_Service.php
public function create_run( array $config ): int {
    // Validate configuration
    $this->validate_config( $config );

    // Create run record
    $run_id = $this->run_repository->create( array(
        'status' => 'pending',
        'config' => wp_json_encode( $config ),
        'created_at' => time(),
    ) );

    // Query content to translate
    $items = $this->post_query_service->query( $config );

    // Create jobs for each item
    foreach ( $items as $item ) {
        foreach ( $config['lang_to'] as $target_lang ) {
            $this->job_repository->create( array(
                'run_id'       => $run_id,
                'type'         => 'post',
                'content_type' => $item->post_type,
                'id_from'      => $item->ID,
                'lang_from'    => $config['lang_from'],
                'lang_to'      => $target_lang,
                'status'       => 'pending',
                'created_at'   => time(),
            ) );
        }
    }

    // Schedule Action Scheduler job
    as_schedule_recurring_action(
        time(),
        60, // Every 60 seconds
        'pllat_process_translation_run',
        array(),
        'pllat-translation'
    );

    return $run_id;
}
```

**3. Process Run (Background Job):**

```php
// Run_Processor_Handler.php
#[Action( tag: 'pllat_process_translation_run' )]
public function process_run(): void {
    // Get active runs
    $runs = $this->run_repository->get_active_runs();

    foreach ( $runs as $run ) {
        // Update run status
        $this->run_repository->update( $run->id, array(
            'status' => 'running',
            'started_at' => time(),
            'last_heartbeat' => time(),
        ) );

        // Process jobs
        $this->job_processor->process_jobs( $run->id, 5 );

        // Check if run is complete
        $stats = $this->job_stats_repository->get_run_stats( $run->id );
        if ( 0 === $stats->in_progress ) {
            $status = $stats->failed > 0 ? 'failed' : 'completed';
            $this->run_repository->update( $run->id, array(
                'status' => $status,
            ) );
        }
    }
}
```

**4. Process Jobs (Batch):**

```php
// Job_Processor.php
public function process_jobs( int $run_id, int $limit = 5 ): void {
    // Fetch next batch of pending jobs
    $jobs = $this->job_repository->get_pending_jobs( $run_id, $limit );

    foreach ( $jobs as $job ) {
        try {
            $this->process_single_job( $job );
        } catch ( \Exception $e ) {
            // Update job status to failed
            $this->job_repository->update( $job->id, array(
                'status' => 'failed',
                'completed_at' => time(),
            ) );

            // Log error
            $this->logger->error( 'Job failed', array(
                'job_id' => $job->id,
                'error'  => $e->getMessage(),
            ) );
        }
    }
}
```

**5. Process Single Job:**

```php
// Job_Processor.php
private function process_single_job( object $job ): void {
    // Update status: translating
    $this->job_repository->update( $job->id, array(
        'status'     => 'translating',
        'started_at' => time(),
    ) );

    // Get content service for content type
    $content_service = $this->content_service_factory->get_service( $job->type );

    // Fetch content data
    $content_data = $content_service->get_content_data( $job->id_from );

    // Create tasks for each field
    foreach ( $content_data as $reference => $value ) {
        $this->task_repository->create( array(
            'job_id'    => $job->id,
            'reference' => $reference,
            'value'     => $value,
            'status'    => 'pending',
        ) );
    }

    // Translate tasks
    $context = array(
        'lang_from' => $job->lang_from,
        'lang_to'   => $job->lang_to,
        'custom_instructions' => $this->get_custom_instructions( $job ),
    );
    $this->translator->translate_job( $job->id, $context );

    // Update status: applying
    $this->job_repository->update( $job->id, array(
        'status' => 'applying',
    ) );

    // Get translated tasks
    $translated_tasks = $this->task_repository->get_completed_tasks( $job->id );

    // Apply translations
    $translations = array();
    foreach ( $translated_tasks as $task ) {
        $translations[ $task->reference ] = $task->translation;
    }

    $translated_id = $content_service->apply_translation(
        $job->id_from,
        $job->lang_to,
        $translations
    );

    // Update job status: completed
    $this->job_repository->update( $job->id, array(
        'status'       => 'completed',
        'id_to'        => $translated_id,
        'completed_at' => time(),
    ) );
}
```

---

## Single Translation Workflow

**Purpose:** Translate one content item immediately (synchronous execution).

### Step-by-Step Flow

```
┌─────────────────────────────────────────────────────────┐
│ 1. User Clicks "Translate" Button (Post Edit Screen)    │
└────────────────┬────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────┐
│ 2. REST API: POST /pllat/v1/single-translator/translate │
│    Payload: { post_id, lang_to, force }                 │
└────────────────┬────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────┐
│ 3. Single_Translation_Service::translate()              │
│    - Creates Job record                                  │
│    - Executes translation immediately (not async)        │
│    - Returns translated post ID                          │
└────────────────┬────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────┐
│ 4. React UI Redirects to Translated Post                │
└─────────────────────────────────────────────────────────┘
```

### Code Implementation

**1. Trigger Translation (React):**

```jsx
// SingleTranslator.jsx
const translatePost = async (postId, targetLang) => {
  const response = await axios.post(
    '/wp-json/pllat/v1/single-translator/translate',
    {
      post_id: postId,
      lang_to: targetLang,
      force: false,
    }
  );

  const { job_id, translated_post_id } = response.data;

  // Redirect to translated post
  window.location.href = `/wp-admin/post.php?post=${translated_post_id}&action=edit`;
};
```

**2. Execute Translation (Backend):**

```php
// Single_Translation_Service.php
public function translate( int $post_id, string $lang_to, bool $force = false ): array {
    // Check if translation already exists
    $translated_post_id = pll_get_post( $post_id, $lang_to );
    if ( $translated_post_id && ! $force ) {
        return array(
            'success'            => false,
            'message'            => 'Translation already exists',
            'translated_post_id' => $translated_post_id,
        );
    }

    // Get source language
    $lang_from = pll_get_post_language( $post_id );

    // Create job record
    $job_id = $this->job_repository->create( array(
        'type'         => 'post',
        'content_type' => get_post_type( $post_id ),
        'id_from'      => $post_id,
        'lang_from'    => $lang_from,
        'lang_to'      => $lang_to,
        'status'       => 'pending',
        'created_at'   => time(),
    ) );

    // Execute translation synchronously
    $this->job_processor->process_single_job_by_id( $job_id );

    // Get translated post ID
    $job = $this->job_repository->get( $job_id );
    $translated_post_id = $job->id_to;

    return array(
        'success'            => true,
        'job_id'             => $job_id,
        'translated_post_id' => $translated_post_id,
    );
}
```

---

## Auto-Translation Workflow

**Purpose:** Automatically translate content when it's created or updated.

### Step-by-Step Flow

```
┌─────────────────────────────────────────────────────────┐
│ 1. User Saves Post (Edit Screen)                        │
└────────────────┬────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────┐
│ 2. WordPress Fires save_post Hook                       │
└────────────────┬────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────┐
│ 3. Content_Change_Handler::on_post_save($post_id)       │
│    - Check if auto-translation enabled                   │
│    - Check if post is in default language                │
│    - For each target language:                           │
│      └─> Create job and enqueue translation              │
└────────────────┬────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────┐
│ 4. Job Processed via Action Scheduler (async)           │
│    (Same as bulk translation workflow)                   │
└─────────────────────────────────────────────────────────┘
```

### Code Implementation

**1. Detect Content Change:**

```php
// Content_Change_Handler.php
#[Action( tag: 'save_post', priority: 20, accepted_args: 3 )]
public function on_post_save( int $post_id, \WP_Post $post, bool $update ): void {
    // Skip if not enabled
    if ( ! $this->settings_service->get( 'auto_translate_on_save', false ) ) {
        return;
    }

    // Skip if not default language
    $post_lang = pll_get_post_language( $post_id );
    if ( $post_lang !== pll_default_language() ) {
        return;
    }

    // Get target languages
    $target_languages = pll_languages_list( array( 'fields' => 'slug' ) );
    $target_languages = array_diff( $target_languages, array( $post_lang ) );

    // Create jobs for each target language
    foreach ( $target_languages as $target_lang ) {
        // Check if job already exists
        $existing_job = $this->job_repository->get_existing_job(
            'post',
            $post_id,
            $target_lang,
            array( 'pending', 'translating', 'applying' )
        );

        if ( $existing_job ) {
            continue; // Skip if already processing
        }

        // Create job
        $job_id = $this->job_repository->create( array(
            'type'         => 'post',
            'content_type' => $post->post_type,
            'id_from'      => $post_id,
            'lang_from'    => $post_lang,
            'lang_to'      => $target_lang,
            'status'       => 'pending',
            'created_at'   => time(),
        ) );

        // Enqueue job (will be processed by Action Scheduler)
        as_enqueue_async_action(
            'pllat_process_single_job',
            array( 'job_id' => $job_id ),
            'pllat-translation'
        );
    }
}
```

---

## Translation Processing

**Core translation logic shared across all workflows.**

### Task Translation Flow

```php
// Translator.php
public function translate_job( int $job_id, array $context ): void {
    // Get pending tasks for job
    $tasks = $this->task_repository->get_pending_tasks( $job_id );

    // Group tasks by batch size (default: 10)
    $batches = array_chunk( $tasks, 10 );

    foreach ( $batches as $batch ) {
        // Build translation request
        $request = $this->build_translation_request( $batch, $context );

        // Call AI provider
        $response = $this->ai_client->translate( $request );

        // Parse and save translations
        foreach ( $batch as $i => $task ) {
            $translation = $response->translations[ $i ] ?? null;

            if ( $translation ) {
                $this->task_repository->update( $task->id, array(
                    'translation' => $translation,
                    'status'      => 'completed',
                ) );
            } else {
                $this->task_repository->update( $task->id, array(
                    'status'   => 'failed',
                    'attempts' => $task->attempts + 1,
                    'issue'    => 'Translation not received',
                ) );
            }
        }
    }
}
```

### Translation Request Format

```php
private function build_translation_request( array $tasks, array $context ): array {
    return array(
        'model'       => $this->settings_service->get( 'ai_model' ),
        'temperature' => 0.3,
        'messages'    => array(
            array(
                'role'    => 'system',
                'content' => $this->build_system_prompt( $context ),
            ),
            array(
                'role'    => 'user',
                'content' => $this->build_user_prompt( $tasks, $context ),
            ),
        ),
    );
}

private function build_system_prompt( array $context ): string {
    $prompt = "You are a professional translator. Translate the following content from {$context['lang_from']} to {$context['lang_to']}.";

    if ( ! empty( $context['custom_instructions'] ) ) {
        $prompt .= "\n\nAdditional instructions:\n{$context['custom_instructions']}";
    }

    return $prompt;
}

private function build_user_prompt( array $tasks, array $context ): string {
    $json_data = array();
    foreach ( $tasks as $task ) {
        $json_data[ $task->reference ] = $task->value;
    }

    return "Translate the following JSON object. Return only valid JSON with the same structure:\n\n" . wp_json_encode( $json_data, JSON_PRETTY_PRINT );
}
```

---

## AI Provider Integration

### Provider Architecture

```
AI_Provider_Registry (singleton)
├── register_provider('claude', Claude_Provider::class)
├── register_provider('openai', OpenAI_Provider::class)
├── register_provider('gemini', Gemini_Provider::class)
└── register_provider('openrouter', OpenRouter_Provider::class)

AI_Provider_Factory
└── create_from_settings($settings) → AI_Provider instance
    └── get_client() → AI_Client (OpenAI Client)
```

### Provider Implementation

```php
// Claude_Provider.php
class Claude_Provider extends AI_Provider {
    public function get_client(): AI_Client {
        $api_key = $this->settings_service->get( 'claude_api_key' );

        return OpenAI::factory()
            ->withApiKey( $api_key )
            ->withBaseUri( 'https://api.anthropic.com/v1' )
            ->withHttpHeader( 'anthropic-version', '2023-06-01' )
            ->make();
    }

    public function get_default_model(): string {
        return 'claude-3-5-sonnet-20241022';
    }
}
```

---

## Error Handling

### Task-Level Errors

**Retry Logic:**

```php
// Task_Repository.php
public function retry_failed_tasks( int $job_id, int $max_attempts = 3 ): void {
    $failed_tasks = $this->get_failed_tasks( $job_id );

    foreach ( $failed_tasks as $task ) {
        if ( $task->attempts < $max_attempts ) {
            $this->update( $task->id, array(
                'status'  => 'pending', // Reset to pending
                'issue'   => null,
            ) );
        }
    }
}
```

**Error Types:**
- **API Error:** Rate limit, timeout, invalid API key
- **Parse Error:** AI returned invalid JSON
- **Network Error:** Connection failed
- **Validation Error:** Translation doesn't match expected format

### Job-Level Errors

**Failure Conditions:**
- All tasks failed after max retries
- Fatal error during job processing
- Content no longer exists

**Error Reporting:**

```php
// Job fails if any task fails
$failed_tasks = $this->task_repository->get_failed_tasks( $job_id );
if ( count( $failed_tasks ) > 0 ) {
    $this->job_repository->update( $job_id, array(
        'status'       => 'failed',
        'completed_at' => time(),
    ) );

    // Log error summary
    $this->logger->error( 'Job failed', array(
        'job_id'       => $job_id,
        'failed_tasks' => count( $failed_tasks ),
        'errors'       => array_map( fn( $t ) => $t->issue, $failed_tasks ),
    ) );
}
```

---

## Status Transitions

### Run Status Flow

```
pending → running → completed
              ├──→ paused → running
              ├──→ cancelled
              └──→ failed
```

### Job Status Flow

```
pending → translating → applying → completed
              ├────────────────────→ failed
              └────────────────────→ cancelled
```

### Task Status Flow

```
pending → translating → completed
              └────────→ failed → pending (retry)
                                 └──→ failed (max retries)
```

---

**Last Updated:** 2025-10-24
**Version:** 2.2.0
**Status:** ✅ Production-Ready
