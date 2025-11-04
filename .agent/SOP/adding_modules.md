# SOP: Adding New Modules

Standard operating procedure for adding new feature modules to PLLAT.

**Related docs:**
- [Project Architecture](../System/project_architecture.md)
- [XWP DI Framework](../System/xwp_di_framework.md)

---

## Overview

Modules are the primary organizational unit in PLLAT. Each module encapsulates a specific feature or domain area using the x-wp/di framework's attribute-based dependency injection.

---

## Prerequisites

Before creating a new module:

1. **Read the documentation:**
   - [Project Architecture](../System/project_architecture.md)
   - [XWP DI Framework](../System/xwp_di_framework.md)

2. **Understand the module hierarchy:**
   - All modules are imported by `src/App.php`
   - Modules can import child modules
   - Priority determines loading order

3. **Plan your module structure:**
   - What handlers are needed? (WordPress hook orchestration)
   - What services are needed? (Business logic)
   - What repositories are needed? (Database access)
   - What REST endpoints are needed? (API)

---

## Step-by-Step Guide

### Step 1: Create Module Directory Structure

Create the module directory under `src/Modules/`:

```bash
mkdir -p src/Modules/YourModule/{Handlers,Services,Repositories,Controllers}
```

**Example structure:**
```
src/Modules/YourModule/
├── YourModule_Module.php       # Module class
├── Handlers/
│   └── Your_Handler.php        # WordPress hook handlers
├── Services/
│   └── Your_Service.php        # Business logic
├── Repositories/
│   └── Your_Repository.php     # Database access (if needed)
└── Controllers/
    └── Your_REST_Controller.php # REST API (if needed)
```

---

### Step 2: Create the Module Class

**File:** `src/Modules/YourModule/YourModule_Module.php`

```php
<?php
declare(strict_types=1);

namespace PLLAT\YourModule;

use PLLAT\YourModule\Handlers\Your_Handler;
use PLLAT\YourModule\Services\Your_Service;
use XWP\DI\Decorators\Module;
use XWP\DI\Interfaces\Can_Initialize;
use XWP\DI\Interfaces\On_Initialize;

/**
 * YourModule module definition.
 */
#[Module(
    hook: 'init',                          // When to load (init, pll_init, etc.)
    priority: 1,                           // Lower = earlier (App loads at 0)
    handlers: array(
        Your_Handler::class,               // WordPress hook handlers
    ),
    services: array(
        Your_Service::class,               // Business logic services
    ),
)]
class YourModule_Module implements Can_Initialize, On_Initialize {
    /**
     * Constructor with dependency injection.
     */
    public function __construct(
        private Your_Service $your_service,
    ) {
        // Dependencies auto-injected by DI container
    }

    /**
     * Check if the module can be initialized.
     * Only initialize if dependencies are met.
     *
     * @return bool
     */
    public static function can_initialize(): bool {
        // Add conditional logic here
        // Examples:
        // - Check if required plugin is active
        // - Check if settings are configured
        // - Check if user has required capabilities
        return true;
    }

    /**
     * Module configuration (optional).
     * Define custom DI bindings here.
     *
     * @return array<string,mixed>
     */
    public static function configure(): array {
        return array(
            // Custom DI bindings
            // Example:
            // SomeInterface::class => \DI\factory(
            //     static fn() => new SomeImplementation(),
            // ),
        );
    }

    /**
     * Initialize the module.
     * Called when module is loaded.
     *
     * Use this for:
     * - Setup logic
     * - Configuration
     * - First-time initialization
     *
     * DO NOT use this for:
     * - Hook registration (use Handlers instead)
     * - Business logic (use Services instead)
     */
    public function on_initialize(): void {
        // Optional initialization logic
        // Example: Check if first-time setup needed
        if ( 'not_started' === get_option( 'yourmodule_status', 'not_started' ) ) {
            $this->your_service->initialize();
        }
    }
}
```

---

### Step 3: Create Handler Classes

Handlers orchestrate WordPress hooks. They should delegate all business logic to services.

**File:** `src/Modules/YourModule/Handlers/Your_Handler.php`

```php
<?php
declare(strict_types=1);

namespace PLLAT\YourModule\Handlers;

use PLLAT\YourModule\Services\Your_Service;
use XWP\DI\Decorators\Action;
use XWP\DI\Decorators\Filter;
use XWP\DI\Decorators\Handler;

/**
 * Handler for YourModule WordPress hooks.
 */
#[Handler( tag: 'init', priority: 10 )]
class Your_Handler {
    /**
     * Constructor with dependency injection.
     */
    public function __construct(
        protected Your_Service $your_service
    ) {
        // Service auto-injected
    }

    /**
     * Example action handler.
     */
    #[Action( tag: 'your_custom_action', priority: 10 )]
    public function handle_action(): void {
        // Delegate to service
        $this->your_service->do_something();
    }

    /**
     * Example filter handler.
     */
    #[Filter( tag: 'the_content', priority: 20, accepted_args: 2 )]
    public function filter_content( string $content, int $post_id ): string {
        // Delegate to service
        return $this->your_service->modify_content( $content, $post_id );
    }

    /**
     * Example WordPress core hook handler.
     */
    #[Action( tag: 'save_post', priority: 20, accepted_args: 3 )]
    public function on_post_save( int $post_id, \WP_Post $post, bool $update ): void {
        // Delegate to service
        $this->your_service->handle_post_save( $post_id, $post, $update );
    }
}
```

