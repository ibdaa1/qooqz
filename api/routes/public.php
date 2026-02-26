<?php
declare(strict_types=1);
/**
 * routes/public.php
 *
 * Public API routes under /api/public/*
 * Handles: home, ui, products, vendors, jobs, entities, tenants
 *
 * Dispatcher provides:
 *  - $_GET['segments'] (array)
 *  - $_GET['splat']    (string)
 *  - $GLOBALS['ADMIN_DB'] (PDO|null)
 */

// Derive sub-route segments from the request URI.
// The Kernel loads this file for all /api/public/* requests but does NOT
// inject $_GET['segments'], so we parse the URI path here.
$pubUri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$pubRel  = (string)preg_replace('#^/api/public/?#i', '', (string)$pubUri);
$segments = array_values(array_filter(explode('/', trim($pubRel, '/'))));
$first    = strtolower($segments[0] ?? '');

/** @var PDO|null $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;

$lang     = $_GET['lang'] ?? 'ar';
$page     = max(1, (int)($_GET['page'] ?? 1));
$per      = min(100, max(1, (int)($_GET['per'] ?? $_GET['limit'] ?? 25)));
$offset   = ($page - 1) * $per;
$tenantId = isset($_GET['tenant_id']) && is_numeric($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : null;

/* -------------------------------------------------------
 * Helper: safe PDO list query
 * ----------------------------------------------------- */
$pdoList = function (string $sql, array $params = []) use ($pdo): array {
    if (!$pdo instanceof PDO) return [];
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
};

$pdoOne = function (string $sql, array $params = []) use ($pdo): ?array {
    if (!$pdo instanceof PDO) return null;
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
};

$pdoCount = function (string $sql, array $params = []) use ($pdo): int {
    if (!$pdo instanceof PDO) return 0;
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return (int)$st->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
};

/* -------------------------------------------------------
 * Route: Home / health
 * ----------------------------------------------------- */
if ($first === '' || $first === 'home') {
    ResponseFormatter::success(['ok' => true, 'service' => 'QOOQZ Public API', 'time' => date('c')]);
    exit;
}

/* -------------------------------------------------------
 * Route: UI (theme / color settings)
 * ----------------------------------------------------- */
if ($first === 'ui') {
    $colors = [];
    if ($pdo instanceof PDO && $tenantId) {
        $colors = $pdoList(
            'SELECT `key`, `value`, `category` FROM color_settings WHERE tenant_id = ? AND is_active = 1 ORDER BY id',
            [$tenantId]
        );
    }
    ResponseFormatter::success(['ok' => true, 'ui' => $GLOBALS['PUBLIC_UI'] ?? [], 'colors' => $colors]);
    exit;
}

/* -------------------------------------------------------
 * Route: Products
 * GET /api/public/products[/{id}]
 * ----------------------------------------------------- */
if ($first === 'products') {
    $id = $_GET['id'] ?? (isset($segments[1]) && ctype_digit((string)$segments[1]) ? (int)$segments[1] : null);

    if ($id) {
        // Params: 1=$lang (for LEFT JOIN language_code), 2=$id (for WHERE p.id), 3=$tenantId (optional)
        $qParams = [$lang, (int)$id];
        $tid = '';
        if ($tenantId) { $tid = ' AND p.tenant_id = ?'; $qParams[] = $tenantId; }
        $row  = $pdoOne(
            "SELECT p.id, pt.name, p.price, p.is_active, p.sku, p.slug, p.tenant_id
               FROM products p
          LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = ?
              WHERE p.id = ?" . $tid . " LIMIT 1",
            $qParams
        );
        if ($row) ResponseFormatter::success(['ok' => true, 'product' => $row]);
        else      ResponseFormatter::notFound('Product not found');
        exit;
    }

    // $whereParams: params for WHERE clause only (no lang — lang is for the translation JOIN)
    $where       = 'WHERE p.is_active = 1';
    $whereParams = [];
    if ($tenantId) { $where .= ' AND p.tenant_id = ?'; $whereParams[] = $tenantId; }
    if (!empty($_GET['brand_id']) && is_numeric($_GET['brand_id'])) {
        $where .= ' AND p.brand_id = ?'; $whereParams[] = (int)$_GET['brand_id'];
    }
    if (!empty($_GET['is_featured'])) {
        $where .= ' AND p.is_featured = ?'; $whereParams[] = (int)$_GET['is_featured'];
    }
    if (!empty($_GET['category_id']) && is_numeric($_GET['category_id'])) {
        $where .= ' AND p.id IN (SELECT product_id FROM product_categories WHERE category_id = ?)';
        $whereParams[] = (int)$_GET['category_id'];
    }
    if (!empty($_GET['search'])) {
        // Escape LIKE wildcards in the search term to prevent unintended broad matches
        $kw = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($_GET['search'])) . '%';
        $where .= ' AND (p.slug LIKE ? OR p.id IN (SELECT product_id FROM product_translations WHERE name LIKE ? AND language_code = ?))';
        $whereParams[] = $kw;
        $whereParams[] = $kw;
        $whereParams[] = $lang;
    }

    $total = $pdoCount("SELECT COUNT(*) FROM products p $where", $whereParams);
    $rows  = $pdoList(
        "SELECT p.id, COALESCE(pt.name, p.slug) AS name, p.sku, p.slug, p.is_featured, p.tenant_id,
                pp.price, pp.currency_code,
                i.url AS image_url, i.thumb_url AS image_thumb_url
           FROM products p
      LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = ?
      LEFT JOIN product_pricing pp ON pp.product_id = p.id AND pp.variant_id IS NULL AND pp.is_active = 1
      LEFT JOIN images i ON i.owner_id = p.id AND i.is_main = 1
             AND i.image_type_id = (SELECT id FROM image_types WHERE code = 'product' LIMIT 1)
         $where ORDER BY p.id DESC LIMIT ? OFFSET ?",
        array_merge([$lang], $whereParams, [$per, $offset])
    );

    ResponseFormatter::success([
        'ok'   => true,
        'data' => $rows,
        'meta' => [
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per,
            'total_pages' => $per > 0 ? (int)ceil($total / $per) : 1,
        ]
    ]);
    exit;
}

