-- database/migrations/add_auction_notification_card_styles.sql
-- Extends the card_type enum to support auction/notification/discount/jobs types
-- and seeds default (theme-agnostic, theme_id = NULL) card style rows for each.
--
-- This migration is idempotent: INSERT statements use INSERT IGNORE so running
-- the file a second time is safe.

-- ─────────────────────────────────────────────────────────────────────────────
-- 1. Extend the card_type ENUM
--    Original: enum('product','category','vendor','blog','feature','testimonial','other')
--    After:    adds 'auction','notification','discount','jobs'
-- ─────────────────────────────────────────────────────────────────────────────
ALTER TABLE `card_styles`
  MODIFY `card_type` ENUM(
    'product', 'category', 'vendor', 'blog', 'feature', 'testimonial', 'other',
    'auction', 'notification', 'discount', 'jobs'
  ) NOT NULL;

-- ─────────────────────────────────────────────────────────────────────────────
-- 2. Seed one default row per new card type for tenant_id = 1
--    theme_id = NULL means "applies to all themes for this tenant"
--    (pub_load_theme() now queries: AND (theme_id = ? OR theme_id IS NULL))
-- ─────────────────────────────────────────────────────────────────────────────

-- Auction card
INSERT IGNORE INTO `card_styles`
  (`tenant_id`, `theme_id`, `name`, `slug`, `card_type`,
   `background_color`, `border_color`, `border_width`, `border_radius`,
   `shadow_style`, `padding`, `hover_effect`, `text_align`, `image_aspect_ratio`,
   `is_active`, `created_at`)
VALUES
  (1, NULL, 'Auction Card - Default', 'auction-default', 'auction',
   '#FFFFFF', '#E0E0E0', 1, 12,
   '0 4px 16px rgba(0,0,0,0.10)', '16px', 'lift', 'left', '4:3',
   1, NOW());

-- Notification card
INSERT IGNORE INTO `card_styles`
  (`tenant_id`, `theme_id`, `name`, `slug`, `card_type`,
   `background_color`, `border_color`, `border_width`, `border_radius`,
   `shadow_style`, `padding`, `hover_effect`, `text_align`, `image_aspect_ratio`,
   `is_active`, `created_at`)
VALUES
  (1, NULL, 'Notification Card - Default', 'notification-default', 'notification',
   '#FFFFFF', '#E0E0E0', 1, 10,
   '0 2px 8px rgba(0,0,0,0.08)', '14px', 'shadow', 'left', '1:1',
   1, NOW());

-- Discount card
INSERT IGNORE INTO `card_styles`
  (`tenant_id`, `theme_id`, `name`, `slug`, `card_type`,
   `background_color`, `border_color`, `border_width`, `border_radius`,
   `shadow_style`, `padding`, `hover_effect`, `text_align`, `image_aspect_ratio`,
   `is_active`, `created_at`)
VALUES
  (1, NULL, 'Discount Card - Default', 'discount-default', 'discount',
   '#FFFFFF', '#E0E0E0', 1, 12,
   '0 4px 16px rgba(0,0,0,0.10)', '16px', 'lift', 'left', '1:1',
   1, NOW());

-- Jobs card
INSERT IGNORE INTO `card_styles`
  (`tenant_id`, `theme_id`, `name`, `slug`, `card_type`,
   `background_color`, `border_color`, `border_width`, `border_radius`,
   `shadow_style`, `padding`, `hover_effect`, `text_align`, `image_aspect_ratio`,
   `is_active`, `created_at`)
VALUES
  (1, NULL, 'Jobs Card - Default', 'jobs-default', 'jobs',
   '#FFFFFF', '#E0E0E0', 1, 10,
   '0 2px 8px rgba(0,0,0,0.08)', '16px', 'lift', 'left', '16:9',
   1, NOW());

-- ─────────────────────────────────────────────────────────────────────────────
-- 3. Back-fill card_type for rows that were previously inserted without it
--    (safe to re-run — only updates rows where card_type is still empty)
-- ─────────────────────────────────────────────────────────────────────────────
UPDATE `card_styles`
SET    `card_type` = 'auction'
WHERE  `slug` = 'auction-default'
  AND (`card_type` IS NULL OR `card_type` = '');

UPDATE `card_styles`
SET    `card_type` = 'notification'
WHERE  `slug` = 'notification-default'
  AND (`card_type` IS NULL OR `card_type` = '');

UPDATE `card_styles`
SET    `card_type` = 'discount'
WHERE  `slug` = 'discount-default'
  AND (`card_type` IS NULL OR `card_type` = '');

UPDATE `card_styles`
SET    `card_type` = 'jobs'
WHERE  `slug` = 'jobs-default'
  AND (`card_type` IS NULL OR `card_type` = '');
