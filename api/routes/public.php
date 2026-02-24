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

$segments = $_GET['segments'] ?? [];
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

    $where  = 'WHERE p.is_active = 1';
    $params = [$lang];
    if ($tenantId) { $where .= ' AND p.tenant_id = ?'; $params[] = $tenantId; }
    if (!empty($_GET['brand_id']) && is_numeric($_GET['brand_id'])) {
        $where .= ' AND p.brand_id = ?'; $params[] = (int)$_GET['brand_id'];
    }
    if (!empty($_GET['is_featured'])) {
        $where .= ' AND p.is_featured = ?'; $params[] = (int)$_GET['is_featured'];
    }

    $total = $pdoCount("SELECT COUNT(*) FROM products p $where", $params);
    $rows  = $pdoList(
        "SELECT p.id, COALESCE(pt.name, p.slug) AS name, p.price, p.sku, p.slug, p.is_featured, p.tenant_id
           FROM products p
      LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = ?
         $where ORDER BY p.id DESC LIMIT ? OFFSET ?",
        array_merge($params, [$per, $offset])
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
 * Route: Entities (public listing)
 * GET /api/public/entities[/{id}]
 * ----------------------------------------------------- */
if ($first === 'entities') {
    $id = $_GET['id'] ?? (isset($segments[1]) && ctype_digit((string)$segments[1]) ? (int)$segments[1] : null);

    if ($id) {
        $row = $pdoOne("SELECT * FROM entities WHERE id = ? AND status = 'active' LIMIT 1", [$id]);
        if ($row) ResponseFormatter::success(['ok' => true, 'entity' => $row]);
        else      ResponseFormatter::notFound('Entity not found');
        exit;
    }

    $where  = "WHERE e.status = 'active'";
    $params = [];
    if ($tenantId)                          { $where .= ' AND e.tenant_id = ?';    $params[] = $tenantId; }
    if (!empty($_GET['vendor_type']))        { $where .= ' AND e.vendor_type = ?'; $params[] = $_GET['vendor_type']; }
    if (!empty($_GET['is_verified']))        { $where .= ' AND e.is_verified = ?'; $params[] = 1; }

    $total = $pdoCount("SELECT COUNT(*) FROM entities e $where", $params);
    $rows  = $pdoList(
        "SELECT e.id, e.store_name, e.slug, e.vendor_type, e.is_verified, e.logo_url, e.tenant_id
           FROM entities e $where ORDER BY e.is_verified DESC, e.id DESC LIMIT ? OFFSET ?",
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
        $row = $pdoOne("SELECT id, name, store_name, domain, is_active, plan_id FROM tenants WHERE id = ? AND is_active = 1 LIMIT 1", [$id]);
        if ($row) ResponseFormatter::success(['ok' => true, 'tenant' => $row]);
        else      ResponseFormatter::notFound('Tenant not found');
        exit;
    }

    $where  = 'WHERE t.is_active = 1';
    $params = [];
    if (!empty($_GET['search'])) {
        $where .= ' AND (t.name LIKE ? OR t.store_name LIKE ? OR t.domain LIKE ?)';
        $kw     = '%' . $_GET['search'] . '%';
        $params = [$kw, $kw, $kw];
    }

    $total = $pdoCount("SELECT COUNT(*) FROM tenants t $where", $params);
    $rows  = $pdoList(
        "SELECT t.id, t.name, t.store_name, t.domain, t.is_active,
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