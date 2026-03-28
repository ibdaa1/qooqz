-- ═══════════════════════════════════════════════════════════
-- Make fcm_token nullable in user_devices table
-- Device registration on login does not always have an FCM token
-- (only available when Firebase/push notifications are configured).
-- Without this, INSERT fails silently because fcm_token was NOT NULL.
-- ═══════════════════════════════════════════════════════════

ALTER TABLE `user_devices`
    MODIFY COLUMN `fcm_token` TEXT DEFAULT NULL;

-- Drop the unique key on fcm_token first (if exists), then re-add
-- with a condition-safe approach. The unique key prevents duplicate
-- FCM tokens but must allow multiple NULLs.
-- Note: MySQL/MariaDB allows multiple NULLs in a UNIQUE key by default.
