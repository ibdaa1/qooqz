-- Migration: Add icon and color columns to image_types table
-- These allow the admin UI to render a FontAwesome icon and a CSS colour badge
-- for each image type directly from the database (no hardcoded values in JS/PHP).

ALTER TABLE `image_types`
    ADD COLUMN `icon`  VARCHAR(100) NOT NULL DEFAULT 'fa-image'  COMMENT 'FontAwesome class (e.g. fa-user, fa-box)' AFTER `is_thumbnail`,
    ADD COLUMN `color` VARCHAR(30)  NOT NULL DEFAULT '#6b7280'   COMMENT 'CSS colour string (hex or named colour)'  AFTER `icon`;

-- ────────────────────────────────────────────────────────────
-- Seed sensible defaults for the 21 existing image_types rows
-- ────────────────────────────────────────────────────────────

UPDATE `image_types` SET `icon` = 'fa-th-large',          `color` = '#8b5cf6' WHERE `code` = 'category';
UPDATE `image_types` SET `icon` = 'fa-box',               `color` = '#3b82f6' WHERE `code` = 'product';
UPDATE `image_types` SET `icon` = 'fa-image',             `color` = '#06b6d4' WHERE `code` = 'product_thumb';
UPDATE `image_types` SET `icon` = 'fa-building',          `color` = '#f59e0b' WHERE `code` = 'entity_logo';
UPDATE `image_types` SET `icon` = 'fa-store',             `color` = '#f97316' WHERE `code` = 'entity_cover';
UPDATE `image_types` SET `icon` = 'fa-id-card',           `color` = '#ef4444' WHERE `code` = 'entity_license';
UPDATE `image_types` SET `icon` = 'fa-user-circle',       `color` = '#10b981' WHERE `code` = 'avatar';
UPDATE `image_types` SET `icon` = 'fa-home',              `color` = '#14b8a6' WHERE `code` = 'homepage_section';
UPDATE `image_types` SET `icon` = 'fa-flag',              `color` = '#6366f1' WHERE `code` = 'banner';
UPDATE `image_types` SET `icon` = 'fa-images',            `color` = '#84cc16' WHERE `code` = 'gallery';
UPDATE `image_types` SET `icon` = 'fa-briefcase',         `color` = '#a855f7' WHERE `code` = 'job_categories';
UPDATE `image_types` SET `icon` = 'fa-trademark',         `color` = '#ec4899' WHERE `code` = 'brand';
UPDATE `image_types` SET `icon` = 'fa-ad',                `color` = '#0ea5e9' WHERE `code` = 'ad_homepage_banner';
UPDATE `image_types` SET `icon` = 'fa-ad',                `color` = '#0ea5e9' WHERE `code` = 'ad_section_banner';
UPDATE `image_types` SET `icon` = 'fa-ad',                `color` = '#0ea5e9' WHERE `code` = 'ad_square';
UPDATE `image_types` SET `icon` = 'fa-ad',                `color` = '#0ea5e9' WHERE `code` = 'ad_store_banner';
UPDATE `image_types` SET `icon` = 'fa-ad',                `color` = '#0ea5e9' WHERE `code` = 'ad_small';
UPDATE `image_types` SET `icon` = 'fa-ad',                `color` = '#0ea5e9' WHERE `code` = 'ad_search_banner';
UPDATE `image_types` SET `icon` = 'fa-mobile-alt',        `color` = '#0ea5e9' WHERE `code` = 'ad_mobile_banner';
UPDATE `image_types` SET `icon` = 'fa-ad',                `color` = '#0ea5e9' WHERE `code` = 'ad_thumb';
UPDATE `image_types` SET `icon` = 'fa-building',          `color` = '#f59e0b' WHERE `code` = 'tenant_logo';
