<?php
declare(strict_types=1);
/**
 * Public API sub-route: ads
 * Loaded by api/v1/routes/public.php dispatcher.
 * Variables available: $pdo, $pdoList, $pdoOne, $pdoCount,
 *   $first, $segments, $lang, $page, $per, $offset, $tenantId
 *
 * Routes:
 *   GET  /api/public/ads?tenant_id=X&placement_key=Y&lang=Z
 *     Returns active ads for the given placement (or all placements if key omitted).
 *   GET  /api/public/ads/{id}  — single ad detail (for click-through pages)
 *   POST /api/public/ads/{id}/click — record a click in ad_stats
 *   POST /api/public/ads/{id}/view  — record a view  in ad_stats
 */

if ($first !== 'ads') {
    return; // not our route
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

/* -------------------------------------------------------
 * Click / view tracking: POST /api/public/ads/{id}/click|view
 * Upserts a row in ad_stats (ad_id, date) incrementing the
 * appropriate counter.  Requires the UNIQUE KEY
 * uq_ad_stats_ad_date on (ad_id, date) — see migration
 * fix_ads_sections_and_add_stats_unique_key.sql.
 * ----------------------------------------------------- */
$adId   = isset($segments[1]) && ctype_digit((string)$segments[1]) ? (int)$segments[1] : 0;
$action = strtolower($segments[2] ?? '');

if ($adId > 0 && $method === 'POST' && in_array($action, ['click', 'view'], true)) {
    if ($pdo instanceof PDO) {
        try {
            if ($action === 'click') {
                $pdo->prepare(
                    "INSERT INTO `ad_stats` (`ad_id`, `date`, `views`, `clicks`)
                     VALUES (?, CURDATE(), 0, 1)
                     ON DUPLICATE KEY UPDATE `clicks` = `clicks` + 1"
                )->execute([$adId]);
            } else {
                $pdo->prepare(
                    "INSERT INTO `ad_stats` (`ad_id`, `date`, `views`, `clicks`)
                     VALUES (?, CURDATE(), 1, 0)
                     ON DUPLICATE KEY UPDATE `views` = `views` + 1"
                )->execute([$adId]);
            }
        } catch (Throwable) {
            // Silently ignore tracking errors — do not break the user experience
        }
    }
    ResponseFormatter::success(['ok' => true]);
    exit;
}

/* -------------------------------------------------------
 * Single-ad detail: GET /api/public/ads/{id}
 * ----------------------------------------------------- */
if ($adId > 0) {
    if (!$tenantId) { ResponseFormatter::notFound('Ad not found'); exit; }
    $row = $pdoOne(
        "SELECT a.id, a.campaign_id, a.target_type, a.target_value, a.status,
                COALESCE(atr.title,       '') AS title,
                COALESCE(atr.description, '') AS description,
                img.url       AS image_url,
                img.thumb_url AS thumb_url
           FROM ads a
           JOIN ad_campaigns ac ON ac.id = a.campaign_id AND ac.tenant_id = ?
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
 * Ads by placement: GET /api/public/ads?tenant_id=X&placement_key=Y
 * ----------------------------------------------------- */
if (!$tenantId) {
    ResponseFormatter::success(['ok' => true, 'data' => []]);
    exit;
}

$placementKey = isset($_GET['placement_key']) ? trim((string)$_GET['placement_key']) : '';
$maxAds = max(1, min(50, (int)($_GET['limit'] ?? 25)));

$sqlParams = [$tenantId, $tenantId, $lang];
$sqlWhere  = '';

if ($placementKey !== '') {
    $sqlWhere  = 'AND ap.placement_key = ?';
    $sqlParams[] = $placementKey;
}

$rows = $pdoList(
    "SELECT a.id, a.target_type, a.target_value,
            COALESCE(atr.title,       '') AS title,
            COALESCE(atr.description, '') AS description,
            img.url       AS image_url,
            img.thumb_url AS thumb_url,
            api_item.priority, api_item.weight
       FROM ad_placement_items api_item
       JOIN ad_placements ap  ON ap.id  = api_item.placement_id AND ap.tenant_id = ? AND ap.status = 'active'
       JOIN ads a             ON a.id   = api_item.ad_id
       JOIN ad_campaigns ac   ON ac.id  = a.campaign_id AND ac.tenant_id = ? AND ac.status = 'active'
       LEFT JOIN ad_translations atr ON atr.ad_id = a.id AND atr.language_code = ?
       LEFT JOIN images img   ON img.owner_id = a.id AND img.image_type_id = 20 AND img.is_main = 1
      WHERE a.status = 'active'
        AND (api_item.start_date IS NULL OR api_item.start_date <= NOW())
        AND (api_item.end_date   IS NULL OR api_item.end_date   >= NOW())
        $sqlWhere
      ORDER BY api_item.priority ASC, api_item.weight DESC
      LIMIT ?",
    array_merge($sqlParams, [$maxAds])
);

ResponseFormatter::success(['ok' => true, 'data' => $rows]);
exit;
