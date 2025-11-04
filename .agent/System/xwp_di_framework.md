# X-WP DI Framework Documentation

Complete guide to the x-wp/di dependency injection framework used in PLLAT.

---

## Table of Contents

1. [Overview](#overview)
2. [Core Concepts](#core-concepts)
3. [Decorators (Attributes)](#decorators-attributes)
4. [Contexts](#contexts)
5. [Priority System](#priority-system)
6. [Module Loading Sequence](#module-loading-sequence)
7. [Best Practices](#best-practices)
8. [Common Pitfalls](#common-pitfalls)
9. [Real Examples from PLLAT](#real-examples-from-pllat)

---

## Overview

**x-wp/di** is a PHP attribute-based dependency injection framework for WordPress. It uses PHP 8 attributes (decorators) to define:

- **Modules:** Top-level containers that register handlers and services
- **Handlers:** Classes that orchestrate WordPress hooks (actions/filters)
- **Services:** Business logic classes (no WordPress coupling)
- **Actions/Filters:** Method-level decorators for WordPress hooks

**Key Features:**
- Attribute-based configuration (no manual hook registration)
- Automatic dependency injection via constructor
- Context-aware execution (frontend, admin, cron, CLI)
- Priority-based loading order
- Clean separation of concerns

---

## Core Concepts

### 1. Modules

**Purpose:** Top-level containers that register handlers, services, and child modules.

**Characteristics:**
- Use `#[Module]` attribute
- Define `hook` (WordPress hook to load on)
- Define `priority` (when to load relative to other modules)
- List `handlers`, `services`, `imports` (child modules)
- Optionally implement `On_Initialize` interface for initialization logic

**Example:**
```php
#[Module(
    hook: 'init',
    priority: 1,
    handlers: array(
        Discovery_Handler::class,
        Import_Handler::class,
    ),
    services: array(
        Discovery_Service::class,
        Import_Service::class,
    ),
)]
class Sync_Module implements On_Initialize {
    public function __construct(
        private Sync_Service $sync_service,
    ) {
        // DI container auto-injects dependencies
    }

    public function on_initialize(): void {
        // Optional initialization logic
        // Called when module loads
    }
}
```

### 2. Handlers

**Purpose:** Orchestrate WordPress hooks. No business logic, only hook registration and delegation to services.

**Characteristics:**
- Use `#[Handler]` attribute
- Define `tag` (WordPress hook to load on)
- Define `priority` (when to load)
- Methods use `#[Action]` or `#[Filter]` decorators
- Delegate to services for business logic

**Example:**
```php
#[Handler( tag: 'init', priority: 10 )]
class Discovery_Handler {
    public function __construct(
        protected Discovery_Service $discovery_service
    ) {
        // DI container auto-injects service
    }

    #[Action( tag: 'pllat_bulk_discovery_process_batch' )]
    public function process_batch_action(): void {
        // Delegate to service
        $this->discovery_service->process_batch();
    }
}
```

### 3. Services

**Purpose:** Contain business logic. No WordPress coupling, pure PHP classes.

**Characteristics:**
- No decorators needed (unless using `#[Service]` for explicit registration)
- Registered in Module's `services` array
- Dependencies auto-injected via constructor
- Testable without WordPress

**Example:**
```php
class Discovery_Service {
    public function __construct(
        private Language_Manager $language_manager,
        private Job_Repository $job_repository,
    ) {
        // DI container auto-injects dependencies
    }

    public function process_batch(): void {
        // Business logic here
        // No WordPress hooks or global state
    }
}
```

### 4. Actions & Filters

**Purpose:** Method-level decorators for WordPress actions/filters.

**Characteristics:**
- Use `#[Action(tag: 'hook_name')]` or `#[Filter(tag: 'hook_name')]`
- Optional `priority` parameter (default: 10)
- Optional `accepted_args` parameter (default: 1)
- Methods must be in a Handler class

**Example:**
```php
#[Handler( tag: 'init', priority: 10 )]
class Example_Handler {
    #[Action( tag: 'wp_loaded', priority: 5 )]
    public function early_action(): void {
        // Runs at priority 5 on wp_loaded
    }

    #[Filter( tag: 'the_content', priority: 20, accepted_args: 2 )]
    public function modify_content( string $content, int $post_id ): string {
        // Filters content at priority 20
        return $content;
    }
}
```

---

## Contexts

**Purpose:** Control when modules/handlers load based on execution environment.

### Available Contexts

From `vendor/x-wp/di/src/Context.php`:

```php
const CTX_FRONTEND = 1;   // Public-facing pages
const CTX_ADMIN    = 2;   // Admin dashboard
const CTX_CRON     = 8;   // WP-Cron background jobs
const CTX_CLI      = 16;  // WP-CLI commands
const CTX_REST     = 32;  // REST API requests
const CTX_AJAX     = 64;  // AJAX requests
```

### How Contexts Work

**Context Validation (Bitwise Operations):**
```php
public static function validate( int $context ): bool {
    return 0 !== ( self::get() & $context );
}
```

**Example:** Module only loads in admin:
```php
#[Module(
    hook: 'init',
    priority: 10,
    context: Context::CTX_ADMIN,
    // ...
)]
class Admin_Module {
    // Only loads in admin dashboard
}
```

**Example:** Handler loads in multiple contexts:
```php
#[Handler(
    tag: 'init',
    priority: 10,
    context: Context::CTX_ADMIN | Context::CTX_CRON,
)]
class Sync_Handler {
    // Loads in admin AND cron contexts
}
```

### Important: WP-Cron Context

**WP-Cron runs in `CTX_CRON` context:**
- Action Scheduler uses WP-Cron
- Decorators (`#[Action]`, `#[Filter]`) work perfectly in cron context
- No special handling needed for background jobs

**Common Misconception:** "Decorators don't work in WP-Cron"
- ❌ FALSE - decorators work perfectly in cron
- ✅ Real issue is usually priority ordering (see below)

---

## Priority System

**WordPress Priority Rules:**
1. **Lower number = Earlier execution**
2. **Default priority = 10**
3. **Priority range:** `-PHP_INT_MAX` to `PHP_INT_MAX` (practically 1-999)

### Module Priority

Controls WHEN the module loads relative to other modules on the same hook.

**Example:**
```php
// App module loads FIRST at priority 0
#[Module( hook: 'pll_init', priority: 0 )]
class App {
    // Loads child modules
}

// Sync module loads at priority 1 (after App)
#[Module( hook: 'init', priority: 1 )]
class Sync_Module {
    // Registers handlers and services
}
```

### Handler Priority

Controls WHEN the handler loads relative to other handlers on the same hook.

**Critical Rule:** Handlers MUST load AFTER their parent Module (or AT THE SAME TIME).

**Example:**
```php
// Module loads at priority 1
#[Module( hook: 'init', priority: 1 )]
class Sync_Module {
    // ...
}

// Handlers load at priority 10 (AFTER module)
#[Handler( tag: 'init', priority: 10 )]
class Discovery_Handler {
    // Loads after Sync_Module is initialized
}
```

**Why This Matters:**
- Module initializes DI container
- Handlers rely on DI container to inject dependencies
- If Handler loads BEFORE Module, DI container isn't ready
- Result: Callbacks not registered, actions fail

### Action/Filter Priority

Controls WHEN the callback runs relative to other callbacks on the same hook.

**Example:**
```php
#[Action( tag: 'wp_loaded', priority: 5 )]
public function early_action(): void {
    // Runs BEFORE default priority (10)
}

#[Action( tag: 'wp_loaded' )]  // Default priority: 10
public function normal_action(): void {
    // Runs at default priority
}

#[Action( tag: 'wp_loaded', priority: 20 )]
public function late_action(): void {
    // Runs AFTER default priority (10)
}
```

---

## Module Loading Sequence

**Complete Loading Flow for PLLAT:**

```
1. WordPress loads plugins (plugins_loaded hook, priority 0)
   └─> xwp_load_app() called

2. xwp_load_app() initializes DI container

3. App module loads (pll_init hook, priority 0)
   └─> Imports child modules:
       - Core_Module (not shown in example)
       - Settings_Module
       - Sync_Module ← Our focus
       - Translator_Module
       - etc.

4. Sync_Module loads (init hook, priority 1)
   ├─> Registers handlers:
   │   - Discovery_Handler (init hook, priority 10)
   │   - Import_Handler (init hook, priority 10)
   │   - Discovery_REST_Controller
   └─> Registers services:
       - Discovery_Service
       - Import_Service
       - Sync_Service

5. Sync_Module::on_initialize() runs
   └─> Checks if first-time setup needed
       └─> Calls sync_service->start_sync() if needed

6. Discovery_Handler loads (init hook, priority 10)
   ├─> Constructor injects Discovery_Service
   └─> #[Action] decorators processed:
       - process_batch_action() → 'pllat_bulk_discovery_process_batch'
       - restart_check_action() → 'pllat_discovery_restart_check'

7. Import_Handler loads (init hook, priority 10)
   ├─> Constructor injects Import_Service
   └─> #[Action] decorator processed:
       - process_batch_action() → 'pllat_import_existing_translations'

8. WordPress continues loading...
   └─> Action Scheduler runs in WP-Cron (CTX_CRON context)
       └─> Triggers registered callbacks successfully ✓
```

**Timeline Visualization:**

```
Time →
│
├─ plugins_loaded (priority 0): xwp_load_app()
│  └─ DI container initialized
│
├─ pll_init (priority 0): App module loads
│  └─ Child modules registered
│
├─ init (priority 1): Sync_Module loads
│  ├─ Handlers/services registered
│  └─ on_initialize() runs
│
├─ init (priority 10): Handlers load
│  ├─ Discovery_Handler
│  │  └─ #[Action] callbacks registered ✓
│  └─ Import_Handler
│     └─ #[Action] callbacks registered ✓
│
├─ wp_loaded: All plugins loaded
│
└─ WP-Cron executes:
   └─ Action Scheduler runs
      └─ Calls registered callbacks ✓
```

---

## Best Practices

### 1. Module Priority

**Rule:** Lower priority for parent modules, higher for child modules.

```php
✅ CORRECT:
#[Module( hook: 'init', priority: 1 )]   // Parent loads first
class Sync_Module { }

#[Handler( tag: 'init', priority: 10 )]  // Child loads after
class Discovery_Handler { }

❌ WRONG:
#[Module( hook: 'init', priority: 15 )]  // Parent loads last
class Sync_Module { }

#[Handler( tag: 'init', priority: 11 )]  // Child loads before parent!
class Discovery_Handler { }
```

### 2. Separation of Concerns

**Services contain business logic, Handlers orchestrate hooks:**

```php
✅ CORRECT:

// Service: Pure business logic
class Discovery_Service {
    public function process_batch(): void {
        // Complex logic here
    }
}

// Handler: Hook orchestration only
#[Handler( tag: 'init', priority: 10 )]
class Discovery_Handler {
    #[Action( tag: 'pllat_discovery' )]
    public function handle(): void {
        $this->discovery_service->process_batch();  // Delegate
    }
}

❌ WRONG:

// Service with hook registration
class Discovery_Service {
    public function __construct() {
        add_action('pllat_discovery', [$this, 'process_batch']);  // NO!
    }
}
```

### 3. Use Decorators, Not Manual Registration

**Let the framework handle hook registration:**

```php
✅ CORRECT:
#[Action( tag: 'pllat_discovery' )]
public function handle(): void {
    // Framework registers this automatically
}

❌ WRONG:
public function __construct() {
    add_action('pllat_discovery', [$this, 'handle']);  // Manual
}
```

### 4. Module Initialization

**Use `on_initialize()` for setup logic, not hook registration:**

```php
✅ CORRECT:
public function on_initialize(): void {
    // Setup, configuration, first-time logic
    if ( 'not_started' === get_option('status') ) {
        $this->service->start();
    }
}

❌ WRONG:
public function on_initialize(): void {
    // Hook registration (handlers should do this)
    add_action('some_hook', [$this->service, 'method']);
}
```

---

## Common Pitfalls

### Pitfall 1: Wrong Priority Order

**Problem:** Handlers load before their parent Module.

**Symptoms:**
- Callbacks not registered
- `$wp_filter['hook_name']->callbacks` is empty
- Action Scheduler errors: "no callbacks registered"

**Solution:** Module priority < Handler priority

```php
// BEFORE (BROKEN):
#[Module( hook: 'init', priority: 15 )]
class Sync_Module { }
#[Handler( tag: 'init', priority: 11 )]
class Discovery_Handler { }
// Handler loads at 11, Module at 15 - WRONG ORDER!

// AFTER (FIXED):
#[Module( hook: 'init', priority: 1 )]
class Sync_Module { }
#[Handler( tag: 'init', priority: 10 )]
class Discovery_Handler { }
// Module loads at 1, Handler at 10 - CORRECT ORDER!
```

### Pitfall 2: Hook Registration in Services

**Problem:** Services contain `add_action()` or `add_filter()` calls.

**Why Wrong:**
- Services should be pure business logic (testable without WordPress)
- Violates separation of concerns
- Makes unit testing difficult

**Solution:** Move hook registration to Handlers

```php
// BEFORE (WRONG):
class Discovery_Service {
    public function __construct() {
        add_action('pllat_discovery', [$this, 'process_batch']);
    }
}

// AFTER (CORRECT):
class Discovery_Service {
    // Clean service with no WordPress coupling
    public function process_batch(): void { }
}

#[Handler( tag: 'init', priority: 10 )]
class Discovery_Handler {
    #[Action( tag: 'pllat_discovery' )]
    public function handle(): void {
        $this->service->process_batch();
    }
}
```

### Pitfall 3: Hook Registration in Module

**Problem:** Module's `on_initialize()` contains `add_action()` calls.

**Why Wrong:**
- Modules should orchestrate, not register hooks directly
- Handlers are responsible for hook registration
- Harder to maintain and test

**Solution:** Use decorators in Handlers

```php
// BEFORE (NOT IDEAL):
class Sync_Module implements On_Initialize {
    public function on_initialize(): void {
        add_action('pllat_discovery', [$this->service, 'process']);
    }
}

// AFTER (BETTER):
class Sync_Module implements On_Initialize {
    public function on_initialize(): void {
        // Only setup/configuration logic
        if ( 'not_started' === get_option('status') ) {
            $this->service->start();
        }
    }
}

#[Handler( tag: 'init', priority: 10 )]
class Discovery_Handler {
    #[Action( tag: 'pllat_discovery' )]
    public function handle(): void {
        $this->service->process();
    }
}
```

### Pitfall 4: Assuming Decorators Don't Work in WP-Cron

**Problem:** Believing `#[Action]` decorators don't work in cron context.

**Why Wrong:**
- Decorators work perfectly in `CTX_CRON` context
- Action Scheduler uses WP-Cron
- Real issue is usually priority ordering (see Pitfall 1)

**Solution:** Fix priority order, not the decorator approach

```php
// This works perfectly in WP-Cron:
#[Handler( tag: 'init', priority: 10 )]
class Discovery_Handler {
    #[Action( tag: 'pllat_bulk_discovery_process_batch' )]
    public function process_batch_action(): void {
        // Called successfully by Action Scheduler via WP-Cron
        $this->service->process_batch();
    }
}
```

---

## Real Examples from PLLAT

### Example 1: App Module (Top-Level)

**File:** `src/App.php`

```php
#[Module(
    hook: 'pll_init',           // Load when Polylang initializes
    priority: 0,                // Load FIRST (lowest priority)
    imports: array(             // Child modules
        Core_Module::class,
        Settings_Module::class,
        Sync_Module::class,     // Our sync system
        Translator_Module::class,
        // ... other modules
    ),
)]
class App implements On_Initialize {
    public static function can_initialize(): bool {
        // Only initialize if Polylang is active
        return ! pllat_is_pll_deactivating() && pll_default_language();
    }

    public function on_initialize(): void {
        // Fire plugin loaded action
        do_action( 'pllat_loaded', pll_default_language() );
    }
}
```

**Key Points:**
- Loads at priority 0 (earliest possible)
- Imports all child modules
- Has conditional initialization via `can_initialize()`
- Uses `on_initialize()` for plugin-loaded action

### Example 2: Sync Module (Child Module)

**File:** `src/Modules/Sync/Sync_Module.php`

```php
#[Module(
    hook: 'init',
    priority: 1,                // After App (0), before handlers (10)
    handlers: array(
        Discovery_Handler::class,
        Import_Handler::class,
        Discovery_REST_Controller::class,
    ),
    services: array(
        Discovery_Service::class,
        Import_Service::class,
        Sync_Service::class,
    ),
)]
class Sync_Module implements Can_Initialize, On_Initialize {
    public function __construct(
        private Sync_Service $sync_service,
    ) {
        // DI injects Sync_Service
    }

    public static function can_initialize(): bool {
        return true;  // Always initialize
    }

    public function on_initialize(): void {
        // Auto-start sync on first plugin load
        $status = get_option( 'pllat_initialization_status', 'not_started' );
        if ( 'not_started' === $status ) {
            $this->sync_service->start_sync();
        }
    }
}
```

**Key Points:**
- Priority 1 (loads after App at 0)
- Registers handlers and services
- Uses `on_initialize()` for first-time setup logic
- Clean separation: no hook registration here

### Example 3: Discovery Handler

**File:** `src/Modules/Sync/Handlers/Discovery_Handler.php`

```php
#[Handler( tag: 'init', priority: 10 )]
class Discovery_Handler {
    public function __construct(
        protected Discovery_Service $discovery_service
    ) {
        // DI injects Discovery_Service
    }

    #[Action( tag: Discovery_Service::PROCESS_ACTION )]
    public function process_batch_action(): void {
        // Called by Action Scheduler via WP-Cron
        $this->discovery_service->process_batch();
    }

    #[Action( tag: Discovery_Service::RESTART_CHECK_ACTION )]
    public function restart_check_action(): void {
        // Periodic restart check
        $this->discovery_service->restart_check();
    }
}
```

**Key Points:**
- Priority 10 (loads after Sync_Module at 1)
- Uses `#[Action]` decorators (no manual `add_action`)
- Delegates to service (no business logic here)
- Works perfectly in WP-Cron context

### Example 4: Import Handler

**File:** `src/Modules/Sync/Handlers/Import_Handler.php`

```php
#[Handler( tag: 'init', priority: 10 )]
class Import_Handler {
    public function __construct(
        protected Import_Service $import_service
    ) {
        // DI injects Import_Service
    }

    #[Action( tag: Import_Service::IMPORT_ACTION )]
    public function process_batch_action(): void {
        // Called by Action Scheduler via WP-Cron
        $this->import_service->process_batch();
    }
}
```

**Key Points:**
- Same pattern as Discovery_Handler
- Priority 10 (after Sync_Module)
- Single action callback
- Clean delegation to service

### Example 5: Discovery Service (No Hooks)

**File:** `src/Modules/Sync/Services/Discovery_Service.php`

```php
class Discovery_Service extends Abstract_Batch_Service {
    const PROCESS_ACTION = 'pllat_bulk_discovery_process_batch';
    const RESTART_CHECK_ACTION = 'pllat_discovery_restart_check';

    public function __construct(
        Language_Manager $language_manager,
        Job_Repository $job_repository,
    ) {
        parent::__construct( $language_manager, $job_repository );
        // Clean constructor - no hook registration!
    }

    public function process_batch(): void {
        // Business logic for processing batch
        // Pure PHP - no WordPress hooks
    }

    public function restart_check(): void {
        // Business logic for restart check
        // Pure PHP - no WordPress hooks
    }

    public function schedule(): void {
        // Schedule Action Scheduler task
        as_schedule_recurring_action(
            time(),
            self::INTERVAL,
            self::PROCESS_ACTION,  // Hook name
            array(),
            'pllat-sync',
        );
    }
}
```

**Key Points:**
- No decorators (services don't need them)
- No `add_action()` calls
- Pure business logic
- Testable without WordPress
- Uses Action Scheduler to schedule background jobs

---

## Testing Hook Registration

**Verify callbacks are registered:**

```bash
# Check if callback exists
wp eval 'global $wp_filter;
echo "Callbacks: " . count($wp_filter["pllat_bulk_discovery_process_batch"]->callbacks ?? []);'

# Expected output: Callbacks: 1 (or more)
```

**Manually trigger action:**

```bash
# Trigger the action directly
wp eval 'do_action("pllat_bulk_discovery_process_batch");
echo "Action executed\n";'

# Should execute without errors
```

**Check Action Scheduler logs:**

```sql
-- View recent action logs
SELECT * FROM wp_actionscheduler_logs
WHERE action_id IN (
    SELECT action_id FROM wp_actionscheduler_actions
    WHERE hook = 'pllat_bulk_discovery_process_batch'
    ORDER BY action_id DESC
    LIMIT 1
)
ORDER BY log_id DESC
LIMIT 5;
```

**Expected:** "action completed" NOT "no callbacks registered"

---

## Summary

**Key Takeaways:**

1. **Priority Ordering Matters:**
   - Module priority < Handler priority
   - Lower number = earlier execution

2. **Separation of Concerns:**
   - Services = business logic (no hooks)
   - Handlers = hook orchestration (use decorators)
   - Modules = registration and initialization

3. **Use Decorators:**
   - `#[Action]` and `#[Filter]` work in all contexts
   - No manual `add_action()` needed
   - Clean, maintainable code

4. **Context Awareness:**
   - Use `context` parameter to control loading
   - Decorators work in `CTX_CRON` (WP-Cron/Action Scheduler)

5. **Module Initialization:**
   - Use `on_initialize()` for setup logic
   - NOT for hook registration

**The Fix for "No Callbacks Registered" Error:**

```diff
- #[Module( hook: 'init', priority: 15 )]  // WRONG: Too late
+ #[Module( hook: 'init', priority: 1 )]   // CORRECT: Load first

- #[Handler( tag: 'init', priority: 11 )]  // WRONG: Before module
+ #[Handler( tag: 'init', priority: 10 )]  // CORRECT: After module
```

**Result:** All decorators work perfectly, callbacks registered successfully in all contexts including WP-Cron! ✅

---

## References

- x-wp/di Documentation: https://github.com/x-wp/di
- WordPress Plugin API: https://developer.wordpress.org/plugins/hooks/
- Action Scheduler: https://actionscheduler.org/
- PHP Attributes: https://www.php.net/manual/en/language.attributes.overview.php

---

**Last Updated:** 2025-10-14
**Author:** PLLAT Development Team
**Status:** ✅ Production-Ready
