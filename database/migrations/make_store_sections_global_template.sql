-- ============================================================
-- MAKE STORE SECTIONS GLOBAL TEMPLATE
-- ============================================================
-- Changes store_pages from per-entity to per-tenant (global template).
-- Sections become a fixed template shared by ALL entities in a tenant.
-- ============================================================

-- 1. Drop the per-entity unique key
ALTER TABLE store_pages DROP INDEX uq_store_pages_entity;

-- 2. Drop the entity_id index
ALTER TABLE store_pages DROP INDEX idx_store_pages_entity;

-- 3. Remove the entity_id column
ALTER TABLE store_pages DROP COLUMN entity_id;

-- 4. Add new unique key: one page per tenant + type
ALTER TABLE store_pages ADD UNIQUE KEY uq_store_pages_tenant_type (tenant_id, type);

-- ============================================================
-- SEED: Insert default global template for tenant 1 (if not exists)
-- ============================================================
INSERT IGNORE INTO store_pages (tenant_id, type, is_active) VALUES (1, 'store', 1);

SET @page_id = (SELECT id FROM store_pages WHERE tenant_id = 1 AND type = 'store' LIMIT 1);

INSERT IGNORE INTO store_sections (page_id, type, position, is_active, settings) VALUES
  (@page_id, 'header',   10, 1, '{"show_cover": true, "show_rating": true, "show_verified": true, "show_status": true}'),
  (@page_id, 'contact',  20, 1, '{"show_phone": true, "show_email": true, "show_website": true, "show_share": true, "show_social": true}'),
  (@page_id, 'tabs',     30, 1, '{"tabs": ["products","info","hours","location","offers","reviews","policies"]}'),
  (@page_id, 'products', 40, 1, '{"per_page": 12, "show_categories": true, "show_search": true, "show_cart": true}'),
  (@page_id, 'info',     50, 1, '{"show_description": true, "show_attributes": true, "show_payment_methods": true, "show_settings": true}'),
  (@page_id, 'hours',    60, 1, '{}'),
  (@page_id, 'location', 70, 1, '{"show_osm": true, "show_google": true}'),
  (@page_id, 'offers',   80, 1, '{}'),
  (@page_id, 'reviews',  90, 1, '{"show_form": true, "limit": 5}'),
  (@page_id, 'policies', 95, 1, '{"types": ["refund", "privacy", "shipping", "terms"]}');
