-- Migration: Fix ads component mapping and entities data_source on homepage
-- - Change homepage_sections with section_type='ads' component from 'ad_banner' to 'ad_ads'
-- - Change entities section data_source from 'entities:featured' to 'entities:verified'
--   so verified sellers show even when no entities are marked is_featured=1
--
-- Safe to re-run (uses conditional UPDATE).

-- ─────────────────────────────────────────────────────────────────────────────
-- 1. Fix ads sections: use ad_ads component instead of ad_banner
-- ─────────────────────────────────────────────────────────────────────────────
UPDATE `homepage_sections`
SET `component` = 'ad_ads'
WHERE `section_type` = 'ads'
  AND (`component` IS NULL OR `component` = 'ad_banner');

-- ─────────────────────────────────────────────────────────────────────────────
-- 2. Fix entities sections: use entities:verified data_source
--    so "Best trusted sellers" shows verified entities even when is_featured=0
-- ─────────────────────────────────────────────────────────────────────────────
UPDATE `homepage_sections`
SET `data_source` = 'entities:verified'
WHERE `section_type` = 'entities'
  AND `data_source` = 'entities:featured';
