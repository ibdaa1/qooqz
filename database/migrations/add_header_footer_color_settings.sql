-- Migration: Add header and footer color settings
-- Date: 2026-03-03
-- Description: Adds dedicated header_background, header_text, footer_background,
--              footer_text color settings so header and footer colors can be
--              configured independently from the main background color.
--
-- Run this once per tenant/theme that exists in your database.
-- Adjust theme_id and tenant_id values as needed for your environment.

-- ── Theme 1 / Tenant 1 ──────────────────────────────────────────────────────

INSERT INTO `color_settings`
    (`theme_id`, `setting_key`, `setting_name`, `color_value`, `category`, `is_active`, `sort_order`, `tenant_id`)
VALUES
    (1, 'header_background', 'Header Background', '#1e2533', 'header', 1, 100, 1),
    (1, 'header_text',       'Header Text',       '#FFFFFF',  'header', 1, 101, 1),
    (1, 'footer_background', 'Footer Background', '#1e2533', 'footer', 1, 500, 1),
    (1, 'footer_text',       'Footer Text',       '#B0B0B0',  'footer', 1, 501, 1)
ON DUPLICATE KEY UPDATE
    `setting_name` = VALUES(`setting_name`),
    `color_value`  = VALUES(`color_value`),
    `category`     = VALUES(`category`),
    `is_active`    = VALUES(`is_active`),
    `sort_order`   = VALUES(`sort_order`);

-- ── Theme 2 / Tenant 1 (if applicable) ─────────────────────────────────────

INSERT INTO `color_settings`
    (`theme_id`, `setting_key`, `setting_name`, `color_value`, `category`, `is_active`, `sort_order`, `tenant_id`)
VALUES
    (2, 'header_background', 'Header Background', '#1e2533', 'header', 1, 100, 1),
    (2, 'header_text',       'Header Text',       '#FFFFFF',  'header', 1, 101, 1),
    (2, 'footer_background', 'Footer Background', '#1e2533', 'footer', 1, 500, 1),
    (2, 'footer_text',       'Footer Text',       '#B0B0B0',  'footer', 1, 501, 1)
ON DUPLICATE KEY UPDATE
    `setting_name` = VALUES(`setting_name`),
    `color_value`  = VALUES(`color_value`),
    `category`     = VALUES(`category`),
    `is_active`    = VALUES(`is_active`),
    `sort_order`   = VALUES(`sort_order`);
