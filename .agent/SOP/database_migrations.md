# SOP: Database Schema Migrations

Standard operating procedure for managing database schema changes in PLLAT.

**Related docs:**
- [Database Schema](../System/database_schema.md)
- [Project Architecture](../System/project_architecture.md)

---

## Overview

PLLAT uses custom database tables for translation job management. Schema changes are handled through versioned migrations in the `Installer` class.

**Key Principles:**
1. Never modify existing migrations - always add new ones
2. Migrations are forward-only (no rollback support)
3. Test migrations on a copy of production data
4. Backfill data when adding non-nullable columns

---

## Database Version System

### Version Format

`MAJOR.MINOR.PATCH` (Semantic Versioning)

- **MAJOR:** Breaking changes, data structure changes
- **MINOR:** New tables, new columns, new indexes
- **PATCH:** Bug fixes, data corrections

### Version Constants

**File:** `polylang-ai-automatic-translation.php`

```php
define( 'PLLAT_DB_VERSION', '2.2.0' );  // Current DB version
```

**Storage:** `wp_options` table
- Key: `pllat_db_version`
- Value: Current installed version

---

## Migration Workflow

### Step 1: Update DB Version

Increment the database version constant.

**File:** `polylang-ai-automatic-translation.php`

```php
// Before
define( 'PLLAT_DB_VERSION', '2.2.0' );

// After
define( 'PLLAT_DB_VERSION', '2.3.0' );
```

---

### Step 2: Update Schema in install()

Modify the `install()` method to include schema changes.

**File:** `src/Common/Installer/Installer.php`

**Add new column:**
```php
public static function install(): void {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();
    $jobs_table = $wpdb->prefix . 'pllat_jobs';

    // IMPORTANT: Add new column to existing CREATE TABLE statement
    $sql_jobs = "CREATE TABLE {$jobs_table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        type VARCHAR(20) NOT NULL,
        content_type VARCHAR(50) NOT NULL DEFAULT '',
        id_from BIGINT UNSIGNED NOT NULL,
        id_to BIGINT UNSIGNED NULL,
        new_column VARCHAR(191) NULL,  -- ← NEW COLUMN ADDED
        lang_from VARCHAR(10) NOT NULL,
        lang_to VARCHAR(10) NOT NULL,
        run_id BIGINT UNSIGNED NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        created_at BIGINT UNSIGNED NOT NULL DEFAULT 0,
        started_at BIGINT UNSIGNED NOT NULL DEFAULT 0,
        completed_at BIGINT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY  (id),
        KEY run_id (run_id),
        KEY id_from (id_from),
        KEY id_to (id_to),
        KEY status (status),
        KEY new_column (new_column)  -- ← INDEX FOR NEW COLUMN
    ) {$charset_collate};";

    dbDelta( $sql_jobs );

    // ... existing tables ...
}
```

**Add new table:**
```php
public static function install(): void {
    // ... existing tables ...

    // New table
    $new_table = $wpdb->prefix . 'pllat_new_table';
    $sql_new_table = "CREATE TABLE {$new_table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(191) NOT NULL,
        value LONGTEXT NULL,
        created_at BIGINT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY  (id),
        KEY name (name)
    ) {$charset_collate};";

    dbDelta( $sql_new_table );

    // ... version update ...
}
```

---

### Step 3: Add Migration Logic

Add migration logic to the `run_migrations()` method.

**File:** `src/Common/Installer/Installer.php`

```php
/**
 * Run migrations based on version changes.
 *
 * @param string $from_version The current version.
 * @param string $to_version The target version.
 * @return void
 */
private static function run_migrations( string $from_version, string $to_version ): void {
    // Existing migrations
    if ( version_compare( $from_version, '2.0.0', '<' ) && version_compare( $to_version, '2.0.0', '>=' ) ) {
        self::migrate_to_2_0_0();
    }

    if ( version_compare( $from_version, '2.2.0', '<' ) && version_compare( $to_version, '2.2.0', '>=' ) ) {
        self::migrate_to_2_2_0();
    }

    // NEW MIGRATION
    if ( version_compare( $from_version, '2.3.0', '<' ) && version_compare( $to_version, '2.3.0', '>=' ) ) {
        self::migrate_to_2_3_0();
    }
}
```

---

### Step 4: Implement Migration Method

Create the migration method with proper error handling.

**File:** `src/Common/Installer/Installer.php`

```php
/**
 * Migration to 2.3.0: Add new_column and backfill data.
 *
 * @return void
 */
private static function migrate_to_2_3_0(): void {
    global $wpdb;

    $jobs_table = $wpdb->prefix . 'pllat_jobs';

    // Step 1: Backfill new_column based on existing data
    $wpdb->query(
        "UPDATE {$jobs_table}
        SET new_column = 'default_value'
        WHERE new_column IS NULL"
    );

    // Step 2: If you need to transform data
    $wpdb->query(
        "UPDATE {$jobs_table}
        SET new_column = CONCAT('prefix_', id)
        WHERE new_column = 'default_value'"
    );

    // Step 3: Log migration completion
    error_log( 'PLLAT: Migration to 2.3.0 completed' );
}
```

