-- Migration: Create entity_categories table
-- Date: 2026-03-07
-- Description: Allows entities (branches/stores) to be associated with specific
--              categories so that each entity's POS only shows its relevant categories.
--              When no rows exist for an entity, all tenant categories are shown (graceful fallback).

CREATE TABLE IF NOT EXISTS `entity_categories` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `entity_id`   INT UNSIGNED NOT NULL,
    `category_id` INT UNSIGNED NOT NULL,
    `tenant_id`   INT UNSIGNED NOT NULL,
    `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_entity_category`  (`entity_id`, `category_id`),
    KEY `idx_entity_categories_entity`   (`entity_id`),
    KEY `idx_entity_categories_category` (`category_id`),
    KEY `idx_entity_categories_tenant`   (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
