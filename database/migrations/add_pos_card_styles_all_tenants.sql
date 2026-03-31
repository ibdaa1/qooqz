-- database/migrations/add_pos_card_styles_all_tenants.sql
-- Adds default POS card_styles rows (product + category) for every tenant that
-- does not already have them.  These rows drive CSS vars --card-product-* and
-- --card-category-* consumed by admin/assets/css/pages/pos.css.
--
-- Safe to re-run (uses INSERT ... SELECT ... WHERE NOT EXISTS pattern).

-- ── POS Product Card – all tenants ───────────────────────────────────────────
INSERT INTO `card_styles`
  (`tenant_id`, `theme_id`, `name`, `slug`, `card_type`,
   `background_color`, `text_color`, `border_color`, `border_width`, `border_radius`,
   `shadow_style`, `padding`, `hover_effect`, `text_align`, `image_aspect_ratio`,
   `is_active`, `created_at`)
SELECT t.id, NULL, 'POS Product Card', 'product', 'pos_product',
       '#1e293b', '#e2e8f0', '#334155', 1, 10,
       'none', '12px 10px', 'lift', 'center', '1:1',
       1, NOW()
FROM `tenants` t
WHERE NOT EXISTS (
  SELECT 1 FROM `card_styles` cs
   WHERE cs.tenant_id = t.id AND cs.slug = 'product'
);

-- ── POS Category Tab Chip – all tenants ─────────────────────────────────────
INSERT INTO `card_styles`
  (`tenant_id`, `theme_id`, `name`, `slug`, `card_type`,
   `background_color`, `text_color`, `border_color`, `border_width`, `border_radius`,
   `shadow_style`, `padding`, `hover_effect`, `text_align`, `image_aspect_ratio`,
   `is_active`, `created_at`)
SELECT t.id, NULL, 'POS Category Tab', 'category', 'pos_category',
       'transparent', '#94a3b8', '#334155', 1, 20,
       'none', '5px 14px', 'none', 'center', NULL,
       1, NOW()
FROM `tenants` t
WHERE NOT EXISTS (
  SELECT 1 FROM `card_styles` cs
   WHERE cs.tenant_id = t.id AND cs.slug = 'category'
);
