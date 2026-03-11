-- Migration: Add POS color settings to admin theme
-- Date: 2026-03-07
-- Description: Inserts POS-relevant color entries into theme_color_settings so that
--              the POS page honours the same DB-driven theme as the rest of the admin.
--              success-color, warning-color, and danger-color are shared semantic colours
--              used across all pages for status indicators.
--
-- Run this once per tenant/theme. Adjust theme_id and tenant_id as needed.

-- ── Theme 1 / Tenant 1 ──────────────────────────────────────────────────────
INSERT INTO `theme_color_settings`
    (`theme_id`, `setting_key`, `setting_name`, `color_value`, `category`, `is_active`, `sort_order`, `tenant_id`)
VALUES
    (1, 'success_color', 'Success Color',  '#10b981', 'semantic', 1, 200, 1),
    (1, 'warning_color', 'Warning Color',  '#f59e0b', 'semantic', 1, 201, 1),
    (1, 'danger_color',  'Danger Color',   '#ef4444', 'semantic', 1, 202, 1),
    (1, 'text_secondary','Text Secondary', '#94a3b8', 'text',     1, 12,  1)
ON DUPLICATE KEY UPDATE
    `setting_name` = VALUES(`setting_name`),
    `color_value`  = VALUES(`color_value`),
    `category`     = VALUES(`category`),
    `is_active`    = VALUES(`is_active`),
    `sort_order`   = VALUES(`sort_order`);

-- ── Theme 2 / Tenant 1 (if applicable) ─────────────────────────────────────
INSERT INTO `theme_color_settings`
    (`theme_id`, `setting_key`, `setting_name`, `color_value`, `category`, `is_active`, `sort_order`, `tenant_id`)
VALUES
    (2, 'success_color', 'Success Color',  '#10b981', 'semantic', 1, 200, 1),
    (2, 'warning_color', 'Warning Color',  '#f59e0b', 'semantic', 1, 201, 1),
    (2, 'danger_color',  'Danger Color',   '#ef4444', 'semantic', 1, 202, 1),
    (2, 'text_secondary','Text Secondary', '#94a3b8', 'text',     1, 12,  1)
ON DUPLICATE KEY UPDATE
    `setting_name` = VALUES(`setting_name`),
    `color_value`  = VALUES(`color_value`),
    `category`     = VALUES(`category`),
    `is_active`    = VALUES(`is_active`),
    `sort_order`   = VALUES(`sort_order`);