/* -------------------------------------------------------
 * Route: Categories (public listing with images)
 * GET /api/public/categories[/{id}]
 * image_type: category (600×600 webp)
 * ----------------------------------------------------- */
if ($first === 'categories') {
    $id = $_GET['id'] ?? (isset($segments[1]) && ctype_digit((string)$segments[1]) ? (int)$segments[1] : null);

    // Pre-fetch category image_type id once to avoid per-row subquery
    $catImgRow    = $pdoOne('SELECT id FROM image_types WHERE code = ? LIMIT 1', ['category']);
    $catImgTypeId = (int)($catImgRow['id'] ?? 0);

    if ($id) {
        $row = $pdoOne(
            "SELECT c.id, COALESCE(ct.name, c.slug) AS name, c.slug, c.description,
                    i.url AS image_url, c.is_featured, c.is_active, c.parent_id, c.sort_order, c.tenant_id
               FROM categories c
          LEFT JOIN category_translations ct ON ct.category_id = c.id AND ct.language_code = ?
          LEFT JOIN images i ON i.owner_id = c.id AND i.is_main = 1 AND i.image_type_id = $catImgTypeId
              WHERE c.id = ? AND c.is_active = 1 LIMIT 1",
            [$lang, (int)$id]
        );
        if ($row) ResponseFormatter::success(['ok' => true, 'category' => $row]);
        else      ResponseFormatter::notFound('Category not found');
        exit;
    }

    // $whereParams: params for WHERE only (no lang — lang is for the translation JOIN)
    $where       = 'WHERE c.is_active = 1';
    $whereParams = [];
    if ($tenantId) { $where .= ' AND c.tenant_id = ?'; $whereParams[] = $tenantId; }
    if (isset($_GET['parent_id'])) {
        if ($_GET['parent_id'] === '0' || $_GET['parent_id'] === '') {
            $where .= ' AND (c.parent_id IS NULL OR c.parent_id = 0)';
        } elseif (is_numeric($_GET['parent_id'])) {
            $where .= ' AND c.parent_id = ?'; $whereParams[] = (int)$_GET['parent_id'];
        }
    }
    if (!empty($_GET['featured'])) { $where .= ' AND c.is_featured = ?'; $whereParams[] = 1; }

    $total = $pdoCount("SELECT COUNT(*) FROM categories c $where", $whereParams);
    $rows  = $pdoList(
        "SELECT c.id, COALESCE(ct.name, c.slug) AS name, c.slug,
                i.url AS image_url, c.is_featured, c.is_active, c.parent_id, c.sort_order, c.tenant_id,
                (SELECT COUNT(*) FROM products p
                  INNER JOIN product_categories pc ON pc.product_id = p.id AND pc.category_id = c.id
                  WHERE p.is_active = 1) AS product_count
           FROM categories c
      LEFT JOIN category_translations ct ON ct.category_id = c.id AND ct.language_code = ?
      LEFT JOIN images i ON i.owner_id = c.id AND i.is_main = 1 AND i.image_type_id = $catImgTypeId
          $where ORDER BY c.is_featured DESC, c.sort_order ASC, c.id ASC LIMIT ? OFFSET ?",
        array_merge([$lang], $whereParams, [$per, $offset])
    );

    ResponseFormatter::success([
        'ok'   => true,
        'data' => $rows,
        'meta' => [
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per,
            'total_pages' => $per > 0 ? (int)ceil($total / $per) : 1,
        ]
    ]);
    exit;
}

