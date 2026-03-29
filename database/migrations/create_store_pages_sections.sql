-- ============================================================
-- STORE PAGES & SECTIONS — Dynamic Store Builder
-- ============================================================
-- Creates a section-based page builder for entity/vendor pages.
-- Each entity can have a configurable page with ordered sections.
-- ============================================================

-- 1. store_pages — one page per entity (the store profile page)
CREATE TABLE IF NOT EXISTS store_pages (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id     INT UNSIGNED NOT NULL,
    entity_id     BIGINT UNSIGNED NOT NULL,
    type          VARCHAR(50)  NOT NULL DEFAULT 'store'  COMMENT 'Page type: store, landing, etc.',
    slug          VARCHAR(255) NULL                       COMMENT 'Optional custom slug override',
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    settings      JSON         NULL                       COMMENT 'Page-level settings (theme, colors, etc.)',
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_store_pages_entity (tenant_id, entity_id, type),
    INDEX idx_store_pages_tenant (tenant_id),
    INDEX idx_store_pages_entity (entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 2. store_sections — ordered sections within a page
CREATE TABLE IF NOT EXISTS store_sections (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    page_id       BIGINT UNSIGNED NOT NULL,
    type          VARCHAR(50)  NOT NULL  COMMENT 'header, contact, tabs, products, info, hours, location, offers, reviews',
    position      INT UNSIGNED NOT NULL DEFAULT 0,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    settings      JSON         NULL      COMMENT 'Section-specific JSON settings',
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_store_sections_page (page_id, position),
    INDEX idx_store_sections_type (type),
    CONSTRAINT fk_store_sections_page FOREIGN KEY (page_id) REFERENCES store_pages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 3. store_section_translations — multi-language content per section
CREATE TABLE IF NOT EXISTS store_section_translations (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    section_id    BIGINT UNSIGNED NOT NULL,
    language_code VARCHAR(10)  NOT NULL DEFAULT 'en',
    title         VARCHAR(255) NULL,
    content       JSON         NULL     COMMENT 'Localized content as JSON',
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_section_translation (section_id, language_code),
    CONSTRAINT fk_section_trans_section FOREIGN KEY (section_id) REFERENCES store_sections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 4. entity_reviews — reviews/ratings for entities (if not already existing as entity_ratings)
--    Skip if entity_ratings table already exists; this is an alias/enhancement.
--    The existing entity_ratings table is used instead.


-- ============================================================
-- SEED: Default section types for reference
-- ============================================================
-- These are the supported section types:
--   header       — Cover image, logo, name, rating, verification, open/closed
--   contact      — Phone, email, website, share button
--   tabs         — Tab navigation bar
--   products     — Product grid with categories, search, pagination
--   info         — Description, business details, attributes, payment methods
--   hours        — Working hours schedule with open/closed logic
--   location     — Map with coordinates, addresses
--   offers       — Discounts and promotions
--   reviews      — Ratings list, user comments, submit form
--   policies     — Entity policies (refund, privacy, shipping, terms)
-- ============================================================

-- Example: Auto-create a default store page for an entity (run manually or from app code)
-- INSERT INTO store_pages (tenant_id, entity_id, type) VALUES (1, 1, 'store');
--
-- INSERT INTO store_sections (page_id, type, position, is_active, settings) VALUES
--   (1, 'header',   10, 1, '{"show_cover": true, "show_rating": true, "show_verified": true, "show_status": true}'),
--   (1, 'contact',  20, 1, '{"show_phone": true, "show_email": true, "show_website": true, "show_share": true, "show_social": true}'),
--   (1, 'tabs',     30, 1, '{"tabs": ["products","info","hours","location","offers","reviews","policies"]}'),
--   (1, 'products', 40, 1, '{"per_page": 12, "show_categories": true, "show_search": true, "show_cart": true}'),
--   (1, 'info',     50, 1, '{"show_description": true, "show_attributes": true, "show_payment_methods": true, "show_settings": true}'),
--   (1, 'hours',    60, 1, '{}'),
--   (1, 'location', 70, 1, '{"show_osm": true, "show_google": true}'),
--   (1, 'offers',   80, 1, '{}'),
--   (1, 'reviews',  90, 1, '{"show_form": true, "limit": 5}'),
--   (1, 'policies', 95, 1, '{"types": ["refund", "privacy", "shipping", "terms"]}');
