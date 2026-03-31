<?php
declare(strict_types=1);

/**
 * Public API sub-route: ads  [v2.1.0 — Production]
 *
 * DB tables:
 *   ads, ad_campaigns, ad_translations, ad_placements, ad_placement_items, ad_stats
 *
 * Routes:
 *   GET  /api/public/ads
 *   GET  /api/public/ads/{id}
 *   POST /api/public/ads/{id}/click
 *   POST /api/public/ads/{id}/view
 *
 * Schema migrations required (run once):
 *
 *   -- Widen target_type ENUM to match all types handled in PHP:
 *   ALTER TABLE ads MODIFY COLUMN target_type
 *     ENUM('url','product','category','entity','brand','auction','job','page')
 *     DEFAULT 'url';
 *
 *   -- Per-event tracking columns + per-session unique key (enables upsert):
 *   Run: database/migrations/alter_ad_stats_add_tracking_columns.sql
 *   Run: database/migrations/add_ad_stats_per_session_unique_key.sql
 *
 * Variables assumed to exist (injected by the API router):
 *   $pdo, $pdoList, $pdoOne, $first, $segments, $lang,
 *   $page, $per, $offset, $tenantId
 */

if ($first !== 'ads') return;

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$adId   = isset($segments[1]) && ctype_digit((string)($segments[1] ?? '')) ? (int)$segments[1] : 0;
$action = strtolower($segments[2] ?? '');

/* ═══════════════════════════════════════════════════════════════════════════
 * POST /ads/{id}/click  |  /ads/{id}/view — impression / click tracking
 *
 * Per-session upsert: one row per (ad_id, session_id, date).
 * Relies on UNIQUE KEY uq_ad_stats_session (ad_id, session_id, date) added by
 * migration add_ad_stats_per_session_unique_key.sql.
 *
 * When a view fires first it creates the row (views=1, clicks=0).
 * When the click fires afterwards ON DUPLICATE KEY UPDATE increments clicks and
 * upgrades event_type to 'click'.
 * If a click fires first (user clicked before the 1-second IntersectionObserver
 * timer) it sets views=1 and clicks=1 directly, so neither counter is lost.
 * ═══════════════════════════════════════════════════════════════════════════ */