**Key Points:**
- ✅ Handlers orchestrate hooks only
- ✅ Delegate all logic to services
- ✅ Use `#[Action]` and `#[Filter]` decorators
- ❌ NO business logic in handlers
- ❌ NO database queries in handlers
- ❌ NO manual `add_action()` calls

---

### Step 4: Create Service Classes

Services contain business logic. They should have no WordPress coupling (for testability).

**File:** `src/Modules/YourModule/Services/Your_Service.php`

```php
<?php
declare(strict_types=1);

namespace PLLAT\YourModule\Services;

/**
 * Service for YourModule business logic.
 */
class Your_Service {
    /**
     * Constructor with dependency injection.
     */
    public function __construct(
        // Inject other services, repositories, etc.
    ) {
        // Dependencies auto-injected
    }

    /**
     * Example business logic method.
     */
    public function do_something(): void {
        // Business logic here
        // Pure PHP - no WordPress hooks or global state
    }

    /**
     * Example method with return value.
     */
    public function modify_content( string $content, int $post_id ): string {
        // Business logic to modify content
        return $content;
    }

    /**
     * Example method handling WordPress event.
     */
    public function handle_post_save( int $post_id, \WP_Post $post, bool $update ): void {
        // Business logic for post save event
    }
}
```

**Key Points:**
- ✅ Pure business logic
- ✅ Testable without WordPress
- ✅ Use dependency injection
- ❌ NO `add_action()` or `add_filter()` calls
- ❌ Avoid WordPress globals when possible
- ❌ NO hook registration

---

### Step 5: Create Repository Classes (Optional)

Repositories handle database access. Use the Repository pattern for consistency.

**File:** `src/Modules/YourModule/Repositories/Your_Repository.php`

```php
<?php
declare(strict_types=1);

namespace PLLAT\YourModule\Repositories;

/**
 * Repository for YourModule database access.
 */
class Your_Repository {
    /**
     * Constructor.
     */
    public function __construct() {
        // No dependencies typically needed
    }

    /**
     * Create a new record.
     *
     * @param array<string,mixed> $data Record data.
     * @return int Record ID.
     */
    public function create( array $data ): int {
        global $wpdb;

        $table = $wpdb->prefix . 'yourmodule_table';

        $wpdb->insert( $table, $data );

        return (int) $wpdb->insert_id;
    }

    /**
     * Get a record by ID.
     *
     * @param int $id Record ID.
     * @return object|null Record object or null.
     */
    public function get( int $id ): ?object {
        global $wpdb;

        $table = $wpdb->prefix . 'yourmodule_table';

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $id
        ) );

        return $row ?: null;
    }

    /**
     * Update a record.
     *
     * @param int                  $id   Record ID.
     * @param array<string,mixed> $data Updated data.
     * @return bool Success status.
     */
    public function update( int $id, array $data ): bool {
        global $wpdb;

        $table = $wpdb->prefix . 'yourmodule_table';

        $result = $wpdb->update(
            $table,
            $data,
            array( 'id' => $id )
        );

        return false !== $result;
    }

    /**
     * Delete a record.
     *
     * @param int $id Record ID.
     * @return bool Success status.
     */
    public function delete( int $id ): bool {
        global $wpdb;

        $table = $wpdb->prefix . 'yourmodule_table';

        $result = $wpdb->delete(
            $table,
            array( 'id' => $id )
        );

        return false !== $result;
    }
}
```

**Key Points:**
- ✅ Encapsulate database access
- ✅ Use prepared statements
- ✅ Return typed values
- ❌ NO business logic in repositories
- ❌ NO WordPress hooks

---

### Step 6: Register Module in App

Add your module to the main `App` class imports.

**File:** `src/App.php`

```php
#[Module(
    hook: 'pll_init',
    priority: 0,
    imports: array(
        Core_Module::class,
        Settings_Module::class,
        // ... existing modules ...
        YourModule_Module::class,  // ← Add your module here
    ),
)]
class App implements On_Initialize {
    // ...
}
```

**Import Order:**
- Order doesn't matter in `imports` array
- Priority is determined by each module's `priority` setting
- All modules at same priority load in array order

---

### Step 7: Add Database Tables (If Needed)

If your module needs custom database tables, add the schema to the Installer.

**File:** `src/Common/Installer/Installer.php`

