-- Migration: add_user_entity_to_search_logs
-- Adds user_id and entity_id to search_logs so we can identify
-- which user searched for what and in which entity context,
-- enabling targeted offers and notifications.

ALTER TABLE search_logs
    ADD COLUMN user_id   INT NULL DEFAULT NULL AFTER tenant_id,
    ADD COLUMN entity_id INT NULL DEFAULT NULL AFTER user_id;

-- Drop old unique key (query + tenant_id + lang) and create a new one
-- that also partitions by user_id and entity_id.
-- NULL values are treated as distinct in MySQL UNIQUE keys, so:
--   user_id=NULL  → guest / global aggregate row
--   user_id=X     → per-user row
ALTER TABLE search_logs
    DROP INDEX uq_query_tenant_lang,
    ADD UNIQUE KEY uq_query_tenant_user_entity_lang (query(100), tenant_id, user_id, entity_id, lang);

-- Index for querying all searches by a specific user
ALTER TABLE search_logs
    ADD INDEX idx_user_id (user_id);

-- Index for querying all searches within a specific entity
ALTER TABLE search_logs
    ADD INDEX idx_entity_id (entity_id);
