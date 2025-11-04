# Database Schema

Complete database schema documentation for Polylang Automatic Translation with AI (PLLAT).

**Related docs:**
- [Project Architecture](./project_architecture.md)
- [Translation Workflow](./translation_workflow.md)

---

## Table of Contents

1. [Overview](#overview)
2. [Table Relationships](#table-relationships)
3. [Table Schemas](#table-schemas)
4. [Indexes](#indexes)
5. [Migrations](#migrations)
6. [Querying Patterns](#querying-patterns)

---

## Overview

PLLAT uses **three custom database tables** to manage translation workflows:

1. **`wp_pllat_bulk_runs`** - Bulk translation campaigns
2. **`wp_pllat_jobs`** - Individual content item translations
3. **`wp_pllat_tasks`** - Individual field translations within a job

**Hierarchy:**
```
Run (1) ──> (N) Jobs (1) ──> (N) Tasks
```

**Current Version:** 2.2.0

---

## Table Relationships

```
┌─────────────────────────────────┐
│     wp_pllat_bulk_runs          │
│  (Translation Campaigns)         │
├─────────────────────────────────┤
│ id (PK)                          │
│ status (pending/running/...)     │
│ config (JSON)                    │
│ created_at                       │
│ started_at                       │
│ last_heartbeat                   │
└────────┬────────────────────────┘
         │
         │ 1:N
         │
         ▼
┌─────────────────────────────────┐
│       wp_pllat_jobs              │
│  (Content Item Translations)     │
├─────────────────────────────────┤
│ id (PK)                          │
│ run_id (FK → runs.id)            │  ← Links to Run
│ type (post/term)                 │
│ content_type (post_type/taxonomy)│
│ id_from (source content ID)      │
│ id_to (target content ID)        │
│ lang_from                        │
│ lang_to                          │
│ status (pending/running/...)     │
│ created_at                       │
│ started_at                       │
│ completed_at                     │
└────────┬────────────────────────┘
         │
         │ 1:N
         │
         ▼
┌─────────────────────────────────┐
│       wp_pllat_tasks             │
│   (Field-Level Translations)     │
├─────────────────────────────────┤
│ id (PK)                          │
│ job_id (FK → jobs.id)            │  ← Links to Job
│ reference (field identifier)     │
│ value (original text)            │
│ translation (translated text)    │
│ status (pending/running/...)     │
│ attempts (retry count)           │
│ issue (error message)            │
└─────────────────────────────────┘
```

---

## Table Schemas

### 1. `wp_pllat_bulk_runs`

**Purpose:** Stores bulk translation campaign metadata.

**Schema:**

| Column          | Type              | Null | Default | Description                                    |
|-----------------|-------------------|------|---------|------------------------------------------------|
| `id`            | BIGINT UNSIGNED   | NO   | AUTO    | Primary key                                    |
| `status`        | VARCHAR(20)       | NO   | pending | Run status (see RunStatus enum)                |
| `config`        | LONGTEXT          | NO   | -       | JSON configuration (filters, settings, etc.)   |
| `created_at`    | BIGINT UNSIGNED   | NO   | 0       | Unix timestamp (run creation time)             |
| `started_at`    | BIGINT UNSIGNED   | NO   | 0       | Unix timestamp (processing start time)         |
| `last_heartbeat`| BIGINT UNSIGNED   | NO   | 0       | Unix timestamp (last activity indicator)       |

**Status Values (RunStatus):**
- `pending` - Created but not started
- `running` - Currently processing jobs
- `paused` - Temporarily stopped
- `completed` - All jobs finished successfully
- `cancelled` - Manually cancelled by user
- `failed` - Fatal error occurred

**Config JSON Structure:**
```json
{
  "content_type": "post",          // "post" or "term"
  "post_types": ["post", "page"],  // Filter by post types (if content_type=post)
  "taxonomies": ["category"],      // Filter by taxonomies (if content_type=term)
  "lang_from": "en",               // Source language code
  "lang_to": ["es", "fr"],         // Target language codes
  "force": false,                  // Re-translate existing translations
  "custom_instructions": "...",    // Optional AI instructions
  "batch_size": 5                  // Jobs per batch
}
```

**Indexes:**
- PRIMARY KEY: `id`
- KEY: `status`

---

### 2. `wp_pllat_jobs`

**Purpose:** Stores translation jobs for individual content items.

**Schema:**

| Column        | Type              | Null | Default | Description                                           |
|---------------|-------------------|------|---------|-------------------------------------------------------|
| `id`          | BIGINT UNSIGNED   | NO   | AUTO    | Primary key                                           |
| `type`        | VARCHAR(20)       | NO   | -       | Content type: `post` or `term`                        |
| `content_type`| VARCHAR(50)       | NO   | ''      | Post type (e.g., `post`, `page`) or taxonomy name     |
| `id_from`     | BIGINT UNSIGNED   | NO   | -       | Source content ID (post_id or term_id)                |
| `id_to`       | BIGINT UNSIGNED   | YES  | NULL    | Target content ID (translated post/term, if exists)   |
| `lang_from`   | VARCHAR(10)       | NO   | -       | Source language code (e.g., `en`, `es`)               |
| `lang_to`     | VARCHAR(10)       | NO   | -       | Target language code                                  |
| `run_id`      | BIGINT UNSIGNED   | YES  | NULL    | Foreign key to `wp_pllat_bulk_runs.id` (NULL = single)|
| `status`      | VARCHAR(20)       | NO   | pending | Job status (see JobStatus enum)                       |
| `created_at`  | BIGINT UNSIGNED   | NO   | 0       | Unix timestamp (job creation time)                    |
| `started_at`  | BIGINT UNSIGNED   | NO   | 0       | Unix timestamp (translation start time)               |
| `completed_at`| BIGINT UNSIGNED   | NO   | 0       | Unix timestamp (translation completion time)          |

**Status Values (JobStatus):**
- `pending` - Waiting to be processed
- `translating` - Currently translating tasks
- `applying` - Applying translations to content
- `completed` - Successfully completed
- `failed` - Error occurred during translation
- `cancelled` - Manually cancelled

**Job Types:**
- `post` - WordPress post (post, page, CPT)
- `term` - Taxonomy term (category, tag, custom taxonomy)

**Indexes:**
- PRIMARY KEY: `id`
- KEY: `run_id` (for run-based queries)
- KEY: `id_from` (for source content lookups)
- KEY: `id_to` (for target content lookups)
- KEY: `status` (for status filtering)
- COMPOSITE KEY: `idx_stats_lookup` (`type`, `content_type`, `lang_from`, `lang_to`, `status`)
- COMPOSITE KEY: `idx_item_status` (`type`, `id_from`, `lang_to`, `status`)
- COMPOSITE KEY: `idx_run_processing` (`run_id`, `status`, `id`)

---

### 3. `wp_pllat_tasks`

**Purpose:** Stores field-level translation tasks within a job.

**Schema:**

| Column        | Type              | Null | Default | Description                                        |
|---------------|-------------------|------|---------|----------------------------------------------------|
| `id`          | BIGINT UNSIGNED   | NO   | AUTO    | Primary key                                        |
| `job_id`      | BIGINT UNSIGNED   | NO   | -       | Foreign key to `wp_pllat_jobs.id`                  |
| `reference`   | VARCHAR(191)      | NO   | -       | Field identifier (e.g., `post_title`, `meta:_yoast_wpseo_title`) |
| `value`       | LONGTEXT          | YES  | NULL    | Original text to translate                         |
| `translation` | LONGTEXT          | YES  | NULL    | Translated text (populated after translation)      |
| `status`      | VARCHAR(20)       | NO   | pending | Task status (see TaskStatus enum)                  |
| `attempts`    | INT UNSIGNED      | NO   | 0       | Number of translation attempts (for retry logic)   |
| `issue`       | TEXT              | YES  | NULL    | Error message (if translation failed)              |

**Status Values (TaskStatus):**
- `pending` - Waiting to be translated
- `translating` - Currently being sent to AI
- `completed` - Successfully translated
- `failed` - Translation failed after retries
- `skipped` - Skipped (empty value, excluded field, etc.)

**Reference Format:**

| Reference Type       | Format                          | Example                              |
|----------------------|---------------------------------|--------------------------------------|
| Core Field           | `{field_name}`                  | `post_title`, `post_content`         |
| Meta Field           | `meta:{meta_key}`               | `meta:_yoast_wpseo_title`            |
| JSON Field           | `json:{meta_key}.{json_path}`   | `json:_elementor_data.0.settings.title` |
| Term Field           | `term_name`, `term_description` | `term_name`                          |

**Indexes:**
- PRIMARY KEY: `id`
- KEY: `job_id` (for job-based queries)
- KEY: `status` (for status filtering)

---

## Indexes

### Performance Optimization

PLLAT uses **composite indexes** for efficient querying:

#### 1. `idx_stats_lookup` (Jobs Table)

**Columns:** `type`, `content_type`, `lang_from`, `lang_to`, `status`

**Purpose:** Fast statistics queries for dashboard

**Example Query:**
```sql
-- Get translation stats for all post types from English to Spanish
SELECT content_type, status, COUNT(*) as count
FROM wp_pllat_jobs
WHERE type = 'post'
  AND lang_from = 'en'
  AND lang_to = 'es'
GROUP BY content_type, status;
```

#### 2. `idx_item_status` (Jobs Table)

**Columns:** `type`, `id_from`, `lang_to`, `status`

**Purpose:** Check if content item already has translation job

**Example Query:**
```sql
-- Check if post ID 123 has pending/running job for Spanish translation
SELECT id, status FROM wp_pllat_jobs
WHERE type = 'post'
  AND id_from = 123
  AND lang_to = 'es'
  AND status IN ('pending', 'translating')
LIMIT 1;
```

#### 3. `idx_run_processing` (Jobs Table)

**Columns:** `run_id`, `status`, `id`

**Purpose:** Efficiently fetch next batch of jobs to process

**Example Query:**
```sql
-- Get next 5 pending jobs for run ID 42
SELECT id FROM wp_pllat_jobs
WHERE run_id = 42
  AND status = 'pending'
ORDER BY id ASC
LIMIT 5;
```

---

## Migrations

**Version History:**

| Version | Date       | Changes                                      |
|---------|------------|----------------------------------------------|
| 1.0.0   | Initial    | Created `runs`, `jobs`, `tasks` tables       |
| 2.0.0   | -          | Added `content_type` column, backfilled data |
| 2.2.0   | 2025-10-24 | Added `id_to` column, cleared existing data  |

### Migration 2.0.0: Content Type Column

**Added:** `content_type` column to `wp_pllat_jobs`

**Purpose:** Store post type or taxonomy name for filtering

**Backfill Logic:**
```sql
-- Backfill post types
UPDATE wp_pllat_jobs j
INNER JOIN wp_posts p ON j.type = 'post' AND j.id_from = p.ID
SET j.content_type = p.post_type
WHERE j.type = 'post' AND j.content_type = '';

-- Backfill taxonomies
UPDATE wp_pllat_jobs j
INNER JOIN wp_term_taxonomy tt ON j.type = 'term' AND j.id_from = tt.term_id
SET j.content_type = tt.taxonomy
WHERE j.type = 'term' AND j.content_type = '';
```

### Migration 2.2.0: ID To Column

**Added:** `id_to` column to `wp_pllat_jobs`

**Purpose:** Store target content ID for bidirectional job cleanup

**Why:** Prevents duplicate jobs when content is updated (e.g., EN post 123 → ES post 456)

**Note:** No backfill performed; existing data was cleared (plugin not yet in production)

**Implementation:** `src/Common/Installer/Installer.php:156`

---

## Querying Patterns

### Common Queries

#### 1. Get Run Progress

```php
// Get run statistics
global $wpdb;
$run_id = 42;

$stats = $wpdb->get_row( $wpdb->prepare(
    "SELECT
        COUNT(*) as total_jobs,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
        SUM(CASE WHEN status IN ('pending', 'translating') THEN 1 ELSE 0 END) as in_progress
    FROM {$wpdb->prefix}pllat_jobs
    WHERE run_id = %d",
    $run_id
) );
```

#### 2. Get Next Batch of Jobs

```php
// Fetch next 5 pending jobs for processing
$jobs = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}pllat_jobs
    WHERE run_id = %d AND status = 'pending'
    ORDER BY id ASC
    LIMIT 5",
    $run_id
) );
```

#### 3. Check Existing Job for Content

```php
// Check if post 123 already has translation job to Spanish
$existing_job = $wpdb->get_var( $wpdb->prepare(
    "SELECT id FROM {$wpdb->prefix}pllat_jobs
    WHERE type = 'post'
      AND (id_from = %d OR id_to = %d)
      AND lang_to = %s
      AND status IN ('pending', 'translating', 'applying')
    LIMIT 1",
    $post_id,
    $post_id,
    $target_lang
) );
```

#### 4. Get Dashboard Statistics

```php
// Get translation stats grouped by content type and language pair
$stats = $wpdb->get_results(
    "SELECT
        content_type,
        lang_from,
        lang_to,
        status,
        COUNT(*) as count
    FROM {$wpdb->prefix}pllat_jobs
    WHERE type = 'post'
    GROUP BY content_type, lang_from, lang_to, status"
);
```

#### 5. Get Tasks for Job

```php
// Get all tasks for job ID 123
$tasks = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}pllat_tasks
    WHERE job_id = %d
    ORDER BY id ASC",
    $job_id
) );
```

#### 6. Cleanup Old Jobs

```php
// Delete completed jobs older than 30 days
$wpdb->query( $wpdb->prepare(
    "DELETE FROM {$wpdb->prefix}pllat_jobs
    WHERE status = 'completed'
      AND completed_at < %d",
    time() - (30 * DAY_IN_SECONDS)
) );

// Cascade delete orphaned tasks
$wpdb->query(
    "DELETE t FROM {$wpdb->prefix}pllat_tasks t
    LEFT JOIN {$wpdb->prefix}pllat_jobs j ON t.job_id = j.id
    WHERE j.id IS NULL"
);
```

---

## Repository Pattern

PLLAT uses **Repository classes** for database access. **Never query tables directly.**

**Available Repositories:**

| Repository              | File                                     | Purpose                              |
|-------------------------|------------------------------------------|--------------------------------------|
| `Run_Repository`        | `Translator/Repositories/Run_Repository.php`       | CRUD for `wp_pllat_bulk_runs`        |
| `Job_Repository`        | `Translator/Repositories/Job_Repository.php`       | CRUD for `wp_pllat_jobs`             |
| `Task_Repository`       | `Translator/Repositories/Task_Repository.php`      | CRUD for `wp_pllat_tasks`            |
| `Job_Stats_Repository`  | `Translator/Repositories/Job_Stats_Repository.php` | Statistics and aggregation queries   |

**Example Usage:**

```php
use PLLAT\Translator\Repositories\Job_Repository;

// Get repository via DI
public function __construct(
    private Job_Repository $job_repository
) {}

// Create new job
$job_id = $this->job_repository->create( array(
    'type'         => 'post',
    'content_type' => 'post',
    'id_from'      => 123,
    'lang_from'    => 'en',
    'lang_to'      => 'es',
    'run_id'       => 42,
    'status'       => 'pending',
) );

// Update job status
$this->job_repository->update( $job_id, array(
    'status'       => 'completed',
    'completed_at' => time(),
) );

// Fetch jobs by run
$jobs = $this->job_repository->get_jobs_by_run( $run_id );
```

---

## Maintenance

### Database Cleanup

**Recommended Cleanup Schedule:**

| Data Type                | Retention Period | Method                                |
|--------------------------|------------------|---------------------------------------|
| Completed Runs           | 90 days          | Delete via `Run_Repository`           |
| Completed Jobs           | 30 days          | Delete via `Job_Repository`           |
| Failed Jobs (retryable)  | 7 days           | Retry or delete                       |
| Tasks (all)              | Cascade delete   | Deleted when parent job is deleted    |

**Cleanup Script Example:**

```bash
# Via WP-CLI
wp eval '
$run_repo = \XWP\DI\App::make(\PLLAT\Translator\Repositories\Run_Repository::class);
$cutoff = time() - (90 * DAY_IN_SECONDS);
$run_repo->delete_completed_before($cutoff);
echo "Cleanup complete\n";
'
```

---

**Last Updated:** 2025-10-24
**Schema Version:** 2.2.0
**Status:** ✅ Production-Ready
