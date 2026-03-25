-- Migration: Extend ad_stats for per-event tracking
-- Switches ad_stats from a daily-aggregate table to a per-event detail table.
--
-- Changes:
--   1. Drop UNIQUE KEY uq_ad_stats_ad_date (blocks multiple events per ad per day).
--   2. Add user_id, session_id, ip_address, user_agent, event_type, created_at columns.
--   3. Add indexes for efficient per-event queries.
--
-- Safe to run multiple times via IF NOT EXISTS / conditional DDL.

-- ─────────────────────────────────────────────────────────────────────────────
-- 1. Drop old UNIQUE KEY (ad_id, date) if it still exists.
--    The per-event model needs multiple rows per ad per day.
-- ─────────────────────────────────────────────────────────────────────────────
SET @_has_uq = (
    SELECT COUNT(1)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name   = 'ad_stats'
      AND index_name   = 'uq_ad_stats_ad_date'
);
SET @_drop_uq = IF(
    @_has_uq > 0,
    'ALTER TABLE `ad_stats` DROP INDEX `uq_ad_stats_ad_date`',
    'SELECT 1 -- uq_ad_stats_ad_date already removed'
);
PREPARE _stmt FROM @_drop_uq;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

-- ─────────────────────────────────────────────────────────────────────────────
-- 2. Add new columns (each guarded with IF NOT EXISTS via INFORMATION_SCHEMA).
-- ─────────────────────────────────────────────────────────────────────────────

-- user_id
SET @_col = (SELECT COUNT(1) FROM information_schema.columns
             WHERE table_schema=DATABASE() AND table_name='ad_stats' AND column_name='user_id');
SET @_sql = IF(@_col=0,
    'ALTER TABLE `ad_stats` ADD COLUMN `user_id` INT NULL AFTER `ad_id`',
    'SELECT 1');
PREPARE _stmt FROM @_sql; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- session_id
SET @_col = (SELECT COUNT(1) FROM information_schema.columns
             WHERE table_schema=DATABASE() AND table_name='ad_stats' AND column_name='session_id');
SET @_sql = IF(@_col=0,
    'ALTER TABLE `ad_stats` ADD COLUMN `session_id` VARCHAR(64) NULL AFTER `user_id`',
    'SELECT 1');
PREPARE _stmt FROM @_sql; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- ip_address
SET @_col = (SELECT COUNT(1) FROM information_schema.columns
             WHERE table_schema=DATABASE() AND table_name='ad_stats' AND column_name='ip_address');
SET @_sql = IF(@_col=0,
    'ALTER TABLE `ad_stats` ADD COLUMN `ip_address` VARCHAR(45) NULL AFTER `session_id`',
    'SELECT 1');
PREPARE _stmt FROM @_sql; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- user_agent
SET @_col = (SELECT COUNT(1) FROM information_schema.columns
             WHERE table_schema=DATABASE() AND table_name='ad_stats' AND column_name='user_agent');
SET @_sql = IF(@_col=0,
    'ALTER TABLE `ad_stats` ADD COLUMN `user_agent` VARCHAR(255) NULL AFTER `ip_address`',
    'SELECT 1');
PREPARE _stmt FROM @_sql; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- event_type
SET @_col = (SELECT COUNT(1) FROM information_schema.columns
             WHERE table_schema=DATABASE() AND table_name='ad_stats' AND column_name='event_type');
SET @_sql = IF(@_col=0,
    "ALTER TABLE `ad_stats` ADD COLUMN `event_type` ENUM('view','click') NOT NULL DEFAULT 'view' AFTER `clicks`",
    'SELECT 1');
PREPARE _stmt FROM @_sql; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- created_at
SET @_col = (SELECT COUNT(1) FROM information_schema.columns
             WHERE table_schema=DATABASE() AND table_name='ad_stats' AND column_name='created_at');
SET @_sql = IF(@_col=0,
    'ALTER TABLE `ad_stats` ADD COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `date`',
    'SELECT 1');
PREPARE _stmt FROM @_sql; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- ─────────────────────────────────────────────────────────────────────────────
-- 3. Add indexes (guarded).
-- ─────────────────────────────────────────────────────────────────────────────

-- idx_ad_event
SET @_idx = (SELECT COUNT(1) FROM information_schema.statistics
             WHERE table_schema=DATABASE() AND table_name='ad_stats' AND index_name='idx_ad_event');
SET @_sql = IF(@_idx=0,
    'CREATE INDEX `idx_ad_event` ON `ad_stats`(`ad_id`, `event_type`)',
    'SELECT 1');
PREPARE _stmt FROM @_sql; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- idx_user
SET @_idx = (SELECT COUNT(1) FROM information_schema.statistics
             WHERE table_schema=DATABASE() AND table_name='ad_stats' AND index_name='idx_user');
SET @_sql = IF(@_idx=0,
    'CREATE INDEX `idx_user` ON `ad_stats`(`user_id`)',
    'SELECT 1');
PREPARE _stmt FROM @_sql; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- idx_session
SET @_idx = (SELECT COUNT(1) FROM information_schema.statistics
             WHERE table_schema=DATABASE() AND table_name='ad_stats' AND index_name='idx_session');
SET @_sql = IF(@_idx=0,
    'CREATE INDEX `idx_session` ON `ad_stats`(`session_id`)',
    'SELECT 1');
PREPARE _stmt FROM @_sql; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;