```php
public static function install(): void {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();

    // Add your table schema
    $your_table = $wpdb->prefix . 'yourmodule_table';
    $sql_your_table = "CREATE TABLE {$your_table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(191) NOT NULL,
        value LONGTEXT NULL,
        created_at BIGINT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY  (id),
        KEY name (name)
    ) {$charset_collate};";

    dbDelta( $sql_your_table );

    // ... existing table schemas ...
}
```

---

### Step 8: Add REST API Endpoints (Optional)

If your module needs REST API endpoints, create a controller.

**File:** `src/Modules/YourModule/Controllers/Your_REST_Controller.php`

```php
<?php
declare(strict_types=1);

namespace PLLAT\YourModule\Controllers;

use PLLAT\YourModule\Services\Your_Service;
use WP_REST_Request;
use WP_REST_Response;
use XWP\DI\Decorators\Action;
use XWP\DI\Decorators\Handler;

/**
 * REST API controller for YourModule.
 */
#[Handler( tag: 'rest_api_init', priority: 10 )]
class Your_REST_Controller {
    /**
     * API namespace.
     */
    private const NAMESPACE = 'pllat/v1';

    /**
     * Constructor with dependency injection.
     */
    public function __construct(
        private Your_Service $your_service
    ) {}

    /**
     * Register REST routes.
     */
    #[Action( tag: 'rest_api_init' )]
    public function register_routes(): void {
        register_rest_route(
            self::NAMESPACE,
            '/yourmodule/endpoint',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'handle_endpoint' ),
                'permission_callback' => array( $this, 'check_permissions' ),
            )
        );
    }

    /**
     * Handle REST endpoint.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function handle_endpoint( WP_REST_Request $request ): WP_REST_Response {
        $params = $request->get_json_params();

        // Delegate to service
        $result = $this->your_service->do_something( $params );

        return new WP_REST_Response( array(
            'success' => true,
            'data'    => $result,
        ) );
    }

    /**
     * Check permissions for endpoint.
     *
     * @return bool
     */
    public function check_permissions(): bool {
        return current_user_can( 'manage_options' );
    }
}
```

**Key Points:**
- ✅ Use `rest_api_init` hook
- ✅ Delegate to services
- ✅ Add permission checks
- ✅ Return typed responses
- ❌ NO business logic in controllers

---

## Testing Your Module

### 1. Test Module Loading

```bash
# Check if module is loaded
wp eval '
$app = \XWP\DI\App::get("pllat");
$modules = $app->get_modules();
var_dump(array_keys($modules));
'
```

### 2. Test Handler Registration

```bash
# Check if hooks are registered
wp eval '
global $wp_filter;
var_dump(isset($wp_filter["your_custom_action"]));
'
```

### 3. Test Service Access

```bash
# Test service via DI container
wp eval '
$service = \XWP\DI\App::make(\PLLAT\YourModule\Services\Your_Service::class);
$service->do_something();
echo "Service works!\n";
'
```

---

## Common Pitfalls

### Pitfall 1: Wrong Priority Order

**Problem:** Handler loads before module.

**Solution:** Module priority < Handler priority.

```php
// ❌ WRONG
#[Module( hook: 'init', priority: 15 )]
#[Handler( tag: 'init', priority: 10 )]

// ✅ CORRECT
#[Module( hook: 'init', priority: 1 )]
#[Handler( tag: 'init', priority: 10 )]
```

### Pitfall 2: Business Logic in Handlers

**Problem:** Handlers contain complex logic.

**Solution:** Move logic to services.

```php
// ❌ WRONG
#[Handler]
class Your_Handler {
    #[Action( tag: 'save_post' )]
    public function on_save( $post_id ) {
        // Complex logic here...
        $wpdb->insert(...);
        wp_mail(...);
    }
}

// ✅ CORRECT
#[Handler]
class Your_Handler {
    #[Action( tag: 'save_post' )]
    public function on_save( $post_id ) {
        $this->service->handle_post_save( $post_id );
    }
}
```

### Pitfall 3: Manual Hook Registration

**Problem:** Using `add_action()` instead of decorators.

**Solution:** Use `#[Action]` and `#[Filter]` decorators.

```php
// ❌ WRONG
public function __construct() {
    add_action( 'save_post', array( $this, 'on_save' ) );
}

// ✅ CORRECT
#[Action( tag: 'save_post' )]
public function on_save( $post_id ) {
    // ...
}
```

---

## Checklist

Before submitting your module:

- [ ] Module class created with proper attributes
- [ ] Handlers use decorators (no manual hook registration)
- [ ] Services contain business logic only
- [ ] Repositories use prepared statements
- [ ] Module registered in `App.php`
- [ ] Database tables added to Installer (if needed)
- [ ] REST endpoints registered properly (if needed)
- [ ] Module loads in correct context (admin, frontend, cron)
- [ ] Priority order correct (module < handlers)
- [ ] Code follows WordPress coding standards (PHPCS)
- [ ] No PHPStan errors
- [ ] Tested module loading and functionality

---

**Last Updated:** 2025-10-24
**Version:** 1.0.0
**Status:** ✅ Production-Ready
