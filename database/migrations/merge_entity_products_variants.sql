-- =====================================================
-- Merge entity_product_variants INTO entity_products
-- After this migration, entity_products stores BOTH
-- product-level (variant_id IS NULL) and variant-level records
-- =====================================================

-- Step 1: Add variant columns to entity_products
ALTER TABLE entity_products
    ADD COLUMN variant_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER product_id,
    ADD COLUMN manage_stock TINYINT(1) NOT NULL DEFAULT 1 AFTER low_stock_threshold,
    ADD COLUMN stock_status ENUM('in_stock', 'out_of_stock', 'unlimited') NOT NULL DEFAULT 'in_stock' AFTER manage_stock;

-- Step 2: Add index on variant_id
ALTER TABLE entity_products
    ADD KEY idx_variant (variant_id);

-- Step 3: Drop old unique key and add new one that includes variant_id
-- MySQL treats NULL as distinct in UNIQUE, so product-level rows (variant_id=NULL)
-- are each unique on their own. For variant rows we add a separate unique constraint.
ALTER TABLE entity_products
    DROP KEY uq_entity_product;

-- Product-level uniqueness: one row per (entity, product) where variant_id IS NULL
-- enforced in application layer since MySQL can't do conditional unique
-- Variant-level uniqueness: one row per (entity, variant)
ALTER TABLE entity_products
    ADD UNIQUE KEY uq_entity_variant (entity_id, variant_id);

-- Step 4: Migrate data from entity_product_variants into entity_products
INSERT INTO entity_products (tenant_id, entity_id, product_id, variant_id, stock_quantity, low_stock_threshold, manage_stock, stock_status, is_active, is_featured, created_at, updated_at)
SELECT tenant_id, entity_id, product_id, variant_id, stock_quantity, low_stock_threshold, manage_stock, stock_status, is_active, is_featured, created_at, updated_at
FROM entity_product_variants
ON DUPLICATE KEY UPDATE
    stock_quantity = VALUES(stock_quantity),
    low_stock_threshold = VALUES(low_stock_threshold),
    manage_stock = VALUES(manage_stock),
    stock_status = VALUES(stock_status),
    is_active = VALUES(is_active),
    is_featured = VALUES(is_featured);

-- Step 5: Drop old table (can be done after verifying migration)
-- DROP TABLE IF EXISTS entity_product_variants;
