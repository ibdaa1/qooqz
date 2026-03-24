-- Migration: Create homepage_sections and homepage_section_translations tables
-- Also seeds default sections for tenant 1 if none exist.
--
-- Run this once on any environment that does not already have these tables.

-- ─────────────────────────────────────────────────────────────────────────────
-- 1.  Main table
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `homepage_sections` (
    `id`               INT UNSIGNED    NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`        INT UNSIGNED    NOT NULL,
    `theme_id`         INT UNSIGNED    NULL,
    `section_type`     VARCHAR(50)     NOT NULL,
    `component`        VARCHAR(100)    NULL         COMMENT 'e.g. ad_products, ad_categories',
    `title`            VARCHAR(255)    NULL,
    `subtitle`         VARCHAR(255)    NULL,
    `layout_type`      VARCHAR(50)     NULL DEFAULT 'grid',
    `layout_config`    TEXT            NULL,
    `items_per_row`    TINYINT UNSIGNED NOT NULL DEFAULT 4,
    `background_color` VARCHAR(30)     NULL,
    `text_color`       VARCHAR(30)     NULL,
    `padding`          VARCHAR(50)     NULL,
    `custom_css`       TEXT            NULL,
    `custom_html`      TEXT            NULL,
    `data_source`      VARCHAR(255)    NULL         COMMENT 'e.g. products:featured, categories, deals',
    `is_active`        TINYINT(1)      NOT NULL DEFAULT 1,
    `sort_order`       SMALLINT        NOT NULL DEFAULT 0,
    `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME        NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_tenant_active` (`tenant_id`, `is_active`),
    INDEX `idx_sort`          (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- 2.  Translations table
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `homepage_section_translations` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `section_id`    INT UNSIGNED NOT NULL,
    `language_code` VARCHAR(10)  NOT NULL,
    `title`         VARCHAR(255) NULL,
    `subtitle`      VARCHAR(255) NULL,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_section_lang` (`section_id`, `language_code`),
    INDEX `idx_section` (`section_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- 3.  Add missing columns to existing tables that lack them
--     (skip if the column already exists — re-running is safe because
--      CREATE TABLE IF NOT EXISTS above will be a no-op on existing tables)
-- ─────────────────────────────────────────────────────────────────────────────

-- component
SET @col_component := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'homepage_sections'
      AND COLUMN_NAME  = 'component'
);
SET @sql_add_component := IF(
    @col_component = 0,
    'ALTER TABLE `homepage_sections` ADD COLUMN `component` VARCHAR(100) NULL COMMENT ''e.g. ad_products, ad_categories'' AFTER `section_type`',
    'SELECT 1 -- component already exists'
);
PREPARE stmt_c FROM @sql_add_component; EXECUTE stmt_c; DEALLOCATE PREPARE stmt_c;

-- layout_config
SET @col_layout_config := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'homepage_sections'
      AND COLUMN_NAME  = 'layout_config'
);
SET @sql_add_layout_config := IF(
    @col_layout_config = 0,
    'ALTER TABLE `homepage_sections` ADD COLUMN `layout_config` TEXT NULL AFTER `layout_type`',
    'SELECT 1 -- layout_config already exists'
);
PREPARE stmt_lc FROM @sql_add_layout_config; EXECUTE stmt_lc; DEALLOCATE PREPARE stmt_lc;

-- background_color
SET @col_bg := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'homepage_sections'
      AND COLUMN_NAME  = 'background_color'
);
SET @sql_add_bg := IF(
    @col_bg = 0,
    'ALTER TABLE `homepage_sections` ADD COLUMN `background_color` VARCHAR(30) NULL AFTER `items_per_row`',
    'SELECT 1 -- background_color already exists'
);
PREPARE stmt_bg FROM @sql_add_bg; EXECUTE stmt_bg; DEALLOCATE PREPARE stmt_bg;

-- text_color
SET @col_tc := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'homepage_sections'
      AND COLUMN_NAME  = 'text_color'
);
SET @sql_add_tc := IF(
    @col_tc = 0,
    'ALTER TABLE `homepage_sections` ADD COLUMN `text_color` VARCHAR(30) NULL AFTER `background_color`',
    'SELECT 1 -- text_color already exists'
);
PREPARE stmt_tc FROM @sql_add_tc; EXECUTE stmt_tc; DEALLOCATE PREPARE stmt_tc;

-- padding
SET @col_pad := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'homepage_sections'
      AND COLUMN_NAME  = 'padding'
);
SET @sql_add_pad := IF(
    @col_pad = 0,
    'ALTER TABLE `homepage_sections` ADD COLUMN `padding` VARCHAR(50) NULL AFTER `text_color`',
    'SELECT 1 -- padding already exists'
);
PREPARE stmt_pad FROM @sql_add_pad; EXECUTE stmt_pad; DEALLOCATE PREPARE stmt_pad;

-- custom_css
SET @col_css := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'homepage_sections'
      AND COLUMN_NAME  = 'custom_css'
);
SET @sql_add_css := IF(
    @col_css = 0,
    'ALTER TABLE `homepage_sections` ADD COLUMN `custom_css` TEXT NULL AFTER `padding`',
    'SELECT 1 -- custom_css already exists'
);
PREPARE stmt_css FROM @sql_add_css; EXECUTE stmt_css; DEALLOCATE PREPARE stmt_css;

-- data_source
SET @col_ds := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'homepage_sections'
      AND COLUMN_NAME  = 'data_source'
);
SET @sql_add_ds := IF(
    @col_ds = 0,
    'ALTER TABLE `homepage_sections` ADD COLUMN `data_source` VARCHAR(255) NULL COMMENT ''e.g. products:featured, categories, deals''',
    'SELECT 1 -- data_source already exists'
);
PREPARE stmt_ds FROM @sql_add_ds; EXECUTE stmt_ds; DEALLOCATE PREPARE stmt_ds;

-- ─────────────────────────────────────────────────────────────────────────────
-- 4.  Populate component from section_type for rows that have it NULL
-- ─────────────────────────────────────────────────────────────────────────────
UPDATE `homepage_sections`
SET `component` = CASE `section_type`
    WHEN 'categories' THEN 'ad_categories'
    WHEN 'products'   THEN 'ad_products'
    WHEN 'deals'      THEN 'ad_deals'
    WHEN 'entities'   THEN 'ad_entities'
    WHEN 'jobs'       THEN 'ad_jobs'
    WHEN 'tenants'    THEN 'ad_tenants'
    WHEN 'slider'     THEN 'ad_slider'
    WHEN 'banners'    THEN 'ad_slider'
    WHEN 'banner'     THEN 'ad_banner'
    WHEN 'search'     THEN 'ad_search'
    WHEN 'stats'      THEN 'ad_stats'
    WHEN 'custom'     THEN 'ad_custom'
    WHEN 'native'     THEN 'ad_native'
    WHEN 'ads'        THEN 'ad_banner'
    ELSE 'default'
END
WHERE `component` IS NULL;

-- ─────────────────────────────────────────────────────────────────────────────
-- 4b. Backfill default background_color and text_color for existing rows
--     that have no background color set yet.
--     NOTE: Because section_type is not unique per tenant, the backfill uses
--     a simple per-type default (#ffffff for products regardless of which
--     section it is). Existing rows with intentionally NULL background_color
--     will also be updated — adjust manually if custom NULL values are needed.
-- ─────────────────────────────────────────────────────────────────────────────
UPDATE `homepage_sections`
SET
    `background_color` = CASE `section_type`
        WHEN 'categories' THEN '#f9fafb'
        WHEN 'products'   THEN '#ffffff'
        WHEN 'deals'      THEN '#fff7ed'
        WHEN 'entities'   THEN '#f5f3ff'
        WHEN 'jobs'       THEN '#f0f9ff'
        WHEN 'tenants'    THEN '#fafaf9'
        WHEN 'slider'     THEN '#ffffff'
        WHEN 'banners'    THEN '#ffffff'
        ELSE '#ffffff'
    END,
    `text_color` = '#1f2937'
WHERE `background_color` IS NULL;

-- ─────────────────────────────────────────────────────────────────────────────
-- 5.  Seed default homepage sections for tenant 1
--     (only inserts if tenant 1 has NO sections at all)
--     Note: the two 'products' sections intentionally use different background
--     colors (#ffffff vs #f0fdf4) to provide visual variety. The backfill in
--     step 4b uses #ffffff for all products sections since it cannot
--     distinguish between them by type alone.
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO `homepage_sections`
    (`tenant_id`, `section_type`, `component`, `title`, `subtitle`,
     `layout_type`, `items_per_row`, `background_color`, `text_color`,
     `data_source`, `is_active`, `sort_order`, `created_at`)
SELECT
    1               AS tenant_id,
    v.section_type,
    v.component,
    v.title,
    v.subtitle,
    'grid'          AS layout_type,
    v.items_per_row,
    v.background_color,
    v.text_color,
    v.data_source,
    1               AS is_active,
    v.sort_order,
    NOW()           AS created_at
FROM (
    SELECT 'categories' AS section_type, 'ad_categories' AS component,
           'تسوق حسب الفئة'         AS title,
           'تصفح التصنيفات الممولة' AS subtitle,
           6  AS items_per_row, '#f9fafb' AS background_color, '#1f2937' AS text_color,
           'categories'         AS data_source, 10 AS sort_order
    UNION ALL
    SELECT 'products', 'ad_products',
           'منتجات ممولة', 'منتجات مدفوعة للظهور',
           4, '#ffffff', '#1f2937', 'products:featured', 20
    UNION ALL
    SELECT 'deals', 'ad_deals',
           'عرض خاص', 'لفترة محدودة',
           3, '#fff7ed', '#1f2937', 'deals', 30
    UNION ALL
    SELECT 'products', 'ad_products',
           'مقترحات لك', 'بناءً على اهتماماتك',
           4, '#f0fdf4', '#1f2937', 'products', 40
    UNION ALL
    SELECT 'deals', 'ad_deals',
           'عروض ساخنة', 'أفضل العروض الآن',
           3, '#fef2f2', '#1f2937', 'deals', 50
    UNION ALL
    SELECT 'entities', 'ad_entities',
           'بائعون مميزون', 'أفضل البائعين الموثوقين',
           4, '#f5f3ff', '#1f2937', 'entities:featured', 60
) AS v
WHERE NOT EXISTS (
    SELECT 1 FROM `homepage_sections` WHERE `tenant_id` = 1 LIMIT 1
);

-- ─────────────────────────────────────────────────────────────────────────────
-- 6.  English translations for the seeded sections
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO `homepage_section_translations`
    (`section_id`, `language_code`, `title`, `subtitle`)
SELECT
    hs.id,
    'en',
    CASE hs.sort_order
        WHEN 10 THEN 'Shop by Category'
        WHEN 20 THEN 'Sponsored Products'
        WHEN 30 THEN 'Special Offers'
        WHEN 40 THEN 'Suggestions for You'
        WHEN 50 THEN 'Hot Deals'
        WHEN 60 THEN 'Featured Sellers'
    END,
    CASE hs.sort_order
        WHEN 10 THEN 'Browse featured categories'
        WHEN 20 THEN 'Paid to appear products'
        WHEN 30 THEN 'Limited time offers'
        WHEN 40 THEN 'Based on your interests'
        WHEN 50 THEN 'Best deals now'
        WHEN 60 THEN 'Top trusted sellers'
    END
FROM `homepage_sections` hs
WHERE hs.tenant_id = 1
  AND hs.sort_order IN (10, 20, 30, 40, 50, 60)
  AND NOT EXISTS (
        SELECT 1 FROM `homepage_section_translations` hst
        WHERE hst.section_id = hs.id AND hst.language_code = 'en'
  );