/* -------------------------------------------------------
 * Route: Jobs (public listing — no auth required)
 * GET /api/public/jobs[/{id}]
 * ----------------------------------------------------- */
if ($first === 'jobs') {
    $id = $_GET['id'] ?? (isset($segments[1]) && ctype_digit((string)$segments[1]) ? (int)$segments[1] : null);

    if ($id) {
        $row = $pdoOne("SELECT * FROM jobs WHERE id = ? AND status = 'published' LIMIT 1", [$id]);
        if ($row) ResponseFormatter::success(['ok' => true, 'job' => $row]);
        else      ResponseFormatter::notFound('Job not found');
        exit;
    }

    $where  = "WHERE j.status = 'published'";
    $params = [];
    if (!empty($_GET['is_featured'])) { $where .= ' AND j.is_featured = ?'; $params[] = 1; }
    if (!empty($_GET['is_urgent']))   { $where .= ' AND j.is_urgent = ?';   $params[] = 1; }
    if (!empty($_GET['is_remote']))   { $where .= ' AND j.is_remote = ?';   $params[] = 1; }
    if (!empty($_GET['employment_type'])) {
        $where .= ' AND j.employment_type = ?'; $params[] = $_GET['employment_type'];
    }
    if (!empty($_GET['search'])) {
        $where .= ' AND (j.title LIKE ? OR j.description LIKE ?)';
        $kw     = '%' . $_GET['search'] . '%';
        $params = array_merge($params, [$kw, $kw]);
    }

    $total = $pdoCount("SELECT COUNT(*) FROM jobs j $where", $params);
    $rows  = $pdoList(
        "SELECT j.id, j.title, j.employment_type, j.is_remote, j.is_featured, j.is_urgent,
                j.deadline, j.city_id, j.entity_id, j.created_at
           FROM jobs j $where ORDER BY j.is_featured DESC, j.created_at DESC LIMIT ? OFFSET ?",
        array_merge($params, [$per, $offset])
    );

    ResponseFormatter::success([
        'ok'   => true,
        'data' => $rows,
        'meta' => ['total' => $total, 'page' => $page, 'per_page' => $per,
                   'total_pages' => $per > 0 ? (int)ceil($total / $per) : 1],
    ]);
    exit;
}

/* -------------------------------------------------------
 * Route: Entity detail — full vendor profile
 * GET /api/public/entity/{id}         — full entity profile
 * GET /api/public/entity/{id}/products — entity products
 * ----------------------------------------------------- */
