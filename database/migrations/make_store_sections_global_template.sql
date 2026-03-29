-- ============================================================
-- MAKE STORE SECTIONS GLOBAL TEMPLATE
-- ============================================================
-- Changes store_pages from per-entity to per-tenant (global template).
-- Sections become a fixed template shared by ALL entities in a tenant.
-- ============================================================

-- 1. Consolidate: keep only ONE row per (tenant_id, type) — the one with the lowest id
--    Delete duplicate rows that would violate the new unique constraint.
DELETE sp2 FROM store_pages sp2
  INNER JOIN (
    SELECT MIN(id) AS keep_id, tenant_id, type
      FROM store_pages
     GROUP BY tenant_id, type
  ) sp_keep
  ON sp2.tenant_id = sp_keep.tenant_id AND sp2.type = sp_keep.type AND sp2.id != sp_keep.keep_id;

-- 2. Drop the per-entity unique key
ALTER TABLE store_pages DROP INDEX uq_store_pages_entity;

-- 3. Drop the entity_id index
ALTER TABLE store_pages DROP INDEX idx_store_pages_entity;

-- 4. Remove the entity_id column
ALTER TABLE store_pages DROP COLUMN entity_id;

-- 5. Add new unique key: one page per tenant + type
ALTER TABLE store_pages ADD UNIQUE KEY uq_store_pages_tenant_type (tenant_id, type);
