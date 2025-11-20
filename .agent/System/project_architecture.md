# Project Architecture

Complete architectural documentation for Polylang Automatic Translation with AI (PLLAT).

**Related docs:**
- [Database Schema](./database_schema.md)
- [XWP DI Framework](./xwp_di_framework.md)
- [Translation Workflow](./translation_workflow.md)

---

## Table of Contents

1. [Project Overview](#project-overview)
2. [Tech Stack](#tech-stack)
3. [Project Structure](#project-structure)
4. [Module System](#module-system)
5. [Core Concepts](#core-concepts)
6. [Integration Points](#integration-points)
7. [Data Flow](#data-flow)

---

## Project Overview

**Plugin Name:** Polylang Automatic Translation with AI
**Purpose:** Adds AI-powered automatic translation capabilities to Polylang, enabling automated bulk translation and single-item translation of WordPress content.

### Key Features

- **Bulk Translation:** Automatically translate all posts, pages, and terms in bulk
- **Single Translation:** Translate individual content items on-demand
- **Multi-Provider Support:** Claude, OpenAI, Gemini, OpenRouter
- **Background Processing:** Uses Action Scheduler for async translation jobs
- **Content Discovery:** Automatic detection of translatable content
- **Translation Dashboard:** React-based admin interface for monitoring translation progress
- **Integration Support:** Extensible integration system (currently supports Elementor)

### Key Goals

1. Seamlessly integrate with Polylang for automatic content translation
2. Support multiple AI providers with consistent interface
3. Handle large-scale bulk translation efficiently via background jobs
4. Provide real-time progress tracking and error reporting
5. Support complex content structures (posts, terms, meta fields, JSON)

---

## Tech Stack

### Backend (PHP)

- **PHP:** 8.1+ (strict typing, attributes)
- **WordPress:** 5.8+
- **Framework:** x-wp/di (attribute-based dependency injection)
- **Database:** Custom tables + WordPress core tables
- **Background Jobs:** Action Scheduler (WooCommerce)
- **HTTP Client:** Guzzle 7
- **AI Clients:** OpenAI PHP Client, custom wrappers for other providers
- **Logging:** Monolog

### Frontend (JavaScript)

- **Build Tool:** @wordpress/scripts (Webpack)
- **UI Framework:** React 18
- **State Management:** Zustand
- **HTTP Client:** Axios
- **Styling:** Tailwind CSS
- **Type System:** TypeScript 5.6

### Development Tools

- **Code Quality:** PHPStan (static analysis), PHPCS/PHPCBF (coding standards)
- **Standards:** WordPress Coding Standards, Oblak WordPress Coding Standard
- **Package Manager:** Composer (PHP), npm (JavaScript)
- **Version Control:** Git, Semantic Release

---

## Project Structure

```
epicwp-ai-translation-for-polylang/
├── .agent/                          # Project documentation
│   ├── README.md                    # Documentation index
│   ├── System/                      # System architecture docs
│   ├── SOP/                         # Standard operating procedures
│   └── Tasks/                       # Feature PRDs and implementation plans
│
├── assets/                          # Frontend source files
│   ├── scripts/                     # JavaScript/React components
│   │   └── admin/
│   │       ├── translation-dashboard/    # Main dashboard UI
│   │       └── single-translator/        # Single item translator UI
│   └── styles/                      # CSS/Tailwind source
│
├── build/                           # Compiled frontend assets
│   └── admin/
│       ├── translation-dashboard.js
│       ├── single-translator.js
│       └── admin.css
│
├── src/                             # PHP source code
│   ├── App.php                      # Main application class
│   ├── Common/                      # Shared utilities and services
│   │   ├── Installer/               # Database schema installer
│   │   ├── Services/                # Common services (Asset, JSON Patch, etc.)
│   │   ├── Interfaces/              # Shared interfaces
│   │   ├── Functions/               # Helper functions
│   │   └── Utils/                   # Bootstrap utilities
│   │
│   └── Modules/                     # Feature modules
│       ├── Admin/                   # Admin dashboard module
│       ├── Content/                 # Content service layer (posts, terms)
│       ├── Core/                    # Core services (Polylang integration)
│       ├── Integrations/            # Plugin integration system
│       │   ├── Core/                # Integration registry and base classes
│       │   └── Integrations/
│       │       └── Elementor/       # Elementor integration module
│       ├── Logs/                    # Logging module
│       ├── Settings/                # Settings page and storage
│       ├── Single_Translator/       # Single-item translation
│       ├── Status/                  # Health checks and status
│       ├── Sync/                    # Content discovery and import (deprecated/unused)
│       ├── Test/                    # WP-CLI test commands
│       └── Translator/              # Core translation engine
│           ├── Handlers/            # WordPress hook handlers
│           ├── Services/            # Translation services
│           ├── Repositories/        # Database access layer
│           ├── Providers/           # AI provider implementations
│           ├── Enums/               # Status enums
│           └── Models/              # Data models
│
├── vendor/                          # Composer dependencies
├── node_modules/                    # npm dependencies
│
├── polylang-ai-automatic-translation.php  # Plugin entry point
├── composer.json                    # PHP dependencies
├── package.json                     # JavaScript dependencies
├── phpstan.neon                     # PHPStan configuration
├── phpcs.xml                        # Coding standards configuration
└── tailwind.config.js               # Tailwind CSS configuration
```

---

## Module System

PLLAT uses the **x-wp/di framework** for modular architecture with attribute-based dependency injection.

### Module Hierarchy

```
App (pll_init:0)
├── Core_Module (init:1)
├── Settings_Module (init:1)
├── Test_Module (init:1)
├── Status_Module (init:1)
├── Logs_Module (init:1)
├── Translator_Module (init:1)           # Main translation engine
│   ├── Run_Processor_Handler
│   ├── Translator_Handler
│   └── Services (Job_Processor, Translator, etc.)
├── Content_Module (init:1)              # Content service layer
├── Admin_Module (init:1)                # Dashboard UI
├── Single_Translator_Module (init:1)    # Single-item translation
├── Integrations_Module (init:5)         # Plugin integrations
│   └── Elementor_Module (conditional)
└── Sync_Module (init:1)                 # [Deprecated] Content discovery
```

### Module Components

Each module typically consists of:

1. **Module Class:** Registers handlers and services, defines initialization logic
2. **Handlers:** Orchestrate WordPress hooks (actions/filters), delegate to services
3. **Services:** Contain business logic, no WordPress coupling
4. **Repositories:** Database access layer (CRUD operations)
5. **Models:** Data transfer objects
6. **Controllers:** REST API endpoints (optional)

### Example Module Structure

```
Translator/
├── Translator_Module.php                 # Module registration
├── Handlers/
│   ├── Run_Processor_Handler.php        # Background job processing
│   └── Translator_Handler.php           # Manual translation triggers
├── Services/
│   ├── Job_Processor.php                # Job execution logic
│   ├── Translator.php                   # Core translation service
│   ├── Translation_Run_Service.php      # Bulk run management
│   ├── AI_Provider_Factory.php          # Provider instantiation
│   └── AI_Provider_Registry.php         # Provider registration
├── Repositories/
│   ├── Job_Repository.php               # Jobs table CRUD
│   ├── Task_Repository.php              # Tasks table CRUD
│   └── Run_Repository.php               # Runs table CRUD
├── Providers/
│   ├── AI_Provider.php                  # Base provider interface
│   ├── Claude_Provider.php
│   ├── OpenAI_Provider.php
│   ├── Gemini_Provider.php
│   └── OpenRouter_Provider.php
├── Enums/
│   ├── JobStatus.php
│   ├── TaskStatus.php
│   └── RunStatus.php
└── Models/
    ├── Job.php
    ├── Task.php
    └── Run.php
```

---

## Core Concepts

### 1. Translation Job System

**Three-level hierarchy:**

```
Run (Bulk Translation Campaign)
└── Job (Single Content Item Translation)
    └── Task (Individual Field Translation)
```

**Example:**
- **Run:** Translate all posts from English to Spanish
  - **Job #1:** Translate Post ID 123
    - **Task #1:** Translate `post_title` field
    - **Task #2:** Translate `post_content` field
    - **Task #3:** Translate `post_excerpt` field
  - **Job #2:** Translate Post ID 124
    - ...

### 2. Content Discovery (Sync Module - Deprecated)

**Note:** The Sync module (Discovery/Import) was an experimental approach that is no longer used in production. Translation jobs are now created directly via the Admin module or Single Translator module.

### 3. AI Provider System

**Registry Pattern:**

```php
AI_Provider_Registry (global registry)
└── register_provider($id, $config)

AI_Provider_Factory
└── create_from_settings() → AI_Provider instance
    └── get_client() → AI_Client (OpenAI Client)
```

**Supported Providers:**
- **Claude:** Anthropic API (via OpenRouter or direct)
- **OpenAI:** GPT-3.5, GPT-4, GPT-4o
- **Gemini:** Google Gemini API
- **OpenRouter:** Multi-model proxy service

### 4. Background Processing

**Action Scheduler Integration:**

```
WordPress WP-Cron
└── Action Scheduler
    └── pllat_process_translation_run (recurring)
        └── Run_Processor_Handler::process_run()
            └── Job_Processor::process_jobs()
                └── Translator::translate()
                    └── AI_Provider::translate()
```

### 5. Content Service Layer

**Abstraction for Content Types:**

```php
interface Content_Service {
    public function get_content_data($id): array
    public function apply_translation($id, array $translations): void
}

Content_Module
├── Post_Content_Service (posts, pages, CPTs)
└── Term_Content_Service (categories, tags, taxonomies)
```

### 6. Integration System

**Extensible Plugin Integration:**

```
Integrations_Module (core registry)
└── Integration_Registry
    └── register_integration($id, $config)

Elementor_Module (conditional loading)
├── can_initialize() → Check if Elementor is active
└── Services
    └── Elementor_Content_Service (JSON translation)
```

---

## Integration Points

### 1. Polylang Integration

**Dependencies:**
- `pll_init` hook for module initialization
- `pll_default_language()` for default language detection
- Polylang's translation linking API

**Integration Points:**
- **Language Manager:** `Polylang_Language_Manager` service wraps Polylang API
- **Translation Links:** Created via `pll_set_post_language()` and `pll_save_post_translations()`
- **Term Translation:** `pll_set_term_language()` and `pll_save_term_translations()`

### 2. WordPress Core Integration

**Hooks:**
- `plugins_loaded` (priority 0): Plugin bootstrap
- `init`: Module registration
- `admin_menu`: Admin page registration
- `rest_api_init`: REST endpoint registration
- `save_post`: Content change detection (for auto-translation)
- `edit_term`: Term change detection

**Admin Pages:**
- **Dashboard:** `admin.php?page=polylang-ai-automatic-translation`
- **Settings:** `admin.php?page=polylang-ai-automatic-translation-settings`

### 3. Action Scheduler Integration

**Background Jobs:**
- **Hook:** `pllat_process_translation_run`
- **Recurrence:** Every 60 seconds
- **Group:** `pllat-translation`

### 4. REST API Endpoints

**Dashboard:**
- `GET /pllat/v1/dashboard/stats`
- `GET /pllat/v1/dashboard/runs`
- `POST /pllat/v1/dashboard/runs`
- `POST /pllat/v1/dashboard/runs/{id}/cancel`

**Single Translator:**
- `POST /pllat/v1/single-translator/translate`
- `GET /pllat/v1/single-translator/job/{id}/status`

---

## Data Flow

### Bulk Translation Flow

```
1. User creates translation run (Admin Dashboard)
   └─> POST /pllat/v1/dashboard/runs
       └─> Translation_Run_Service::create_run($config)
           └─> Creates Run record
           └─> Queries content via Post_Query_Service
           └─> Creates Job records for each item
           └─> Schedules Action Scheduler job

2. Action Scheduler executes (WP-Cron)
   └─> Run_Processor_Handler::process_run()
       └─> Job_Processor::process_jobs($run_id)
           └─> Fetches pending jobs
           └─> For each job:
               └─> Content_Service::get_content_data($id)
               └─> Creates Task records for each field
               └─> Translator::translate($tasks)
                   └─> AI_Provider::translate($text)
               └─> Content_Service::apply_translation($id, $translations)
               └─> Updates Job status
           └─> Updates Run progress

3. Dashboard polls for updates (React)
   └─> GET /pllat/v1/dashboard/runs
       └─> Returns run progress, job stats, errors
```

### Single Translation Flow

```
1. User clicks "Translate" button (Post Edit Screen)
   └─> POST /pllat/v1/single-translator/translate
       └─> Single_Translation_Service::translate($post_id, $lang_to)
           └─> Creates Job record
           └─> Content_Service::get_content_data($post_id)
           └─> Creates Task records
           └─> Translator::translate($tasks)
               └─> AI_Provider::translate($text)
           └─> Content_Service::apply_translation($post_id, $translations)
           └─> Returns translated post ID

2. React UI updates status (polling or WebSocket in future)
   └─> GET /pllat/v1/single-translator/job/{id}/status
       └─> Returns job progress, task statuses, errors
```

### Content Change Detection Flow (Auto-Translation)

```
1. User updates post content
   └─> save_post hook fires
       └─> Content_Change_Handler::on_post_save($post_id)
           └─> Check if auto-translation enabled
           └─> Check if post is in default language
           └─> For each target language:
               └─> Create Job record
               └─> Enqueue translation job
                   └─> Follows same flow as single translation
```

---

## Performance Optimizations

### 1. Batch Processing

- Process jobs in batches (default: 5 jobs per run)
- Configurable batch size via settings
- Prevents memory exhaustion on large datasets

### 2. Async Translation

- All translations run in background via Action Scheduler
- Non-blocking UI experience
- Graceful handling of API timeouts and rate limits

### 3. Database Indexing

- Composite indexes on frequently queried columns
- Status-based filtering for pending jobs
- Efficient run/job/task lookups

### 4. Caching Strategy

- DI container caches service instances
- Settings cached via WordPress transients
- Language data cached in memory

### 5. Conditional Module Loading

- Modules use `can_initialize()` for conditional loading
- Integrations only load when target plugin is active
- Translator module only loads when API configured

---

## Security Considerations

### 1. API Key Storage

- Stored in `wp_options` table (encrypted in future)
- Not exposed in frontend JavaScript
- Only accessible to admin users

### 2. REST API Authentication

- WordPress nonce verification
- Capability checks (`manage_options`)
- Rate limiting (via Action Scheduler)

### 3. Database Access

- Prepared statements via `$wpdb`
- Repository pattern for consistent access control
- Type validation on all inputs

### 4. XSS Prevention

- All output escaped via `esc_html()`, `esc_attr()`
- React automatically escapes text content
- Tailwind CSS provides safe styling

---

## Future Enhancements

### Planned Features

1. **Additional Integrations:**
   - ACF (Advanced Custom Fields)
   - Bricks Builder
   - Beaver Builder
   - WooCommerce product content

2. **Advanced Translation Features:**
   - Translation memory (reuse previous translations)
   - Glossary support (consistent term translation)
   - Context-aware translation (preserve formatting)

3. **Performance Improvements:**
   - Parallel job processing (multiple workers)
   - Smart batching (prioritize by content type)
   - Incremental translation (only changed fields)

4. **Enhanced Monitoring:**
   - Real-time progress tracking (WebSockets)
   - Detailed error reporting and retry logic
   - Translation quality metrics

5. **User Experience:**
   - Visual diff viewer (compare original vs translation)
   - Bulk edit translations
   - Translation history and rollback

---

**Last Updated:** 2025-10-24
**Version:** 2.2.0
**Status:** ✅ Production-Ready