---

## Migration Patterns

### Pattern 1: Add Nullable Column

**Scenario:** Add optional column without backfill.

```php
// Schema change (in install())
$sql = "CREATE TABLE {$table} (
    -- ... existing columns ...
    new_column VARCHAR(191) NULL,  -- Nullable
    -- ... rest of schema ...
) {$charset_collate};";

// Migration (no backfill needed)
private static function migrate_to_x_x_x(): void {
    // Nothing to do - nullable column added by dbDelta
    error_log( 'PLLAT: Migration to X.X.X completed (nullable column added)' );
}
```

---

### Pattern 2: Add Non-Nullable Column

**Scenario:** Add required column with default value.

```php
// Schema change (in install())
$sql = "CREATE TABLE {$table} (
    -- ... existing columns ...
    new_column VARCHAR(191) NOT NULL DEFAULT 'default_value',
    -- ... rest of schema ...
) {$charset_collate};";

// Migration (backfill required)
private static function migrate_to_x_x_x(): void {
    global $wpdb;
    $table = $wpdb->prefix . 'pllat_table';

    // Backfill with default value
    $wpdb->query(
        "UPDATE {$table}
        SET new_column = 'default_value'
        WHERE new_column = '' OR new_column IS NULL"
    );
}
```

---

### Pattern 3: Add Column with Derived Data

**Scenario:** Add column based on existing data or external source.

```php
// Migration
private static function migrate_to_2_0_0(): void {
    global $wpdb;
    $jobs_table = $wpdb->prefix . 'pllat_jobs';

    // Backfill from related table (posts)
    $wpdb->query(
        "UPDATE {$jobs_table} j
        INNER JOIN {$wpdb->posts} p ON j.type = 'post' AND j.id_from = p.ID
        SET j.content_type = p.post_type
        WHERE j.type = 'post' AND j.content_type = ''"
    );

    // Backfill from related table (terms)
    $wpdb->query(
        "UPDATE {$jobs_table} j
        INNER JOIN {$wpdb->term_taxonomy} tt ON j.type = 'term' AND j.id_from = tt.term_id
        SET j.content_type = tt.taxonomy
        WHERE j.type = 'term' AND j.content_type = ''"
    );
}
```

---

### Pattern 4: Add Index

**Scenario:** Add performance optimization index.

```php
// Schema change (in install())
$sql = "CREATE TABLE {$table} (
    -- ... existing columns ...
    KEY idx_new_index (column1, column2)  -- New index
) {$charset_collate};";

// Migration (no backfill needed)
private static function migrate_to_x_x_x(): void {
    // dbDelta will add index automatically
    error_log( 'PLLAT: Migration to X.X.X completed (index added)' );
}
```

---

### Pattern 5: Change Column Type (Complex)

**Scenario:** Change column from VARCHAR to BIGINT (requires data transformation).

```php
// Schema change (in install())
$sql = "CREATE TABLE {$table} (
    -- ... existing columns ...
    id_reference BIGINT UNSIGNED NOT NULL,  -- Changed from VARCHAR
    -- ... rest of schema ...
) {$charset_collate};";

// Migration (data transformation required)
private static function migrate_to_x_x_x(): void {
    global $wpdb;
    $table = $wpdb->prefix . 'pllat_table';

    // Step 1: Add temporary column
    $wpdb->query(
        "ALTER TABLE {$table}
        ADD COLUMN id_reference_new BIGINT UNSIGNED NULL"
    );

    // Step 2: Transform and copy data
    $wpdb->query(
        "UPDATE {$table}
        SET id_reference_new = CAST(id_reference AS UNSIGNED)
        WHERE id_reference REGEXP '^[0-9]+$'"
    );

    // Step 3: Drop old column
    $wpdb->query(
        "ALTER TABLE {$table}
        DROP COLUMN id_reference"
    );

    // Step 4: Rename new column
    $wpdb->query(
        "ALTER TABLE {$table}
        CHANGE id_reference_new id_reference BIGINT UNSIGNED NOT NULL"
    );
}
```

---

### Pattern 6: Data Cleanup (Clear Tables)

**Scenario:** Plugin not yet in production, safe to clear data.

```php
// Migration (clear existing data)
private static function migrate_to_2_2_0(): void {
    global $wpdb;

    $jobs_table  = $wpdb->prefix . 'pllat_jobs';
    $tasks_table = $wpdb->prefix . 'pllat_tasks';

    // Clear existing data - plugin not yet in production
    $wpdb->query( "TRUNCATE TABLE {$tasks_table}" );
    $wpdb->query( "TRUNCATE TABLE {$jobs_table}" );

    error_log( 'PLLAT: Migration to 2.2.0 completed (tables cleared)' );
}
```

---

## Testing Migrations

### 1. Test Fresh Install

**Scenario:** First-time installation (no existing data).