if ($first === 'entity') {
    $entityId = isset($segments[1]) && ctype_digit((string)$segments[1]) ? (int)$segments[1] : (int)($_GET['id'] ?? 0);
    $sub      = strtolower($segments[2] ?? '');

    if (!$entityId) {
        ResponseFormatter::notFound('Entity ID required');
        exit;
    }

    // Sub-route: entity products
    // Products have no entity_id column; use entity's tenant_id to scope products
    if ($sub === 'products') {
        $entityRow = $pdoOne('SELECT tenant_id FROM entities WHERE id = ? LIMIT 1', [$entityId]);
        if (!$entityRow) { ResponseFormatter::notFound('Entity not found'); exit; }
        $entityTenantId = (int)$entityRow['tenant_id'];
        $where  = 'WHERE p.is_active = 1 AND p.tenant_id = ?';
        $params = [$entityTenantId];
        if (!empty($_GET['category_id']) && is_numeric($_GET['category_id'])) {
            $where .= ' AND EXISTS (SELECT 1 FROM product_categories pc2 WHERE pc2.product_id = p.id AND pc2.category_id = ?)';
            $params[] = (int)$_GET['category_id'];
        }
        $total = $pdoCount("SELECT COUNT(*) FROM products p $where", $params);
        $rows  = $pdoList(
            "SELECT p.id, COALESCE(pt.name, p.slug) AS name, p.sku, p.slug,
                    p.is_featured, pp.price, pp.currency_code,
                    i.url AS image_url, i.thumb_url AS image_thumb_url
               FROM products p
          LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = ?
          LEFT JOIN product_pricing pp ON pp.product_id = p.id AND pp.variant_id IS NULL AND pp.is_active = 1
          LEFT JOIN images i ON i.owner_id = p.id AND i.is_main = 1
                 AND i.image_type_id = (SELECT id FROM image_types WHERE code = 'product' LIMIT 1)
              $where ORDER BY p.is_featured DESC, p.id DESC LIMIT ? OFFSET ?",
            array_merge([$lang], $params, [$per, $offset])
        );
        ResponseFormatter::success(['ok'=>true,'data'=>$rows,'meta'=>[
            'total'=>$total,'page'=>$page,'per_page'=>$per,
            'total_pages'=>$per > 0 ? (int)ceil($total/$per) : 1
        ]]);
        exit;
    }

    // Full entity profile — images fetched from images table (entity_logo / entity_cover)
    $entity = $pdoOne(
        "SELECT e.id, e.store_name, e.slug, e.vendor_type, e.store_type,
                e.is_verified, e.phone, e.mobile, e.email, e.website_url AS website,
                e.status, e.tenant_id, e.created_at,
                et.name AS type_name, et.icon AS type_icon,
                logo_i.url AS logo_url, logo_i.thumb_url AS logo_thumb_url,
                cover_i.url AS cover_url
           FROM entities e
      LEFT JOIN entity_types et ON et.id = e.entity_type_id
      LEFT JOIN images logo_i ON logo_i.owner_id = e.id AND logo_i.is_main = 1
             AND logo_i.image_type_id = (SELECT id FROM image_types WHERE code = 'entity_logo' LIMIT 1)
      LEFT JOIN images cover_i ON cover_i.owner_id = e.id AND cover_i.is_main = 1
             AND cover_i.image_type_id = (SELECT id FROM image_types WHERE code = 'entity_cover' LIMIT 1)
          WHERE e.id = ? AND e.status = 'approved' LIMIT 1",
        [$entityId]
    );

    if (!$entity) {
        ResponseFormatter::notFound('Entity not found');
        exit;
    }

    // Translation override (store_name / description)
    $translation = $pdoOne(
        "SELECT store_name, description FROM entity_translations
          WHERE entity_id = ? AND language_code = ? LIMIT 1",
        [$entityId, $lang]
    );
    if ($translation) {
        if (!empty($translation['store_name'])) $entity['store_name'] = $translation['store_name'];
        if (!empty($translation['description'])) $entity['description'] = $translation['description'];
    }

    // Working hours
    $workingHours = $pdoList(
        "SELECT day_of_week, open_time, close_time, is_closed
           FROM entities_working_hours
          WHERE entity_id = ? ORDER BY FIELD(day_of_week,'sunday','monday','tuesday','wednesday','thursday','friday','saturday')",
        [$entityId]
    );

    // Addresses (with coordinates)
    $addresses = $pdoList(
        "SELECT id, label, address_line1, address_line2, city_id, country_id,
                latitude, longitude, is_primary
           FROM addresses
          WHERE owner_type = 'entity' AND owner_id = ? ORDER BY is_primary DESC, id ASC LIMIT 5",
        [$entityId]
    );

    // Payment methods
    $paymentMethods = $pdoList(
        "SELECT pm.id, pm.name, pm.code, pm.icon, epm.is_active
           FROM entity_payment_methods epm
      LEFT JOIN payment_methods pm ON pm.id = epm.payment_method_id
          WHERE epm.entity_id = ? AND epm.is_active = 1",
        [$entityId]
    );

    // Attributes
    $attributes = $pdoList(
        "SELECT ea.attribute_name, eav.value
           FROM entities_attributes ea
      LEFT JOIN entities_attribute_values eav ON eav.attribute_id = ea.id AND eav.entity_id = ?
          WHERE ea.entity_type_id IS NULL OR ea.entity_type_id = (SELECT entity_type_id FROM entities WHERE id = ?)
          LIMIT 20",
        [$entityId, $entityId]
    );

    ResponseFormatter::success([
        'ok'      => true,
        'data'    => array_merge($entity, [
            'working_hours'   => $workingHours,
            'addresses'       => $addresses,
            'payment_methods' => $paymentMethods,
            'attributes'      => $attributes,
        ]),
    ]);
    exit;
}

/* -------------------------------------------------------
 * Route: Entities (public listing)
 * GET /api/public/entities[/{id}]
 * ----------------------------------------------------- */
