<?php
declare(strict_types=1);
/**
 * api/routes/public_notifications.php
 * QOOQZ — Public Notifications API
 *
 * Serves /api/public/notifications/* requests.
 * Included by public.php when $first === 'notifications'.
 *
 * Endpoints:
 *  GET  /api/public/notifications              — list recent notifications for tenant
 *  GET  /api/public/notifications/types        — list active notification types (for icons/labels)
 *  POST /api/public/notifications/mark-seen    — store seen IDs server-side (no-op stub, kept for extensibility)
 *
 * Auth: session-based (same as the rest of public.php).
 *       Listing is public (tenant-scoped); mark-seen requires login.
 *
 * Variables provided by the parent (public.php):
 *  $pdo        PDO|null
 *  $segments   array
 *  $tenantId   int|null
 *  $lang       string
 *  $page       int
 *  $per        int
 *  $offset     int
 *  $pdoList    callable
 */

// $pdo, $segments, $tenantId, $page, $per, $offset, $pdoList are already set by public.php.

$notifMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$notifSub    = strtolower($segments[1] ?? '');

// Resolve user from session (same pattern as rest of public.php)
$notifUserId = (int)($_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? 0));

/* ------------------------------------------------------------------
 * GET /api/public/notifications/types
 * Returns all active notification types (id, code, name, description).
 * ------------------------------------------------------------------ */
if ($notifMethod === 'GET' && $notifSub === 'types') {
    if (!$pdo instanceof PDO) { ResponseFormatter::error('DB unavailable', 503); exit; }
    try {
        $st = $pdo->prepare(
            'SELECT id, code, name, description FROM notification_types WHERE is_active = 1 ORDER BY id ASC'
        );
        $st->execute();
        $types = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        ResponseFormatter::success(['types' => $types, 'total' => count($types)]);
    } catch (Throwable $e) {
        error_log('[public_notifications] types error: ' . $e->getMessage());
        ResponseFormatter::error('Failed to load notification types', 500);
    }
    exit;
}

/* ------------------------------------------------------------------
 * GET /api/public/notifications
 * Returns recent notifications for the tenant with optional filters.
 *
 * Query params:
 *   tenant_id          int    (required — falls back to session value)
 *   limit              int    default 20, max 100
 *   page               int    default 1
 *   type_code          string filter by notification_type code
 *   priority           string filter by priority (low|normal|high|urgent)
 * ------------------------------------------------------------------ */
if ($notifMethod === 'GET' && $notifSub === '') {
    if (!$pdo instanceof PDO) { ResponseFormatter::error('DB unavailable', 503); exit; }

    $nTenantId = $tenantId ?? (int)($_SESSION['pub_tenant_id'] ?? 1);
    if (!$nTenantId) { ResponseFormatter::error('tenant_id is required', 422); exit; }

    $nLimit  = min(100, max(1, (int)($_GET['limit'] ?? $per)));
    $nPage   = max(1, (int)($_GET['page'] ?? $page));
    $nOffset = ($nPage - 1) * $nLimit;

    $where  = ['n.tenant_id = ?', '(n.expires_at IS NULL OR n.expires_at > NOW())'];
    $params = [$nTenantId];

    if (!empty($_GET['type_code'])) {
        $where[]  = 'nt.code = ?';
        $params[] = (string)$_GET['type_code'];
    }
    $allowedPriorities = ['low', 'normal', 'high', 'urgent'];
    if (!empty($_GET['priority']) && in_array($_GET['priority'], $allowedPriorities, true)) {
        $where[]  = 'n.priority = ?';
        $params[] = $_GET['priority'];
    }

    $whereClause = implode(' AND ', $where);

    try {
        // Count
        $cSt = $pdo->prepare(
            "SELECT COUNT(*) FROM notifications n
             LEFT JOIN notification_types nt ON nt.id = n.notification_type_id
             WHERE $whereClause"
        );
        $cSt->execute($params);
        $total = (int)$cSt->fetchColumn();

        // Fetch
        $qSt = $pdo->prepare(
            "SELECT n.id, n.title, n.message, n.sent_at, n.priority, n.data,
                    n.entity_id, n.sender_entity_id,
                    nt.id   AS type_id,
                    nt.code AS type_code,
                    nt.name AS type_name
               FROM notifications n
          LEFT JOIN notification_types nt ON nt.id = n.notification_type_id
              WHERE $whereClause
           ORDER BY n.sent_at DESC
              LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $i => $v) {
            $qSt->bindValue($i + 1, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $qSt->bindValue(':limit',  $nLimit,  PDO::PARAM_INT);
        $qSt->bindValue(':offset', $nOffset, PDO::PARAM_INT);
        $qSt->execute();
        $items = $qSt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        ResponseFormatter::success([
            'items' => $items,
            'meta'  => [
                'total'       => $total,
                'page'        => $nPage,
                'per_page'    => $nLimit,
                'total_pages' => $total > 0 ? (int)ceil($total / $nLimit) : 0,
            ],
        ]);
    } catch (Throwable $e) {
        error_log('[public_notifications] list error: ' . $e->getMessage());
        ResponseFormatter::error('Failed to load notifications', 500);
    }
    exit;
}

/* ------------------------------------------------------------------
 * POST /api/public/notifications/mark-seen
 * Stub endpoint — unread state is tracked client-side (localStorage)
 * because the notifications table has no per-user is_read column.
 * Kept here so frontend code can POST to it without receiving a 404,
 * and can be wired to a real DB column if added in a future migration.
 *
 * Body: { "ids": [1, 2, 3] }
 * ------------------------------------------------------------------ */
if ($notifMethod === 'POST' && $notifSub === 'mark-seen') {
    if (!$notifUserId) { ResponseFormatter::error('Login required', 401); exit; }
    $raw  = file_get_contents('php://input');
    $body = $raw !== false && $raw !== '' ? (json_decode($raw, true) ?? []) : [];
    $ids  = array_filter(array_map('intval', (array)($body['ids'] ?? [])));
    // Future: UPDATE notifications_read SET ... WHERE user_id = ? AND notification_id IN (...)
    ResponseFormatter::success(['marked' => array_values($ids)], 'Marked as seen');
    exit;
}

ResponseFormatter::notFound('Notifications route not found: /' . implode('/', $segments));
