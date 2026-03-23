-- Migration: add_session_id_to_user_phone_verifications
-- Adds session_id column so verification can be bound to the exact PHP session
-- that initiated registration, preventing cross-device link activation.

ALTER TABLE `user_phone_verifications`
    ADD COLUMN `session_id` VARCHAR(128) COLLATE utf8mb4_unicode_ci
        DEFAULT NULL
        COMMENT 'PHP session_id() at registration time; must match at verification time'
        AFTER `device_hash`;
