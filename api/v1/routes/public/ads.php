<?php
declare(strict_types=1);
/**
 * Public API sub-route: ads
 * Loaded by api/v1/routes/public.php dispatcher.
 *
 * DB SCHEMA (from DESCRIBE):
 *   ads.target_type        = enum('url','entity')
 *   ad_campaigns.status    = enum('draft','active','paused','completed')
 *   ad_stats               = no UNIQUE KEY on (ad_id,date) — safe UPDATE+INSERT
 *
 * Routes:
 *   GET  /api/public/ads?tenant_id=X[&placement_key=Y&lang=Z&limit=N]
 *   GET  /api/public/ads/{id}
 *   POST /api/public/ads/{id}/click
 *   POST /api/public/ads/{id}/view
 *
 * Variables: $pdo, $pdoList, $pdoOne, $pdoCount,
 *            $first, $segments, $lang, $page, $per, $offset, $tenantId
 */

if ($first !== 'ads') {
    return;
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$adId   = isset($segments[1]) && ctype_digit((string)($segments[1] ?? ''))
            ? (int)$segments[1]
            : 0;
$action = strtolower($segments[2] ?? '');

/* -------------------------------------------------------
 * POST /api/public/ads/{id}/click|view  — tracking
 * ad_stats has no UNIQUE KEY → safe UPDATE first, INSERT if 0 rows
 * ----------------------------------------------------- */
if ($adId > 0 && $method === 'POST' && in_array($action, ['click', 'view'], true)) {
    if ($pdo instanceof PDO) {
        try {
            $col = $action === 'click' ? 'clicks' : 'views';
            $upd = $pdo->prepare(
                "UPDATE `ad_stats` SET `{$col}` = `{$col}` + 1
                  WHERE `ad_id` = ? AND `date` = CURDATE()"
            );
            $upd->execute([$adId]);
            if ($upd->rowCount() === 0) {
                $pdo->prepare(
                    "INSERT IGNORE INTO `ad_stats` (`ad_id`, `date`, `views`, `clicks`)
                     VALUES (?, CURDATE(), ?, ?)"
                )->execute([
                    $adId,
                    $action === 'view'  ? 1 : 0,
                    $action === 'click' ? 1 : 0,
                ]);
            }
        } catch (Throwable) {
            // Tracking failure must never break the user experience
        }
    }
    ResponseFormatter::success(['ok' => true]);
    exit;
}

/* -------------------------------------------------------
 * GET /api/public/ads/{id}  — single ad detail
 * ----------------------------------------------------- */
if ($adId > 0 && $method === 'GET') {
    if (!$tenantId) { ResponseFormatter::notFound('Ad not found'); exit; }
    $row = $pdoOne(
        "SELECT a.id, a.campaign_id, a.target_type, a.target_value, a.status,
                COALESCE(NULLIF(TRIM(atr.title),       ''), '') AS title,
                COALESCE(NULLIF(TRIM(atr.description), ''), '') AS description,
                img.url       AS image_url,
                img.thumb_url AS thumb_url
           FROM ads a
           JOIN ad_campaigns ac ON ac.id = a.campaign_id
                               AND ac.tenant_id = ?
                               AND ac.status    = 'active'
      LEFT JOIN ad_translations atr ON atr.ad_id = a.id AND atr.language_code = ?
      LEFT JOIN images img ON img.owner_id = a.id AND img.image_type_id = 20 AND img.is_main = 1
          WHERE a.id = ? AND a.status = 'active'
          LIMIT 1",
        [$tenantId, $lang, $adId]
    );
    if (!$row) { ResponseFormatter::notFound('Ad not found'); exit; }
    ResponseFormatter::success(['ok' => true, 'data' => $row]);
    exit;
}

/* -------------------------------------------------------
 * GET /api/public/ads  — listing
 * ----------------------------------------------------- */
if ($method !== 'GET') {
    ResponseFormatter::error('Method not allowed', 405);
    exit;
}

if (!$tenantId) {
    ResponseFormatter::success(['ok' => true, 'data' => []]);
    exit;
}

$placementKey = isset($_GET['placement_key']) ? trim((string)$_GET['placement_key']) : '';
$maxAds       = max(1, min(50, (int)($_GET['limit'] ?? $per)));

/* -------------------------------------------------------
 * SELECT fragment reused in all 3 strategies
 * ----------------------------------------------------- */
$adSelect = "SELECT
     a.id,
     a.target_type,
     a.target_value,
     COALESCE(NULLIF(TRIM(atr.title),       ''), '') AS title,
     COALESCE(NULLIF(TRIM(atr.description), ''), '') AS description,
     img.url       AS image_url,
     img.thumb_url AS thumb_url";

/* -------------------------------------------------------
 * Strategy 1 — Placement-aware (primary path)
 * ----------------------------------------------------- */
$p1Where  = '';
$p1Params = [$tenantId, $tenantId, $lang];
if ($placementKey !== '') {
    $p1Where    = 'AND ap.placement_key = ?';
    $p1Params[] = $placementKey;
}
$p1Params[] = $maxAds;

$rows = $pdoList(
    "{$adSelect},
         api_item.priority,
         api_item.weight
       FROM ad_placement_items api_item
       JOIN ad_placements ap ON ap.id        = api_item.placement_id
                            AND ap.tenant_id = ?
                            AND ap.status    = 'active'
       JOIN ads a            ON a.id     = api_item.ad_id
                            AND a.status = 'active'
       JOIN ad_campaigns ac  ON ac.id        = a.campaign_id
                            AND ac.tenant_id = ?
                            AND ac.status    = 'active'
  LEFT JOIN ad_translations atr ON atr.ad_id = a.id AND atr.language_code = ?
  LEFT JOIN images img ON img.owner_id = a.id AND img.image_type_id = 20 AND img.is_main = 1
      WHERE (api_item.start_date IS NULL OR api_item.start_date <= NOW())
        AND (api_item.end_date   IS NULL OR api_item.end_date   >= NOW())
        {$p1Where}
      ORDER BY api_item.priority ASC, api_item.weight DESC
      LIMIT ?",
    $p1Params
);

/* -------------------------------------------------------
 * Strategy 2 — Direct (no placement system configured)
 * ----------------------------------------------------- */
if (empty($rows)) {
    $rows = $pdoList(
        "{$adSelect}, 0 AS priority, 0 AS weight
           FROM ads a
           JOIN ad_campaigns ac ON ac.id        = a.campaign_id
                               AND ac.tenant_id = ?
                               AND ac.status    = 'active'
      LEFT JOIN ad_translations atr ON atr.ad_id = a.id AND atr.language_code = ?
      LEFT JOIN images img ON img.owner_id = a.id AND img.image_type_id = 20 AND img.is_main = 1
          WHERE a.status = 'active'
          ORDER BY a.id DESC
          LIMIT ?",
        [$tenantId, $lang, $maxAds]
    );
}

/* -------------------------------------------------------
 * Strategy 3 — Dev/staging fallback
 * Shows any ad regardless of campaign status.
 * Disabled in production (only when PUB_DEBUG = true).
 * ----------------------------------------------------- */
if (empty($rows) && defined('PUB_DEBUG') && PUB_DEBUG) {
    $rows = $pdoList(
        "{$adSelect}, 0 AS priority, 0 AS weight
           FROM ads a
           JOIN ad_campaigns ac ON ac.id = a.campaign_id AND ac.tenant_id = ?
      LEFT JOIN ad_translations atr ON atr.ad_id = a.id AND atr.language_code = ?
      LEFT JOIN images img ON img.owner_id = a.id AND img.image_type_id = 20 AND img.is_main = 1
          ORDER BY a.id DESC LIMIT ?",
        [$tenantId, $lang, $maxAds]
    );
}

ResponseFormatter::success(['ok' => true, 'data' => $rows]);
exit;
