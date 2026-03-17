-- Migration: Add tenant_id to ad_placements and create ad_placement_items table
-- Fixes: SQLSTATE[42S22] Column not found: tenant_id in INSERT INTO ad_placements
-- Required by the ads admin module for multi-tenant placement management.
--
-- This migration is idempotent (safe to run multiple times).

-- Disable FK checks so we can backfill tenant_id before the FK constraint is added.
SET FOREIGN_KEY_CHECKS = 0;

-- Step 1: Create ad_placements table with tenant_id if it does not already exist.
CREATE TABLE IF NOT EXISTS ad_placements (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id     INT UNSIGNED NOT NULL,
    name          VARCHAR(255) NOT NULL,
    placement_key VARCHAR(100) NOT NULL,
    description   TEXT NULL,
    status        ENUM('active','inactive') DEFAULT 'active',
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_tenant_placement_key (tenant_id, placement_key)
);

-- Step 2: If the table already existed without tenant_id, add the column.
--         We use a temporary NULL default so the statement succeeds even when
--         existing rows are present; the column is made NOT NULL after backfill.
ALTER TABLE ad_placements
    ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED NULL AFTER id;

-- Step 3: Backfill tenant_id to the smallest existing tenant id for any NULL rows.
--         This is only relevant when the table had rows before the migration.
UPDATE ad_placements ap
SET    ap.tenant_id = (SELECT MIN(id) FROM tenants)
WHERE  ap.tenant_id IS NULL;

-- Step 4: Enforce NOT NULL now that all rows have a value.
ALTER TABLE ad_placements
    MODIFY COLUMN tenant_id INT UNSIGNED NOT NULL;

-- Step 5: Add the FK to tenants (safe – ignored if the constraint already exists).
ALTER TABLE ad_placements
    ADD CONSTRAINT IF NOT EXISTS fk_ad_placements_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE;

-- Step 6: Create ad_placement_items table if it does not already exist.
CREATE TABLE IF NOT EXISTS ad_placement_items (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    placement_id BIGINT UNSIGNED NOT NULL,
    ad_id        BIGINT UNSIGNED NOT NULL,
    priority     INT DEFAULT 1,
    weight       INT DEFAULT 1,
    start_date   DATETIME NULL,
    end_date     DATETIME NULL,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (placement_id) REFERENCES ad_placements(id) ON DELETE CASCADE,
    FOREIGN KEY (ad_id)        REFERENCES ads(id)            ON DELETE CASCADE,
    UNIQUE KEY uniq_ad_placement (placement_id, ad_id)
);

-- Re-enable FK checks.
SET FOREIGN_KEY_CHECKS = 1;
