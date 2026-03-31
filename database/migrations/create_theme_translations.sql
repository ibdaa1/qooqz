-- Migration: create_theme_translations.sql
-- Creates the theme_translations table for multi-language theme names/descriptions

CREATE TABLE IF NOT EXISTS `theme_translations` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `theme_id`      INT UNSIGNED    NOT NULL,
    `tenant_id`     INT UNSIGNED    NOT NULL DEFAULT 0,
    `language_code` VARCHAR(10)     NOT NULL,
    `name`          VARCHAR(255)    NOT NULL DEFAULT '',
    `description`   TEXT            NULL,
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_theme_lang_tenant` (`theme_id`, `language_code`, `tenant_id`),
    KEY `idx_theme_id` (`theme_id`),
    KEY `idx_language_code` (`language_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
