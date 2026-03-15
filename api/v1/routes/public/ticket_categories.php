<?php
declare(strict_types=1);
/**
 * api/v1/routes/public/ticket_categories.php
 * QOOQZ — Public Ticket Categories API
 *
 * Serves /api/public/ticket_categories requests.
 * Loaded by api/v1/routes/public.php dispatcher when $first === 'ticket_categories'.
 *
 * Endpoints:
 *  GET  /api/public/ticket_categories   — list active ticket categories (for form dropdowns)
 *
 * Variables provided by the parent (public.php):
 *  $pdo, $pdoList, $pdoOne, $pdoCount,
 *  $first, $segments, $lang, $page, $per, $offset, $tenantId
 */

$tcMethod   = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$tcTenantId = (int)($tenantId ?? $_SESSION['pub_tenant_id'] ?? 1) ?: 1;

if ($tcMethod === 'OPTIONS') {
    if (!headers_sent()) {
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token, X-Requested-With');
        http_response_code(204);
    }
    exit;
}

if ($tcMethod !== 'GET') {
    ResponseFormatter::error('Method not allowed', 405);
    exit;
}

if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database unavailable', 503);
    exit;
}

/* -------------------------------------------------------
 * GET /api/public/ticket_categories
 * Returns active ticket categories for the new-ticket form dropdown.
 * No login required.
 * ----------------------------------------------------- */
try {
    $cats = $pdoList(
        "SELECT tc.id, COALESCE(tct.name, tc.slug) AS name, tc.slug
         FROM ticket_categories tc
         LEFT JOIN ticket_category_translations tct
            ON tct.category_id = tc.id AND tct.lang = ?
         WHERE tc.tenant_id = ? AND tc.is_active = 1
         ORDER BY tc.sort_order ASC, tc.id ASC",
        [$lang, $tcTenantId]
    );
    ResponseFormatter::success(['items' => $cats]);
} catch (Throwable $ex) {
    ResponseFormatter::error('Failed to load ticket categories', 500);
}
