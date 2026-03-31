-- database/migrations/add_pos_product_category_card_styles.sql
-- Adds default card style rows for POS product cards and category tab chips.
-- These rows generate CSS variables --card-product-* and --card-category-*
-- consumed by admin/assets/css/pages/pos.css.
--
-- Safe to re-run (uses INSERT ... WHERE NOT EXISTS pattern).

-- ── Ensure card_type column accepts 'pos_product' and 'pos_category' ─────────
-- card_type was already converted to VARCHAR(50) in add_auction_notification_card_styles.sql
-- so no schema change is needed here.

-- ── POS Product Card – Tenant 1 ───────────────────────────────────────────────
INSERT INTO `card_styles`
  (`tenant_id`, `theme_id`, `name`, `slug`, `card_type`,
   `background_color`, `text_color`, `border_color`, `border_width`, `border_radius`,
   `shadow_style`, `padding`, `hover_effect`, `text_align`, `image_aspect_ratio`,
   `is_active`, `created_at`)
SELECT 1, NULL, 'POS Product Card', 'product', 'pos_product',
       '#1e293b', '#e2e8f0', '#334155', 1, 10,
       'none', '12px 10px', 'lift', 'center', '1:1',
       1, NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM `card_styles` WHERE `tenant_id` = 1 AND `slug` = 'product'
);

-- ── POS Category Tab Chip – Tenant 1 ─────────────────────────────────────────
INSERT INTO `card_styles`
  (`tenant_id`, `theme_id`, `name`, `slug`, `card_type`,
   `background_color`, `text_color`, `border_color`, `border_width`, `border_radius`,
   `shadow_style`, `padding`, `hover_effect`, `text_align`, `image_aspect_ratio`,
   `is_active`, `created_at`)
SELECT 1, NULL, 'POS Category Tab', 'category', 'pos_category',
       'transparent', '#94a3b8', '#334155', 1, 20,
       'none', '5px 14px', 'none', 'center', NULL,
       1, NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM `card_styles` WHERE `tenant_id` = 1 AND `slug` = 'category'
);
