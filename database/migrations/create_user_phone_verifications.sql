-- Migration: create_user_phone_verifications
-- Stores one-time phone verification tokens for device-bound activation.
-- The raw token is NEVER stored; only its SHA-256 hash is kept.
-- The device_hash ties the token to the browser/device that registered.
-- The session_id ties the token to the exact PHP session that initiated registration.

CREATE TABLE IF NOT EXISTS `user_phone_verifications` (
    `id`          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT(11)    NOT NULL,
    `token_hash`  CHAR(64)   NOT NULL COLLATE utf8mb4_unicode_ci
                  COMMENT 'SHA-256 hex of the raw token sent in the SMS link',
    `device_hash` CHAR(64)   NOT NULL COLLATE utf8mb4_unicode_ci
                  COMMENT 'SHA-256 hex of the device_token cookie set during registration',
    `session_id`  VARCHAR(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL
                  COMMENT 'PHP session_id() at registration time; must match at verification time',
    `user_agent`  TEXT       COLLATE utf8mb4_unicode_ci,
    `ip`          VARCHAR(45) COLLATE utf8mb4_unicode_ci,
    `expires_at`  DATETIME   NOT NULL,
    `used_at`     DATETIME   NULL DEFAULT NULL,
    `created_at`  DATETIME   NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_token_hash` (`token_hash`),
    INDEX `idx_user_id`  (`user_id`),
    INDEX `idx_expires`  (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
