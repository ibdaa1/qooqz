<?php
declare(strict_types=1);

/**
 * Public API sub-route: events  [v1.0.0]
 *
 * POST /api/public/events — Record a core analytics event into core_events.
 *
 * Body (JSON or form-data):
 *   entity_type  string   required  product|entity|brand|category|job|auction
 *   entity_id    int      required
 *   event_type   string   required  view|click|favorite|contact|add_to_cart|purchase
 *   value        float    optional  e.g. product price for add_to_cart/purchase
 *
 * Variables injected by the router:
 *   $pdo, $first, $segments
 */

if ($first !== 'events') return;

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method !== 'POST') {
    ResponseFormatter::error('Method not allowed', 405);
    exit;
}

// ── Parse body (JSON, form-data, or URL-encoded from php://input) ─────────────
$body    = [];
// Priority 1: $_POST (populated by PHP for application/x-www-form-urlencoded)
if (!empty($_POST)) {
    $body = $_POST;
} else {
    // Priority 2: try to read raw body (JSON or URL-encoded)
    $rawBody = (string)file_get_contents('php://input');
    if ($rawBody !== '') {
        $json = json_decode($rawBody, true);
        if (is_array($json)) {
            $body = $json;
        } else {
            // URL-encoded fallback (covers enable_post_data_reading=Off)
            parse_str($rawBody, $parsed);
            if (!empty($parsed)) {
                $body = $parsed;
            }
        }
    }
}

// ── Validate ─────────────────────────────────────────────────────────────────
$allowedEntityTypes = ['product', 'entity', 'brand', 'category', 'job', 'auction'];
$allowedEventTypes  = ['view', 'click', 'favorite', 'contact', 'add_to_cart', 'purchase'];

$entityType = strtolower(trim((string)($body['entity_type'] ?? '')));
$entityId   = isset($body['entity_id']) ? (int)$body['entity_id'] : 0;
$eventType  = strtolower(trim((string)($body['event_type'] ?? '')));
$value      = isset($body['value']) && is_numeric($body['value'])
    ? round((float)$body['value'], 2)
    : null;

if (
    !in_array($entityType, $allowedEntityTypes, true) ||
    $entityId <= 0 ||
    !in_array($eventType, $allowedEventTypes, true)
) {
    error_log('[core_events] Validation failed: entity_type=' . var_export($entityType, true)
        . ' entity_id=' . $entityId . ' event_type=' . var_export($eventType, true));
    ResponseFormatter::error('Invalid parameters', 422);
    exit;
}

// ── Ensure DB connection ──────────────────────────────────────────────────────
// events.php is require'd from public.php; $pdo normally comes from that scope.
// As a fail-safe, build a direct PDO using the DB_* constants loaded by bootstrap.
if (!$pdo instanceof PDO && defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
    try {
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                DB_HOST,
                defined('DB_PORT') ? (int)DB_PORT : 3306,
                DB_NAME
            ),
            DB_USER,
            defined('DB_PASS') ? DB_PASS : '',
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    } catch (Throwable $e) {
        error_log('[core_events] Fallback PDO failed: ' . $e->getMessage());
        $pdo = null;
    }
}

if (!$pdo instanceof PDO) {
    error_log('[core_events] No DB connection available — event dropped: '
        . $entityType . '/' . $entityId . '/' . $eventType);
    ResponseFormatter::success(['ok' => false, 'reason' => 'db_unavailable']);
    exit;
}

// ── Session / user / request metadata ────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    @session_start([
        'cookie_secure'   => isset($_SERVER['HTTPS']),
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

$sessId  = session_id() ?: null;
$userId  = (int)(
    $_SESSION['user']['id'] ??
    ($_SESSION['current_user']['id'] ?? ($_SESSION['user_id'] ?? 0))
) ?: null;

$ip = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
if (str_contains($ip, ',')) {
    $ip = trim(explode(',', $ip)[0]);
}
$ip        = substr($ip, 0, 45) ?: null;
$userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null;

// ── Insert into core_events ───────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare(
        "INSERT INTO core_events
             (entity_type, entity_id, user_id, session_id,
              event_type, value, ip_address, user_agent)
         VALUES
             (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $entityType,
        $entityId,
        $userId,
        $sessId,
        $eventType,
        $value,
        $ip,
        $userAgent,
    ]);
    ResponseFormatter::success(['ok' => true]);
} catch (Throwable $e) {
    // Log the error for server-side diagnostics, but never surface it to the browser.
    error_log('[core_events] Insert failed: ' . $e->getMessage()
        . ' | entity_type=' . $entityType . ' entity_id=' . $entityId
        . ' event_type=' . $eventType);
    ResponseFormatter::success(['ok' => false]);
}
exit;
