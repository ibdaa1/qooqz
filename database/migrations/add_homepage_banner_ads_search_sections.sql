-- Migration: Add search, slider/banner, and ads sections to default homepage
-- Also updates existing section background_color to use theme CSS variables
-- so the page background is driven by the active theme, not hardcoded hex values.
--
-- Safe to run multiple times (all inserts are guarded by NOT EXISTS checks).

-- ─────────────────────────────────────────────────────────────────────────────
-- 1.  Add search section (rendered via ad_search component) at top of page
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO `homepage_sections`
    (`tenant_id`, `section_type`, `component`, `title`, `subtitle`,
     `layout_type`, `items_per_row`, `background_color`, `text_color`,
     `data_source`, `is_active`, `sort_order`, `created_at`)
SELECT
    1, 'search', 'ad_search', '', '',
    'full', 1, 'var(--pub-surface)', 'var(--pub-text)',
    'search', 1, 2, NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `homepage_sections`
    WHERE `tenant_id` = 1 AND `section_type` = 'search'
    LIMIT 1
);

-- ─────────────────────────────────────────────────────────────────────────────
-- 2.  Add banner/slider section (ad_slider component) just below search
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO `homepage_sections`
    (`tenant_id`, `section_type`, `component`, `title`, `subtitle`,
     `layout_type`, `items_per_row`, `background_color`, `text_color`,
     `data_source`, `is_active`, `sort_order`, `created_at`)
SELECT
    1, 'slider', 'ad_slider', '', '',
    'full', 1, 'var(--pub-bg)', 'var(--pub-text)',
    'banners', 1, 5, NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `homepage_sections`
    WHERE `tenant_id` = 1 AND `section_type` IN ('slider', 'banners')
    LIMIT 1
);

-- ─────────────────────────────────────────────────────────────────────────────
-- 3.  Add ads section (ad_banner component) between products sections
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO `homepage_sections`
    (`tenant_id`, `section_type`, `component`, `title`, `subtitle`,
     `layout_type`, `items_per_row`, `background_color`, `text_color`,
     `data_source`, `is_active`, `sort_order`, `created_at`)
SELECT
    1, 'ads', 'ad_ads', 'إعلانات', '',
    'full', 1, 'var(--pub-surface)', 'var(--pub-text)',
    'ads:homepage', 1, 25, NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `homepage_sections`
    WHERE `tenant_id` = 1 AND `section_type` = 'ads'
    LIMIT 1
);

-- Add English title for Ads section
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
-- 4.  Update existing section background_color values to use theme CSS variables
--     so the page background is driven by the active theme colours.
--     var(--pub-bg)      → main page/body background (from theme)
--     var(--pub-surface) → card/surface background (slightly offset from bg)
--     var(--pub-text)    → primary text colour (from theme)
-- ─────────────────────────────────────────────────────────────────────────────
UPDATE `homepage_sections`
SET `background_color` = CASE
    -- Plain white → main theme background
    WHEN `background_color` = '#ffffff' THEN 'var(--pub-bg)'
    -- Neutral light grays → surface background
    WHEN `background_color` IN ('#f9fafb', '#fafaf9', '#f8f9fa') THEN 'var(--pub-surface)'
    -- Tinted light backgrounds (deals, entities, jobs, products variants) → surface
    WHEN `background_color` IN ('#fff7ed', '#fef2f2', '#f5f3ff', '#f0fdf4', '#f0f9ff') THEN 'var(--pub-surface)'
    -- Already a CSS variable or other value → keep as-is
    ELSE `background_color`
END,
`text_color` = CASE
    WHEN `text_color` = '#1f2937' THEN 'var(--pub-text)'
    ELSE `text_color`
END
WHERE `tenant_id` = 1;