if ($first === 'entities') {
    $id = $_GET['id'] ?? (isset($segments[1]) && ctype_digit((string)$segments[1]) ? (int)$segments[1] : null);

    if ($id) {
        $row = $pdoOne("SELECT * FROM entities WHERE id = ? AND status = 'approved' LIMIT 1", [$id]);
        if ($row) ResponseFormatter::success(['ok' => true, 'entity' => $row]);
        else      ResponseFormatter::notFound('Entity not found');
        exit;
    }

    $where  = "WHERE e.status = 'approved'";
    $params = [];
    if ($tenantId)                          { $where .= ' AND e.tenant_id = ?';    $params[] = $tenantId; }
    if (!empty($_GET['vendor_type']))        { $where .= ' AND e.vendor_type = ?'; $params[] = $_GET['vendor_type']; }
    if (!empty($_GET['is_verified']))        { $where .= ' AND e.is_verified = ?'; $params[] = 1; }

    $total = $pdoCount("SELECT COUNT(*) FROM entities e $where", $params);
    $rows  = $pdoList(
        "SELECT e.id, e.store_name, e.slug, e.vendor_type, e.is_verified, e.tenant_id,
                i.url AS logo_url, i.thumb_url AS logo_thumb_url
           FROM entities e
      LEFT JOIN images i ON i.owner_id = e.id AND i.is_main = 1
             AND i.image_type_id = (SELECT id FROM image_types WHERE code = 'entity_logo' LIMIT 1)
          $where ORDER BY e.is_verified DESC, e.id DESC LIMIT ? OFFSET ?",
        array_merge($params, [$per, $offset])
    );

    ResponseFormatter::success([
        'ok'   => true,
        'data' => $rows,
        'meta' => ['total' => $total, 'page' => $page, 'per_page' => $per,
                   'total_pages' => $per > 0 ? (int)ceil($total / $per) : 1],
    ]);
    exit;
}

/* -------------------------------------------------------
 * Route: Tenants (public listing)
 * GET /api/public/tenants[/{id}]
 * ----------------------------------------------------- */
if ($first === 'tenants') {
    $id = $_GET['id'] ?? (isset($segments[1]) && ctype_digit((string)$segments[1]) ? (int)$segments[1] : null);

    if ($id) {
        $row = $pdoOne("SELECT id, name, store_name, domain, status, plan_id FROM tenants WHERE id = ? AND status = 'active' LIMIT 1", [$id]);
        if ($row) ResponseFormatter::success(['ok' => true, 'tenant' => $row]);
        else      ResponseFormatter::notFound('Tenant not found');
        exit;
    }

    $where  = "WHERE t.status = 'active'";
    $params = [];
    if (!empty($_GET['search'])) {
        $where .= ' AND (t.name LIKE ? OR t.store_name LIKE ? OR t.domain LIKE ?)';
        $kw     = '%' . $_GET['search'] . '%';
        $params = [$kw, $kw, $kw];
    }

    $total = $pdoCount("SELECT COUNT(*) FROM tenants t $where", $params);
    $rows  = $pdoList(
        "SELECT t.id, t.name, t.store_name, t.domain, t.status,
                sp.plan_name
           FROM tenants t
      LEFT JOIN subscription_plans sp ON sp.id = (
              SELECT plan_id FROM subscriptions s
               WHERE s.tenant_id = t.id AND s.status IN ('active','trial')
               ORDER BY s.id DESC LIMIT 1
          )
          $where ORDER BY t.id DESC LIMIT ? OFFSET ?",
        array_merge($params, [$per, $offset])
    );

    ResponseFormatter::success([
        'ok'   => true,
        'data' => $rows,
        'meta' => ['total' => $total, 'page' => $page, 'per_page' => $per,
                   'total_pages' => $per > 0 ? (int)ceil($total / $per) : 1],
    ]);
    exit;
}

/* -------------------------------------------------------
 * Legacy: Vendors (kept for backwards compatibility)
 * ----------------------------------------------------- */
if ($first === 'vendors') {
    $id = $_GET['id'] ?? (isset($segments[1]) && ctype_digit((string)$segments[1]) ? (int)$segments[1] : null);
    if ($id) {
        $row = $pdoOne('SELECT id, store_name AS name, is_active FROM entities WHERE id = ? LIMIT 1', [$id]);
        if ($row) ResponseFormatter::success(['ok' => true, 'vendor' => $row]);
        else      ResponseFormatter::notFound('Vendor not found');
    } else {
        $rows = $pdoList('SELECT id, store_name AS name, is_active FROM entities LIMIT ? OFFSET ?', [$per, $offset]);
        ResponseFormatter::success(['ok' => true, 'data' => $rows]);
    }
    exit;
}

/* -------------------------------------------------------
 * 404 fallback
 * ----------------------------------------------------- */
ResponseFormatter::notFound('Public route not found: /' . ($first ?: ''));