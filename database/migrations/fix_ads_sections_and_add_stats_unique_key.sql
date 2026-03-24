-- Migration: Fix ads sections on homepage and add unique key to ad_stats for upsert support
-- 1. Adds a UNIQUE KEY on ad_stats(ad_id, date) enabling INSERT ... ON DUPLICATE KEY UPDATE
-- 2. Fixes ads section component and data_source (use all ads, not placement-filtered)
-- 3. Ensures entities section uses entities:verified (verified sellers show even without is_featured)
--
-- Safe to run multiple times (uses conditional DDL and guarded UPDATEs).

-- ─────────────────────────────────────────────────────────────────────────────
-- 1.  Add UNIQUE KEY on ad_stats(ad_id, date) if it does not already exist.
--     This enables the INSERT ... ON DUPLICATE KEY UPDATE upsert pattern used
--     by the click/view tracking API endpoints.
-- ─────────────────────────────────────────────────────────────────────────────
SET @_has_uq = (
    SELECT COUNT(1)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name   = 'ad_stats'
      AND index_name   = 'uq_ad_stats_ad_date'
);
SET @_sql = IF(
    @_has_uq = 0,
    'ALTER TABLE `ad_stats` ADD UNIQUE KEY `uq_ad_stats_ad_date` (`ad_id`, `date`)',
    'SELECT 1 -- uq_ad_stats_ad_date already exists'
);
PREPARE _stmt FROM @_sql;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

-- ─────────────────────────────────────────────────────────────────────────────
-- 2.  Fix ads sections on homepage:
--     a) Ensure component = 'ad_ads'  (not 'ad_banner' or NULL)
--     b) Ensure data_source = 'ads'   (not 'ads:homepage' which requires a
--        placement with key='homepage' to exist — use no filter so all active
--        ads are shown regardless of placement key)
-- ─────────────────────────────────────────────────────────────────────────────
UPDATE `homepage_sections`
SET `component`   = 'ad_ads',
    `data_source` = 'ads'
WHERE `section_type` = 'ads'
  AND (`component` != 'ad_ads' OR `component` IS NULL OR `data_source` LIKE 'ads:%');

-- ─────────────────────────────────────────────────────────────────────────────
-- 3.  Fix entities section: change data_source to 'entities:verified' so
--     "أفضل البائعين الموثوقين" shows verified entities.
--     The getSectionData() function already falls back to all entities if
--     entities:verified returns empty.
-- ─────────────────────────────────────────────────────────────────────────────
UPDATE `homepage_sections`
SET `data_source` = 'entities:verified'
WHERE `section_type` = 'entities'
  AND `data_source` IN ('entities', 'entities:featured');

-- ─────────────────────────────────────────────────────────────────────────────
-- 4.  If no ads section exists yet for tenant 1, insert one.
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO `homepage_sections`
    (`tenant_id`, `section_type`, `component`, `title`, `subtitle`,
     `layout_type`, `items_per_row`, `background_color`, `text_color`,
     `data_source`, `is_active`, `sort_order`, `created_at`)
SELECT
    1, 'ads', 'ad_ads', 'إعلانات', '',
    'grid', 4, 'var(--pub-surface)', 'var(--pub-text)',
    'ads', 1, 25, NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `homepage_sections`
    WHERE `tenant_id` = 1 AND `section_type` = 'ads'
    LIMIT 1
);

-- Add English translation for the ads section (if missing)
INSERT INTO `homepage_section_translations`
    (`section_id`, `language_code`, `title`, `subtitle`)
SELECT hs.id, 'en', 'Advertisements', ''
FROM `homepage_sections` hs
WHERE hs.tenant_id = 1 AND hs.section_type = 'ads'
  AND NOT EXISTS (
      SELECT 1 FROM `homepage_section_translations` hst
      WHERE hst.section_id = hs.id AND hst.language_code = 'en'
  )
LIMIT 1;

-- ─────────────────────────────────────────────────────────────────────────────
-- 5.  If no entities section exists yet for tenant 1, insert one.
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO `homepage_sections`
    (`tenant_id`, `section_type`, `component`, `title`, `subtitle`,
     `layout_type`, `items_per_row`, `background_color`, `text_color`,
     `data_source`, `is_active`, `sort_order`, `created_at`)
SELECT
    1, 'entities', 'ad_entities', 'أفضل البائعين الموثوقين', 'بائعون تم التحقق منهم',
    'grid', 4, 'var(--pub-surface)', 'var(--pub-text)',
    'entities:verified', 1, 60, NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `homepage_sections`
    WHERE `tenant_id` = 1 AND `section_type` = 'entities'
    LIMIT 1
);

-- Add English translation for the entities section (if missing)
INSERT INTO `homepage_section_translations`
    (`section_id`, `language_code`, `title`, `subtitle`)
SELECT hs.id, 'en', 'Top Trusted Sellers', 'Verified vendors'
FROM `homepage_sections` hs
WHERE hs.tenant_id = 1 AND hs.section_type = 'entities'
  AND NOT EXISTS (
      SELECT 1 FROM `homepage_section_translations` hst
      WHERE hst.section_id = hs.id AND hst.language_code = 'en'
  )
LIMIT 1;
