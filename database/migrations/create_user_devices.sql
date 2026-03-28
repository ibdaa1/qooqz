-- ═══════════════════════════════════════════════════════════
-- user_devices — تخزين أجهزة المستخدمين و FCM tokens
-- كل مستخدم ممكن عنده عدة أجهزة، كل جهاز له fcm_token
-- ═══════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `user_devices` (
    `id`            BIGINT(20)   UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`       INT(11)      NOT NULL,
    `fcm_token`     TEXT         NOT NULL,
    `device_type`   VARCHAR(20)  NOT NULL DEFAULT 'web' COMMENT 'web, android, ios, other',
    `device_name`   VARCHAR(100) DEFAULT NULL COMMENT 'e.g. Chrome on Windows',
    `user_agent`    TEXT         DEFAULT NULL,
    `ip`            VARCHAR(45)  DEFAULT NULL COMMENT 'IPv4 or IPv6',
    `last_seen_at`  DATETIME     DEFAULT NULL,
    `is_active`     TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '1=active, 0=deregistered',
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_devices_user_id` (`user_id`),
    KEY `idx_user_devices_active` (`is_active`),
    KEY `idx_user_devices_user_active` (`user_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Unique constraint on fcm_token (first 700 bytes for InnoDB key limit)
-- prevents duplicate token registrations
ALTER TABLE `user_devices`
    ADD UNIQUE KEY `uq_user_devices_fcm_token` (`fcm_token`(700));