if ($adId > 0 && $method === 'POST' && in_array($action, ['click', 'view'], true)) {
    // ── Collect tracking data ────────────────────────────────────────────
    if (session_status() === PHP_SESSION_NONE) {
        @session_start([
            'cookie_secure'   => isset($_SERVER['HTTPS']),
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
        ]);
    }

    $trackSessionId = session_id() ?: null;
    $trackUserId    = (int)(
        $_SESSION['user']['id'] ??
        ($_SESSION['current_user']['id'] ?? ($_SESSION['user_id'] ?? 0))
    ) ?: null;

    // Release session lock immediately — we only read, never write.
    session_write_close();

    $trackIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    if (str_contains((string)$trackIp, ',')) {
        $trackIp = trim(explode(',', (string)$trackIp)[0]);
    }
    $trackIp        = substr((string)$trackIp, 0, 45) ?: null;
    $trackUserAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null;
    $trackEventType = $action === 'click' ? 'click' : 'view';

    // A click always implies a view.  Setting views=1 on click events means the
    // row correctly shows views=1 even when the click arrives before the separate
    // view request (within the first second of visibility).
    $isView  = 1; // every event records a view
    $isClick = $action === 'click' ? 1 : 0;

    try {
        // Upsert: insert on first event, increment counters on subsequent events
        // for the same session+ad+day.  Also upgrade event_type to 'click' when
        // a click comes in after a view-only row was created.
        // Relies on UNIQUE KEY uq_ad_stats_session (ad_id, session_id, date).
        $ins = $pdo->prepare(
            "INSERT INTO ad_stats
                 (ad_id, user_id, session_id, ip_address, user_agent,
                  date, created_at, views, clicks, event_type)
             VALUES
                 (?, ?, ?, ?, ?, CURDATE(), NOW(), ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                 views      = views + VALUES(views),
                 clicks     = clicks + VALUES(clicks),
                 event_type = IF(VALUES(clicks) > 0, 'click', event_type)"
        );
        $ins->execute([
            $adId,
            $trackUserId,
            $trackSessionId,
            $trackIp,
            $trackUserAgent,
            $isView,
            $isClick,
            $trackEventType,
        ]);
    } catch (Throwable $e) {
        error_log('[ads.php] ad_stats insert failed for ad_id=' . $adId . ': ' . $e->getMessage());
        // Fallback: minimal upsert without the extended columns (legacy schema).
        try {
            $pdo->prepare(
                "INSERT INTO ad_stats (ad_id, date, views, clicks)
                 VALUES (?, CURDATE(), ?, ?)
                 ON DUPLICATE KEY UPDATE
                     views  = views  + VALUES(views),
                     clicks = clicks + VALUES(clicks)"
            )->execute([$adId, $isView, $isClick]);
        } catch (Throwable $e2) {
            error_log('[ads.php] ad_stats fallback insert failed for ad_id=' . $adId . ': ' . $e2->getMessage());
        }
    }
    ResponseFormatter::success(['ok' => true]);
    exit;
}

/* ═══════════════════════════════════════════════════════════════════════════
 * GET /ads/{id} — single ad
 * ═══════════════════════════════════════════════════════════════════════════ */
if ($adId > 0 && $method === 'GET') {
    if (!$tenantId) {
        ResponseFormatter::notFound('Ad not found');
        exit;
    }

    $row = $pdoOne("
        SELECT
            a.id,
            a.campaign_id,
            a.target_type,
            a.target_value,
            a.status,
            COALESCE(NULLIF(TRIM(atr.title), ''), '')       AS title,
            COALESCE(NULLIF(TRIM(atr.description), ''), '') AS description,
            img.url       AS image_url,
            img.thumb_url AS thumb_url
        FROM ads a
        JOIN ad_campaigns ac
            ON  ac.id        = a.campaign_id
            AND ac.tenant_id = ?
            AND ac.status    = 'active'
        LEFT JOIN ad_translations atr
            ON  atr.ad_id         = a.id
            AND atr.language_code = ?
        LEFT JOIN images img
            ON  img.owner_id      = a.id
            AND img.image_type_id = 20
            AND img.is_main       = 1
        WHERE a.id     = ?
          AND a.status = 'active'
        LIMIT 1
    ", [$tenantId, $lang, $adId]);

    if (!$row) {
        ResponseFormatter::notFound('Ad not found');
        exit;
    }

    ResponseFormatter::success(['ok' => true, 'data' => $row]);
    exit;
}

/* ═══════════════════════════════════════════════════════════════════════════
 * GET /ads — listing
 * ═══════════════════════════════════════════════════════════════════════════ */
if ($method !== 'GET') {
    ResponseFormatter::error('Method not allowed', 405);
    exit;
}

if (!$tenantId) {
    ResponseFormatter::success(['ok' => true, 'data' => []]);
    exit;
}

$placementKey = trim($_GET['placement_key'] ?? '');
$maxAds       = max(1, min(50, (int)($_GET['limit'] ?? $per)));

/* ── Shared SELECT fragment ────────────────────────────────────────────── */
$adSelect = "
    SELECT
        a.id,
        a.target_type,
        a.target_value,
        COALESCE(NULLIF(TRIM(atr.title), ''), '')       AS title,
        COALESCE(NULLIF(TRIM(atr.description), ''), '') AS description,
        img.url       AS image_url,
        img.thumb_url AS thumb_url
";

/* ── Strategy 1: Placement-aware ──────────────────────────────────────── */
$p1Where  = '';
$p1Params = [$tenantId, $tenantId, $lang];

if ($placementKey !== '') {
    $p1Where    = 'AND ap.placement_key = ?';
    $p1Params[] = $placementKey;
}
$p1Params[] = $maxAds;

$rows = $pdoList("
    {$adSelect},
        api_item.priority,
        api_item.weight
    FROM ad_placement_items api_item
    JOIN ad_placements ap
        ON  ap.id        = api_item.placement_id
        AND ap.tenant_id = ?
        AND ap.status    = 'active'
    JOIN ads a
        ON  a.id     = api_item.ad_id
        AND a.status = 'active'
    JOIN ad_campaigns ac
        ON  ac.id        = a.campaign_id
        AND ac.tenant_id = ?
        AND ac.status    = 'active'
    LEFT JOIN ad_translations atr
        ON  atr.ad_id         = a.id
        AND atr.language_code = ?
    LEFT JOIN images img
        ON  img.owner_id      = a.id
        AND img.image_type_id = 20
        AND img.is_main       = 1
    WHERE (api_item.start_date IS NULL OR api_item.start_date <= NOW())
      AND (api_item.end_date   IS NULL OR api_item.end_date   >= NOW())
      {$p1Where}
    ORDER BY api_item.priority ASC, api_item.weight DESC
    LIMIT ?
", $p1Params);

/* ── Strategy 2: Direct ads (no placement configured) ─────────────────── */
if (empty($rows)) {
    $rows = $pdoList("
        {$adSelect}, 0 AS priority, 0 AS weight
        FROM ads a
        JOIN ad_campaigns ac
            ON  ac.id        = a.campaign_id
            AND ac.tenant_id = ?
            AND ac.status    = 'active'
        LEFT JOIN ad_translations atr
            ON  atr.ad_id         = a.id
            AND atr.language_code = ?
        LEFT JOIN images img
            ON  img.owner_id      = a.id
            AND img.image_type_id = 20
            AND img.is_main       = 1
        WHERE a.status = 'active'
        ORDER BY a.id DESC
        LIMIT ?
    ", [$tenantId, $lang, $maxAds]);
}

ResponseFormatter::success(['ok' => true, 'data' => $rows]);
exit;
