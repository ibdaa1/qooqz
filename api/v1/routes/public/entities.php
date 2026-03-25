<?php
declare(strict_types=1);
/**
 * Public API sub-route: entities
 * Loaded by api/v1/routes/public.php dispatcher.
 *
 * FIX: entities table has no `is_featured` column — removed that filter.
 *      Ordering uses is_verified + joined_at instead.
 *
 * Variables: $pdo, $pdoList, $pdoOne, $pdoCount,
 *            $first, $segments, $lang, $page, $per, $offset, $tenantId
 */

if ($first !== 'entities') {
    return;
}

/* -------------------------------------------------------
 * Single entity detail — by numeric ID
 * GET /api/public/entities/{id}
 * ----------------------------------------------------- */
$id = isset($segments[1]) && ctype_digit((string)$segments[1])
    ? (int)$segments[1]
    : (isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0);

if ($id > 0) {
    $row = $pdoOne(
        "SELECT e.id, e.store_name, e.slug, e.vendor_type, e.store_type,
                e.is_verified, e.phone, e.mobile, e.email, e.website_url AS website,
                e.status, e.tenant_id, e.joined_at, e.created_at,
                COALESCE(et.store_name, e.store_name) AS display_name,
                et.description,
                (SELECT i.url FROM images i
                  WHERE i.owner_id = e.id ORDER BY i.id ASC LIMIT 1) AS logo_url
           FROM entities e
      LEFT JOIN entity_translations et
             ON et.entity_id = e.id AND et.language_code = ?
          WHERE e.id = ?
            AND e.status NOT IN ('suspended','rejected')
          LIMIT 1",
        [$lang, $id]
    );

    if ($row) {
        ResponseFormatter::success(['ok' => true, 'entity' => $row]);
    } else {
        ResponseFormatter::notFound('Entity not found');
    }
    exit;
}

/* -------------------------------------------------------
 * Entities listing
 * GET /api/public/entities?tenant_id=X[&is_verified=1&vendor_type=X&q=X]
 * ----------------------------------------------------- */
$where  = "WHERE e.status NOT IN ('suspended','rejected')";
$params = [];

// Tenant scope
if ($tenantId) {
    $where    .= ' AND e.tenant_id = ?';
    $params[]  = $tenantId;
}

// Vendor type filter (product_seller | service_provider | both)
if (!empty($_GET['vendor_type']) && in_array(
    $_GET['vendor_type'],
    ['product_seller', 'service_provider', 'both'],
    true
)) {
    $where    .= ' AND e.vendor_type = ?';
    $params[]  = $_GET['vendor_type'];
}

// Verified filter — mapped from is_featured param for backward-compat
// (homepage section passes &is_verified=1 or &is_featured=1 — both mean verified)
if (!empty($_GET['is_verified']) || !empty($_GET['is_featured'])) {
    $where    .= ' AND e.is_verified = 1';
}

// Store type filter
if (!empty($_GET['store_type']) && in_array(
    $_GET['store_type'],
    ['individual', 'company', 'brand'],
    true
)) {
    $where    .= ' AND e.store_type = ?';
    $params[]  = $_GET['store_type'];
}

// Full-text search — store_name (base) + translated store_name
if (!empty($_GET['q'])) {
    $like      = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($_GET['q'])) . '%';
    $where    .= ' AND (e.store_name LIKE ?
                     OR e.email LIKE ?
                     OR EXISTS (
                            SELECT 1 FROM entity_translations et2
                             WHERE et2.entity_id = e.id
                               AND et2.store_name LIKE ?
                        ))';
    $params[]  = $like;
    $params[]  = $like;
    $params[]  = $like;
}

$total = $pdoCount("SELECT COUNT(*) FROM entities e $where", $params);

$rows = $pdoList(
    "SELECT
         e.id,
         COALESCE(NULLIF(TRIM(et.store_name), ''), e.store_name) AS store_name,
         e.slug,
         e.vendor_type,
         e.store_type,
         e.is_verified,
         e.tenant_id,
         e.joined_at,
         (SELECT i.url FROM images i
           WHERE i.owner_id = e.id ORDER BY i.id ASC LIMIT 1) AS logo_url,
         -- Card style from entity_settings (if set)
         cs.slug            AS card_style_slug,
         cs.background_color AS card_bg_color,
         cs.border_color    AS card_border_color,
         cs.border_radius   AS card_border_radius,
         cs.shadow_style    AS card_shadow,
         cs.padding         AS card_padding,
         cs.hover_effect    AS card_hover_effect,
         cs.image_aspect_ratio AS card_image_aspect_ratio
       FROM entities e
  LEFT JOIN entity_translations et
         ON et.entity_id = e.id AND et.language_code = ?
  LEFT JOIN entity_settings es
         ON es.entity_id = e.id
  LEFT JOIN card_styles cs
         ON cs.id = es.card_style_id AND cs.is_active = 1
       $where
       ORDER BY e.is_verified DESC, e.joined_at DESC
       LIMIT ? OFFSET ?",
    array_merge([$lang], $params, [$per, $offset])
);

ResponseFormatter::success([
    'ok'   => true,
    'data' => $rows,
    'meta' => [
        'total'       => $total,
        'page'        => $page,
        'per_page'    => $per,
        'total_pages' => $per > 0 ? (int)ceil($total / $per) : 1,
    ],
]);
exit;