```bash
# Activate plugin
wp plugin activate polylang-ai-automatic-translation

# Check DB version
wp option get pllat_db_version

# Verify tables exist
wp db query "SHOW TABLES LIKE 'wp_pllat_%';"

# Check schema
wp db query "DESCRIBE wp_pllat_jobs;"
```

---

### 2. Test Migration from Previous Version

**Scenario:** Upgrade from version 2.2.0 to 2.3.0.

```bash
# 1. Backup database
wp db export backup-before-migration.sql

# 2. Set old version
wp option update pllat_db_version 2.2.0

# 3. Create test data (if needed)
wp eval '
$repo = \XWP\DI\App::make(\PLLAT\Translator\Repositories\Job_Repository::class);
$repo->create(array(
    "type" => "post",
    "content_type" => "post",
    "id_from" => 123,
    "lang_from" => "en",
    "lang_to" => "es",
    "status" => "pending",
    "created_at" => time(),
));
'

# 4. Trigger migration
wp eval '
$installer = new \PLLAT\Common\Installer\Installer();
$installer->maybe_upgrade();
echo "Migration complete\n";
'

# 5. Verify migration
wp option get pllat_db_version  # Should be 2.3.0
wp db query "DESCRIBE wp_pllat_jobs;"  # Check new column exists

# 6. Verify data backfilled
wp db query "SELECT id, new_column FROM wp_pllat_jobs LIMIT 5;"
```

---

### 3. Test Idempotency

**Scenario:** Running migration multiple times should be safe.

```bash
# Run migration twice
wp eval '
$installer = new \PLLAT\Common\Installer\Installer();
$installer->maybe_upgrade();
echo "First run complete\n";
'

wp eval '
$installer = new \PLLAT\Common\Installer\Installer();
$installer->maybe_upgrade();
echo "Second run complete\n";
'

# Verify data integrity
wp db query "SELECT COUNT(*) FROM wp_pllat_jobs;"
```

---

## Deployment Checklist

Before deploying a migration to production:

- [ ] DB version incremented in plugin header
- [ ] Schema updated in `install()` method
- [ ] Migration method created in `run_migrations()`
- [ ] Migration logic handles existing data correctly
- [ ] Tested fresh install
- [ ] Tested migration from previous version
- [ ] Tested idempotency (running migration multiple times)
- [ ] Backup strategy documented
- [ ] Rollback plan prepared (if needed)
- [ ] Performance impact assessed (for large datasets)
- [ ] Migration logged via `error_log()`

---

## Common Pitfalls

### Pitfall 1: Non-Idempotent Migrations

**Problem:** Running migration twice breaks data.

**Solution:** Use conditional logic or upsert patterns.

```php
// ❌ BAD: Duplicates data
private static function migrate_to_x_x_x(): void {
    $wpdb->query( "INSERT INTO {$table} (name) VALUES ('test')" );
}

// ✅ GOOD: Idempotent
private static function migrate_to_x_x_x(): void {
    $wpdb->query(
        "INSERT INTO {$table} (name) VALUES ('test')
        ON DUPLICATE KEY UPDATE name = VALUES(name)"
    );
}
```

---

### Pitfall 2: Not Handling NULL Values

**Problem:** Backfill query doesn't account for NULL.

**Solution:** Check both empty string and NULL.

```php
// ❌ BAD: Misses NULL values
$wpdb->query(
    "UPDATE {$table} SET new_column = 'default' WHERE new_column = ''"
);

// ✅ GOOD: Handles NULL and empty
$wpdb->query(
    "UPDATE {$table} SET new_column = 'default' WHERE new_column IS NULL OR new_column = ''"
);
```

---

### Pitfall 3: Large Dataset Performance

**Problem:** Backfill query locks table for minutes.

**Solution:** Batch updates or use non-blocking queries.

```php
// ❌ BAD: Locks entire table
$wpdb->query( "UPDATE {$large_table} SET new_column = 'default'" );

// ✅ GOOD: Batch updates
$batch_size = 1000;
$offset = 0;
do {
    $affected = $wpdb->query(
        "UPDATE {$large_table}
        SET new_column = 'default'
        WHERE new_column IS NULL
        LIMIT {$batch_size}"
    );
    $offset += $batch_size;
} while ( $affected > 0 );
```

---

## Rollback Strategy

**Migrations are forward-only.** If you need to rollback:

### Option 1: Database Restore

```bash
# Restore from backup
wp db import backup-before-migration.sql

# Revert plugin version
git checkout v2.2.0

# Reinstall old version
wp plugin deactivate polylang-ai-automatic-translation
wp plugin activate polylang-ai-automatic-translation
```

### Option 2: Manual Rollback Query

**Only if you know what you're doing:**

```sql
-- Remove new column
ALTER TABLE wp_pllat_jobs DROP COLUMN new_column;

-- Revert DB version
UPDATE wp_options SET option_value = '2.2.0' WHERE option_name = 'pllat_db_version';
```

---

**Last Updated:** 2025-10-24
**Version:** 1.0.0
**Status:** ✅ Production-Ready
