-- database/migrations/add_entity_tenant_card_styles.sql
-- Adds default entity and tenant card_styles rows for every tenant.
-- These drive the pub_card_inline_style('entities') and pub_card_inline_style('tenants')
-- calls in frontend/public/entities.php and frontend/public/tenants.php.
--
-- Safe to re-run (uses INSERT ... SELECT ... WHERE NOT EXISTS pattern).

-- ── Fix any existing entity-card rows whose slug/card_type were set incorrectly ──
-- (e.g. slug='eee' created manually; identified by tenant=1 + wrong slug + no proper card_type)
UPDATE `card_styles`
SET    `slug`      = 'entities-default',
       `card_type` = 'entities',
       `name`      = 'Entity Card'
WHERE  `slug` = 'eee'
  AND (`card_type` = 'product' OR `card_type` IS NULL OR `card_type` = '');

-- ── Entity Card (entities listing page) ──────────────────────────────────────
INSERT INTO `card_styles`
  (`tenant_id`, `theme_id`, `name`, `slug`, `card_type`,
   `background_color`, `text_color`, `border_color`, `border_width`, `border_radius`,
   `shadow_style`, `padding`, `hover_effect`, `text_align`, `image_aspect_ratio`,
   `is_active`, `created_at`)
SELECT t.id, NULL, 'Entity Card', 'entities-default', 'entities',
       '#ffffff', '#1e293b', '#e2e8f0', 1, 12,
       '0 2px 8px rgba(0,0,0,0.07)', '16px', 'lift', 'left', '1:1',
       1, NOW()
FROM `tenants` t
WHERE NOT EXISTS (
  SELECT 1 FROM `card_styles` cs
   WHERE cs.tenant_id = t.id AND cs.slug = 'entities-default'
);

-- ── Tenant Card (tenants listing page) ───────────────────────────────────────
INSERT INTO `card_styles`
  (`tenant_id`, `theme_id`, `name`, `slug`, `card_type`,
   `background_color`, `text_color`, `border_color`, `border_width`, `border_radius`,
   `shadow_style`, `padding`, `hover_effect`, `text_align`, `image_aspect_ratio`,
   `is_active`, `created_at`)
SELECT t.id, NULL, 'Tenant Card', 'tenants-default', 'tenants',
       '#ffffff', '#1e293b', '#e2e8f0', 1, 12,
       '0 2px 8px rgba(0,0,0,0.07)', '16px', 'lift', 'left', '1:1',
       1, NOW()
FROM `tenants` t
WHERE NOT EXISTS (
  SELECT 1 FROM `card_styles` cs
   WHERE cs.tenant_id = t.id AND cs.slug = 'tenants-default'
);

-- ── Back-fill card_type for any existing entity/tenant rows with empty type ──
UPDATE `card_styles`
SET    `card_type` = 'entities'
WHERE  `slug` = 'entities-default'
  AND (`card_type` IS NULL OR `card_type` = '');

UPDATE `card_styles`
SET    `card_type` = 'tenants'
WHERE  `slug` = 'tenants-default'
  AND (`card_type` IS NULL OR `card_type` = '');
