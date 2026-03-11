-- database/migrations/add_auction_notification_card_styles.sql
-- Extends card_type support for auction/notification/discount/jobs/plan card style rows.
--
-- This migration is idempotent and safe to re-run.
--
-- STRATEGY: Convert card_type from ENUM to VARCHAR(50) FIRST.
--   - The original ENUM only contains the legacy values; trying to UPDATE a row
--     with 'auction' before extending the ENUM fails in MySQL strict mode.
--   - Converting to VARCHAR(50) removes the DB-level constraint entirely.
--     PHP (CardStylesValidator) remains the single validation gatekeeper.
--   - This also adds `updated_at` if it does not already exist.

-- ─────────────────────────────────────────────────────────────────────────────
-- 1. Convert card_type column from ENUM to VARCHAR(50).
--    VARCHAR allows any string value including the current empty-string rows.
--    Existing rows with '' are left as-is and back-filled in step 3.
-- ─────────────────────────────────────────────────────────────────────────────
ALTER TABLE `card_styles`
  MODIFY `card_type` VARCHAR(50) NOT NULL DEFAULT '';

-- ─────────────────────────────────────────────────────────────────────────────
-- 2. Add updated_at column if it does not exist.
--    The repository UPDATE sets updated_at = NOW(); missing column → 500 error.
--    NOTE: ADD COLUMN IF NOT EXISTS requires MySQL 8.0+.
--    For MySQL 5.7 compatibility we use a stored-procedure workaround that
--    only executes the ALTER when the column is absent.
-- ─────────────────────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS `temp_add_updated_at_to_card_styles`;

DELIMITER $$
CREATE PROCEDURE `temp_add_updated_at_to_card_styles`()
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM   information_schema.COLUMNS
    WHERE  TABLE_SCHEMA = DATABASE()
      AND  TABLE_NAME   = 'card_styles'
      AND  COLUMN_NAME  = 'updated_at'
  ) THEN
    ALTER TABLE `card_styles`
      ADD COLUMN `updated_at` DATETIME NULL DEFAULT NULL;
  END IF;
END$$
DELIMITER ;

CALL `temp_add_updated_at_to_card_styles`();
DROP PROCEDURE IF EXISTS `temp_add_updated_at_to_card_styles`;

-- ─────────────────────────────────────────────────────────────────────────────
-- 3. Back-fill card_type for rows that have an empty or null value.
--    The column is now VARCHAR so these UPDATEs are unconditionally safe.
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
-- 4. Seed one default row per new card type for tenant_id = 1.
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

-- Plan card
INSERT INTO `card_styles`
  (`tenant_id`, `theme_id`, `name`, `slug`, `card_type`,
   `background_color`, `border_color`, `border_width`, `border_radius`,
   `shadow_style`, `padding`, `hover_effect`, `text_align`, `image_aspect_ratio`,
   `is_active`, `created_at`)
SELECT 1, NULL, 'Plan Card - Default', 'plan-default', 'plan',
       '#FFFFFF', '#E0E0E0', 1, 12,
       '0 4px 16px rgba(0,0,0,0.10)', '20px', 'lift', 'center', '4:3',
       1, NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM `card_styles` WHERE `tenant_id` = 1 AND `slug` = 'plan-default'
);
