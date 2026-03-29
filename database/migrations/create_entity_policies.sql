-- ============================================================
-- ENTITY POLICIES — Refund, Privacy, Shipping, Terms per entity
-- ============================================================
-- Stores multilingual policy content for each entity.
-- Each entity can have multiple policy types (refund, privacy, shipping, terms).
-- Falls back to default policies if entity has none.
-- ============================================================

CREATE TABLE IF NOT EXISTS entity_policies (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id     INT UNSIGNED    NOT NULL,
    entity_id     BIGINT UNSIGNED NOT NULL,
    type          VARCHAR(50)     NOT NULL  COMMENT 'Policy type: refund, privacy, shipping, terms',
    language_code VARCHAR(10)     NOT NULL DEFAULT 'en',
    title         VARCHAR(255)    NOT NULL,
    content       TEXT            NULL      COMMENT 'Policy content (HTML or plain text)',
    is_active     TINYINT(1)      NOT NULL DEFAULT 1,
    sort_order    INT UNSIGNED    NOT NULL DEFAULT 0,
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_entity_policy_lang (entity_id, type, language_code),
    INDEX idx_entity_policies_tenant (tenant_id),
    INDEX idx_entity_policies_entity (entity_id),
    INDEX idx_entity_policies_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Supported policy types:
--   refund    — Return & Refund Policy
--   privacy   — Privacy Policy
--   shipping  — Shipping & Delivery Policy
--   terms     — Terms & Conditions
-- ============================================================
