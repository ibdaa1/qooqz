-- Migration: create_search_logs
-- Tracks search queries globally and per-tenant for popular searches feature.

CREATE TABLE IF NOT EXISTS search_logs (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    query       VARCHAR(255)    NOT NULL,
    tenant_id   INT             NULL DEFAULT NULL,
    lang        VARCHAR(10)     NOT NULL DEFAULT 'ar',
    count       INT UNSIGNED    NOT NULL DEFAULT 1,
    last_searched_at DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP
                                ON UPDATE CURRENT_TIMESTAMP,
    -- Note: index uses first 100 chars of query; queries differing only beyond char 100 are treated as one.
    UNIQUE KEY uq_query_tenant_lang (query(100), tenant_id, lang),
    INDEX idx_tenant_lang_count (tenant_id, lang, count DESC),
    INDEX idx_global_lang_count (lang, count DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
