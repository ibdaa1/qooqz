-- Migration: Add per-session unique key to ad_stats
--
-- Background:
--   alter_ad_stats_add_tracking_columns.sql dropped the old uq_ad_stats_ad_date(ad_id,date)
--   key and switched to a per-event model.  The PHP INSERT ... ON DUPLICATE KEY UPDATE
--   then never triggered, so view and click events each created their own row, leaving
--   views=0 in click rows and clicks=0 in view rows.
--
-- Fix:
--   Add UNIQUE KEY uq_ad_stats_session(ad_id, session_id, date) so that
--   view + click events from the same browser session for the same ad on the same day
--   are merged into one row via ON DUPLICATE KEY UPDATE.
--
-- Safe to run multiple times (conditional DDL).
-- ─────────────────────────────────────────────────────────────────────────────

-- 1. Deduplicate existing rows before adding the key.
--    Keep the row with the highest id (latest insert) per (ad_id, session_id, date)
--    and accumulate views/clicks into it.
DROP TEMPORARY TABLE IF EXISTS _ad_stats_dedup_tmp;
CREATE TEMPORARY TABLE _ad_stats_dedup_tmp AS
SELECT
    MAX(id)          AS keep_id,
    ad_id,
    session_id,
    `date`,
    SUM(views)       AS total_views,
    SUM(clicks)      AS total_clicks
FROM ad_stats
WHERE session_id IS NOT NULL
GROUP BY ad_id, session_id, `date`
HAVING COUNT(*) > 1;

-- Update the kept row with accumulated totals.
UPDATE ad_stats a
JOIN _ad_stats_dedup_tmp t ON a.id = t.keep_id
SET a.views  = t.total_views,
    a.clicks = t.total_clicks;

-- Delete duplicate rows (all but the kept one per group).
DELETE a
FROM ad_stats a
JOIN _ad_stats_dedup_tmp t
     ON  a.ad_id      = t.ad_id
     AND a.session_id = t.session_id
     AND a.`date`     = t.`date`
     AND a.id        <> t.keep_id;

DROP TEMPORARY TABLE IF EXISTS _ad_stats_dedup_tmp;

-- Rows with NULL session_id are excluded from the deduplication because MySQL
-- treats NULL as not equal to NULL in UNIQUE constraints, so those rows will
-- never conflict with each other and require no deduplication.  The UNIQUE KEY
-- added below will still index non-NULL (ad_id, session_id, date) tuples.
SET @_has_uq = (
    SELECT COUNT(1)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name   = 'ad_stats'
      AND index_name   = 'uq_ad_stats_session'
);
SET @_sql = IF(
    @_has_uq = 0,
    'ALTER TABLE `ad_stats` ADD UNIQUE KEY `uq_ad_stats_session` (`ad_id`, `session_id`, `date`)',
    'SELECT 1 -- uq_ad_stats_session already exists'
);
PREPARE _stmt FROM @_sql;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;
