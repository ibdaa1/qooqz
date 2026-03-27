-- Migration: create_search_logs
-- Tracks search queries globally and per-user/entity for popular searches and targeting.

CREATE TABLE IF NOT EXISTS search_logs (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    query       VARCHAR(255)    NOT NULL,
    tenant_id   INT             NULL DEFAULT NULL,
    user_id     INT             NULL DEFAULT NULL,
    entity_id   INT             NULL DEFAULT NULL,
    lang        VARCHAR(10)     NOT NULL DEFAULT 'ar',
    count       INT UNSIGNED    NOT NULL DEFAULT 1,
    last_searched_at DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP
                                ON UPDATE CURRENT_TIMESTAMP,
    -- Global aggregate rows: user_id=NULL, entity_id may be set (entity context) or NULL.
    -- Per-user rows:         user_id=X   (for targeted offers/notifications).
    -- Note: index uses first 100 chars of query; queries differing only beyond char 100 are treated as one.
    UNIQUE KEY uq_query_tenant_user_entity_lang (query(100), tenant_id, user_id, entity_id, lang),
    INDEX idx_tenant_lang_count (tenant_id, lang, count DESC),
    INDEX idx_global_lang_count (lang, count DESC),
    INDEX idx_user_id            (user_id),
    INDEX idx_entity_id          (entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
