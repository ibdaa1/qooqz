-- database/migrations/add_auction_notification_card_styles.sql
-- Extends the card_type enum to support auction/notification/discount/jobs types
-- and seeds default (theme-agnostic, theme_id = NULL) card style rows for each.
--
-- This migration is idempotent and safe to re-run.
-- IMPORTANT: back-fill UPDATEs run FIRST so that empty card_type rows are fixed
-- before the ENUM is extended (avoids strict-mode failures on ALTER TABLE).

-- ─────────────────────────────────────────────────────────────────────────────
-- 1. Back-fill card_type for rows already in the DB with an empty/null value.
--    Uses slug prefix to determine the correct type.
--    Must run BEFORE the ALTER TABLE so there are no invalid-enum rows left.
-- ─────────────────────────────────────────────────────────────────────────────
UPDATE `card_styles`
SET    `card_type` = 'auction'
WHERE  `slug` LIKE 'auction-%'
  AND  (`card_type` IS NULL OR `card_type` = '');

UPDATE `card_styles`
SET    `card_type` = 'notification'
WHERE  `slug` LIKE 'notification-%'
  AND  (`card_type` IS NULL OR `card_type` = '');

UPDATE `card_styles`
SET    `card_type` = 'discount'
WHERE  `slug` LIKE 'discount-%'
  AND  (`card_type` IS NULL OR `card_type` = '');

UPDATE `card_styles`
SET    `card_type` = 'jobs'
WHERE  `slug` LIKE 'jobs-%'
  AND  (`card_type` IS NULL OR `card_type` = '');

UPDATE `card_styles`
SET    `card_type` = 'plan'
WHERE  `slug` LIKE 'plan-%'
  AND  (`card_type` IS NULL OR `card_type` = '');

-- ─────────────────────────────────────────────────────────────────────────────
-- 2. Extend the card_type ENUM (safe now — no more empty rows)
--    Original: enum('product','category','vendor','blog','feature','testimonial','other')
--    After:    adds 'auction','notification','discount','jobs'
-- ─────────────────────────────────────────────────────────────────────────────
ALTER TABLE `card_styles`
  MODIFY `card_type` ENUM(
    'product', 'category', 'vendor', 'blog', 'feature', 'testimonial', 'other',
    'auction', 'notification', 'discount', 'jobs'
  ) NOT NULL;

-- ─────────────────────────────────────────────────────────────────────────────
-- 3. Seed one default row per new card type for tenant_id = 1.
--    Uses INSERT ... WHERE NOT EXISTS to avoid duplicate rows even when there
--    is no UNIQUE constraint on (tenant_id, slug).
-- ─────────────────────────────────────────────────────────────────────────────

-- Auction card
INSERT INTO `card_styles`
  (`tenant_id`, `theme_id`, `name`, `slug`, `card_type`,
   `background_color`, `border_color`, `border_width`, `border_radius`,
   `shadow_style`, `padding`, `hover_effect`, `text_align`, `image_aspect_ratio`,
   `is_active`, `created_at`)
SELECT 1, NULL, 'Auction Card - Default', 'auction-default', 'auction',
       '#FFFFFF', '#E0E0E0', 1, 12,
       '0 4px 16px rgba(0,0,0,0.10)', '16px', 'lift', 'left', '4:3',
       1, NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM `card_styles` WHERE `tenant_id` = 1 AND `slug` = 'auction-default'
);

-- Notification card
INSERT INTO `card_styles`
  (`tenant_id`, `theme_id`, `name`, `slug`, `card_type`,
   `background_color`, `border_color`, `border_width`, `border_radius`,
   `shadow_style`, `padding`, `hover_effect`, `text_align`, `image_aspect_ratio`,
   `is_active`, `created_at`)
SELECT 1, NULL, 'Notification Card - Default', 'notification-default', 'notification',
       '#FFFFFF', '#E0E0E0', 1, 10,
       '0 2px 8px rgba(0,0,0,0.08)', '14px', 'shadow', 'left', '1:1',
       1, NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM `card_styles` WHERE `tenant_id` = 1 AND `slug` = 'notification-default'
);

-- Discount card
INSERT INTO `card_styles`
  (`tenant_id`, `theme_id`, `name`, `slug`, `card_type`,
   `background_color`, `border_color`, `border_width`, `border_radius`,
   `shadow_style`, `padding`, `hover_effect`, `text_align`, `image_aspect_ratio`,
   `is_active`, `created_at`)
SELECT 1, NULL, 'Discount Card - Default', 'discount-default', 'discount',
       '#FFFFFF', '#E0E0E0', 1, 12,
       '0 4px 16px rgba(0,0,0,0.10)', '16px', 'lift', 'left', '1:1',
       1, NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM `card_styles` WHERE `tenant_id` = 1 AND `slug` = 'discount-default'
);

-- Jobs card
INSERT INTO `card_styles`
  (`tenant_id`, `theme_id`, `name`, `slug`, `card_type`,
   `background_color`, `border_color`, `border_width`, `border_radius`,
   `shadow_style`, `padding`, `hover_effect`, `text_align`, `image_aspect_ratio`,
   `is_active`, `created_at`)
SELECT 1, NULL, 'Jobs Card - Default', 'jobs-default', 'jobs',
       '#FFFFFF', '#E0E0E0', 1, 10,
       '0 2px 8px rgba(0,0,0,0.08)', '16px', 'lift', 'left', '16:9',
       1, NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM `card_styles` WHERE `tenant_id` = 1 AND `slug` = 'jobs-default'
);
