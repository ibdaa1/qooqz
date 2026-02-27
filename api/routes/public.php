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
 * Route: Current user (for JS-based auth display)
 * GET /api/public/me
 * Returns logged-in user info from session, or null.
 * Used by public.js to update header login button without
 * relying on PHP session detection in public_context.php.
 * ----------------------------------------------------- */
if ($first === 'me') {
    $sessUser = $_SESSION['user'] ?? null;
    $sessUserId = isset($sessUser['id']) ? (int)$sessUser['id'] : 0;
    if ($sessUser && $sessUserId > 0) {
        ResponseFormatter::success([
            'user' => [
                'id'    => $sessUserId,
                'name'  => $sessUser['name'] ?? $sessUser['username'] ?? '',
                'email' => $sessUser['email'] ?? '',
            ],
        ]);
    } else {
        ResponseFormatter::success(['user' => null]);
    }
    exit;
}

/* -------------------------------------------------------
 * Route: UI (theme / color / font / design / button / card settings)
 * GET /api/public/ui?tenant_id=X
 * All settings needed by the frontend to render dynamic theme.
 * color_settings columns: setting_key, color_value (NOT key/value)
 * ----------------------------------------------------- */
if ($first === 'ui') {
    $tid = $tenantId ?? 1;

    // Look up active theme_id for this tenant (mirrors AdminUiThemeLoader::getActiveThemeId)
    $uiThemeRow = $pdoOne('SELECT id FROM themes WHERE tenant_id = ? AND is_active = 1 LIMIT 1', [$tid]);
    if (!$uiThemeRow) {
        $uiThemeRow = $pdoOne('SELECT id FROM themes WHERE tenant_id = ? AND is_default = 1 LIMIT 1', [$tid]);
    }
    $uiThemeId  = $uiThemeRow ? (int)$uiThemeRow['id'] : null;
    $uiTidCond  = $uiThemeId ? ' AND theme_id = ?' : '';
    $uiP = static function(array $base) use ($uiThemeId): array {
        return $uiThemeId ? array_merge($base, [$uiThemeId]) : $base;
    };

    $colors  = $pdoList(
        'SELECT setting_key AS `key`, color_value AS value, category FROM color_settings WHERE tenant_id = ? AND is_active = 1' . $uiTidCond . ' ORDER BY sort_order, id',
        $uiP([$tid])
    );
    $fonts   = $pdoList(
        'SELECT setting_key, font_family, font_size, font_weight, line_height, category FROM font_settings WHERE tenant_id = ? AND is_active = 1' . $uiTidCond . ' ORDER BY sort_order',
        $uiP([$tid])
    );
    $designs = $pdoList(
        'SELECT setting_key, setting_value, setting_type, category FROM design_settings WHERE tenant_id = ? AND is_active = 1' . $uiTidCond . ' ORDER BY sort_order',
        $uiP([$tid])
    );
    $buttons = $pdoList(
        'SELECT slug, button_type, background_color, text_color, border_color, border_width, border_radius, padding, font_size, font_weight, hover_background_color, hover_text_color FROM button_styles WHERE tenant_id = ? AND is_active = 1' . $uiTidCond . ' ORDER BY button_type',
        $uiP([$tid])
    );
    $cards = $pdoList(
        'SELECT slug, card_type, background_color, border_color, border_width, border_radius, shadow_style, padding FROM card_styles WHERE tenant_id = ? AND is_active = 1' . $uiTidCond . ' ORDER BY card_type',
        $uiP([$tid])
    );

    // Generate CSS string from all settings (mirrors AdminUiThemeLoader::generateCss)
    // Values are escaped to prevent CSS injection (</style> breakout protection)
    $esc = function(string $v): string { return str_replace('</style', '<\\/style', htmlspecialchars($v, ENT_QUOTES, 'UTF-8')); };
    $css = ":root {\n";
    foreach ($colors as $c) {
        if (!empty($c['key']) && !empty($c['value'])) {
            $css .= '  --' . preg_replace('/[^a-z0-9_\-]/', '-', strtolower((string)$c['key'])) . ': ' . $esc((string)$c['value']) . ";\n";
        }
    }
    // CSS variable aliases: map DB setting_key names (underscore) to --pub-* / --color-*
    // so public.css and variables.css receive the correct DB values directly.
    $uiAliases = [
        'primary_color'        => ['color-primary',  'pub-primary'],
        'secondary_color'      => ['color-secondary', 'pub-secondary'],
        'accent_color'         => ['color-accent',    'pub-accent'],
        'background_main'      => ['pub-bg'],
        'background_secondary' => ['pub-surface'],
        'text_primary'         => ['pub-text'],
        'text_secondary'       => ['pub-muted'],
        'border_color'         => ['pub-border'],
    ];
    $colorKeyVal = [];
    foreach ($colors as $c) {
        if (!empty($c['key'])) {
            $colorKeyVal[$c['key']] = $c['value'] ?? '';
        }
    }
    foreach ($uiAliases as $srcKey => $aliases) {
        if (empty($colorKeyVal[$srcKey])) continue;
        $val = $esc($colorKeyVal[$srcKey]);
        foreach ($aliases as $alias) {
            $css .= '  --' . $alias . ': ' . $val . ";\n";
        }
    }
    foreach ($fonts as $f) {
        if (!empty($f['setting_key'])) {
            $sk = preg_replace('/[^a-z0-9_\-]/', '-', strtolower((string)$f['setting_key']));
            if (!empty($f['font_family'])) $css .= '  --' . $sk . '-family: ' . $esc((string)$f['font_family']) . ";\n";
            if (!empty($f['font_size']))   $css .= '  --' . $sk . '-size: '   . $esc((string)$f['font_size'])   . ";\n";
            if (!empty($f['font_weight'])) $css .= '  --' . $sk . '-weight: ' . $esc((string)$f['font_weight']) . ";\n";
        }
    }
    foreach ($designs as $d) {
        if (!empty($d['setting_key']) && !empty($d['setting_value'])) {
            $css .= '  --' . preg_replace('/[^a-z0-9_\-]/', '-', strtolower((string)$d['setting_key'])) . ': ' . $esc((string)$d['setting_value']) . ";\n";
        }
    }
    $css .= "}\n";
    foreach ($buttons as $b) {
        if (empty($b['slug'])) continue;
        $css .= '.btn-' . preg_replace('/[^a-z0-9_\-]/', '-', (string)$b['slug']) . " {\n";
        if (!empty($b['background_color'])) $css .= '  background-color: ' . $esc((string)$b['background_color']) . ";\n";
        if (!empty($b['text_color']))       $css .= '  color: '            . $esc((string)$b['text_color'])       . ";\n";
        if (!empty($b['border_color']))     $css .= '  border: '           . (int)$b['border_width'] . 'px solid ' . $esc((string)$b['border_color']) . ";\n";
        if (isset($b['border_radius']))     $css .= '  border-radius: '    . (int)$b['border_radius'] . "px;\n";
        if (!empty($b['padding']))          $css .= '  padding: '          . $esc((string)$b['padding'])          . ";\n";
        if (!empty($b['font_size']))        $css .= '  font-size: '        . $esc((string)$b['font_size'])        . ";\n";
        if (!empty($b['font_weight']))      $css .= '  font-weight: '      . $esc((string)$b['font_weight'])      . ";\n";
        $css .= "}\n";
    }
    foreach ($cards as $c) {
        if (empty($c['slug'])) continue;
        $css .= '.card-' . preg_replace('/[^a-z0-9_\-]/', '-', (string)$c['slug']) . " {\n";
        if (!empty($c['background_color'])) $css .= '  background-color: ' . $esc((string)$c['background_color']) . ";\n";
        if (!empty($c['border_color']))     $css .= '  border: '           . (int)$c['border_width'] . 'px solid ' . $esc((string)$c['border_color']) . ";\n";
        if (isset($c['border_radius']))     $css .= '  border-radius: '    . (int)$c['border_radius'] . "px;\n";
        if (!empty($c['shadow_style']))     $css .= '  box-shadow: '       . $esc((string)$c['shadow_style'])      . ";\n";
        if (!empty($c['padding']))          $css .= '  padding: '          . $esc((string)$c['padding'])           . ";\n";
        $css .= "}\n";
    }

    ResponseFormatter::success([
        'ok'           => true,
        'ui'           => $GLOBALS['PUBLIC_UI'] ?? [],
        'colors'       => $colors,
        'fonts'        => $fonts,
        'design'       => $designs,
        'buttons'      => $buttons,
        'cards'        => $cards,
        'generated_css'=> $css,
    ]);
    exit;
}

/* -------------------------------------------------------
 * Route: Products
 * GET /api/public/products[/{id}]
 * ----------------------------------------------------- */
if ($first === 'products') {
    $id = $_GET['id'] ?? (isset($segments[1]) && ctype_digit((string)$segments[1]) ? (int)$segments[1] : null);
    if (!$id && !empty($_GET['slug'])) {
        $slugParams = [$_GET['slug']];
        $slugCond = ' AND is_active = 1';
        if ($tenantId) { $slugCond .= ' AND tenant_id = ?'; $slugParams[] = $tenantId; }
        $slugRow = $pdoOne('SELECT id FROM products WHERE slug = ?' . $slugCond . ' LIMIT 1', $slugParams);
        if ($slugRow) $id = (int)$slugRow['id'];
    }

    if ($id) {
        // Full product detail — scalar subqueries for pricing and images to avoid
        // multi-row JOIN issues and dependency on pp.is_active in product_pricing.
        $qParams = [$lang, (int)$id];
        $tidCond = '';
        if ($tenantId) { $tidCond = ' AND p.tenant_id = ?'; $qParams[] = $tenantId; }
        $row = $pdoOne(
            "SELECT p.id, p.sku, p.slug, p.barcode, p.brand_id,
                    p.is_active, p.is_featured, p.is_new, p.is_bestseller,
                    p.stock_quantity, p.stock_status, p.rating_average, p.rating_count,
                    p.views_count, p.tenant_id,
                    COALESCE(pt.name, p.slug) AS name,
                    pt.short_description, pt.description, pt.specifications, pt.meta_title,
                    (SELECT pp.price FROM product_pricing pp
                       WHERE pp.product_id = p.id ORDER BY pp.id ASC LIMIT 1) AS price,
                    (SELECT pp.compare_at_price FROM product_pricing pp
                       WHERE pp.product_id = p.id ORDER BY pp.id ASC LIMIT 1) AS compare_at_price,
                    (SELECT pp.currency_code FROM product_pricing pp
                       WHERE pp.product_id = p.id ORDER BY pp.id ASC LIMIT 1) AS currency_code,
                    b.name AS brand_name,
                    (SELECT i.url FROM images i WHERE i.owner_id = p.id
                       ORDER BY i.is_main DESC, i.sort_order ASC, i.id ASC LIMIT 1) AS image_url,
                    (SELECT i.thumb_url FROM images i WHERE i.owner_id = p.id
                       ORDER BY i.is_main DESC, i.sort_order ASC, i.id ASC LIMIT 1) AS image_thumb_url
               FROM products p
          LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = ?
          LEFT JOIN brands b ON b.id = p.brand_id
              WHERE p.id = ?" . $tidCond,
            $qParams
        );
        if (!$row) { ResponseFormatter::notFound('Product not found'); exit; }

        // All product images (gallery)
        $productImages = $pdoList(
            "SELECT i.id, i.url, i.thumb_url, i.alt_text, i.sort_order
               FROM images i
               JOIN image_types it ON it.id = i.image_type_id AND it.code = 'product'
              WHERE i.owner_id = ?
              ORDER BY i.is_main DESC, i.sort_order ASC, i.id ASC LIMIT 10",
            [(int)$id]
        );

        // Categories
        $productCategories = $pdoList(
            "SELECT c.id, COALESCE(ct.name, c.slug) AS name, c.slug
               FROM categories c
         INNER JOIN product_categories pc ON pc.category_id = c.id AND pc.product_id = ?
          LEFT JOIN category_translations ct ON ct.category_id = c.id AND ct.language_code = ?
              LIMIT 5",
            [(int)$id, $lang]
        );

        // Related products (same first category)
        $related = [];
        if (!empty($productCategories[0]['id'])) {
            $related = $pdoList(
                "SELECT p2.id, COALESCE(pt2.name, p2.slug) AS name, p2.slug,
                        pp2.price, pp2.currency_code,
                        i2.url AS image_url
                   FROM products p2
             INNER JOIN product_categories pc2 ON pc2.product_id = p2.id AND pc2.category_id = ?
              LEFT JOIN product_translations pt2 ON pt2.product_id = p2.id AND pt2.language_code = ?
              LEFT JOIN product_pricing pp2 ON pp2.product_id = p2.id AND pp2.variant_id IS NULL
              LEFT JOIN images i2 ON i2.owner_id = p2.id AND i2.is_main = 1
                     AND i2.image_type_id = (SELECT id FROM image_types WHERE code = 'product' LIMIT 1)
                  WHERE p2.is_active = 1 AND p2.id != ? AND p2.tenant_id = ?
                  ORDER BY p2.is_featured DESC, p2.id DESC LIMIT 8",
                [(int)$productCategories[0]['id'], $lang, (int)$id, (int)$row['tenant_id']]
            );
        }

        ResponseFormatter::success([
            'ok'         => true,
            'product'    => $row,
            'images'     => $productImages,
            'categories' => $productCategories,
            'related'    => $related,
        ]);
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
      LEFT JOIN product_pricing pp ON pp.product_id = p.id AND pp.variant_id IS NULL
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
        // Full job detail with translated title/description via job_translations
        $row = $pdoOne(
            "SELECT j.*, COALESCE(jt.job_title, j.slug) AS title,
                    jt.description, jt.requirements, jt.benefits
               FROM jobs j
          LEFT JOIN job_translations jt ON jt.job_id = j.id AND jt.language_code = ?
              WHERE j.id = ? AND j.status NOT IN ('cancelled','filled','closed') LIMIT 1",
            [$lang, $id]
        );
        if ($row) ResponseFormatter::success(['ok' => true, 'job' => $row]);
        else      ResponseFormatter::notFound('Job not found');
        exit;
    }

    // $whereParams: params for WHERE only (no $lang — $lang is for the JOIN)
    $where       = "WHERE j.status NOT IN ('cancelled', 'filled', 'closed')";
    $whereParams = [];
    if (!empty($_GET['is_featured']))    { $where .= ' AND j.is_featured = ?'; $whereParams[] = 1; }
    if (!empty($_GET['is_urgent']))      { $where .= ' AND j.is_urgent = ?';   $whereParams[] = 1; }
    if (!empty($_GET['is_remote']))      { $where .= ' AND j.is_remote = ?';   $whereParams[] = 1; }
    if (!empty($_GET['employment_type'])) {
        // Frontend sends full_time/part_time/etc., stored in j.job_type (not j.employment_type)
        $where .= ' AND j.job_type = ?'; $whereParams[] = $_GET['employment_type'];
    }
    if (!empty($_GET['search'])) {
        // Escape LIKE wildcards so user-typed % or _ match literally; SQL injection prevented by PDO params.
        $kw = '%' . str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], trim($_GET['search'])) . '%';
        $where .= ' AND (j.slug LIKE ? OR j.id IN (SELECT job_id FROM job_translations WHERE job_title LIKE ? AND language_code = ?))';
        $whereParams[] = $kw;
        $whereParams[] = $kw;
        $whereParams[] = $lang;
    }

    $total = $pdoCount("SELECT COUNT(*) FROM jobs j $where", $whereParams);
    $rows  = $pdoList(
        "SELECT j.id, COALESCE(jt.job_title, j.slug) AS title,
                j.job_type AS employment_type,
                j.is_remote, j.is_featured, j.is_urgent,
                j.application_deadline AS deadline,
                j.salary_min, j.salary_max, j.salary_currency,
                j.city_id, j.entity_id, j.created_at
           FROM jobs j
      LEFT JOIN job_translations jt ON jt.job_id = j.id AND jt.language_code = ?
         $where ORDER BY j.is_featured DESC, j.created_at DESC LIMIT ? OFFSET ?",
        array_merge([$lang], $whereParams, [$per, $offset])
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
          LEFT JOIN product_pricing pp ON pp.product_id = p.id AND pp.variant_id IS NULL
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
    // entities table has vendor_type (varchar code), not entity_type_id — join entity_types by code
    $entity = $pdoOne(
        "SELECT e.id, e.store_name, e.slug, e.vendor_type, e.store_type,
                e.is_verified, e.phone, e.mobile, e.email, e.website_url AS website,
                e.status, e.tenant_id, e.created_at,
                et.name AS type_name,
                logo_i.url AS logo_url, logo_i.thumb_url AS logo_thumb_url,
                cover_i.url AS cover_url
           FROM entities e
      LEFT JOIN entity_types et ON et.code = e.vendor_type
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

    // Addresses (with coordinates) — addresses table has no label column
    $addresses = $pdoList(
        "SELECT id, address_line1, address_line2, city_id, country_id,
                postal_code, latitude, longitude, is_primary
           FROM addresses
          WHERE owner_type = 'entity' AND owner_id = ? ORDER BY is_primary DESC, id ASC LIMIT 5",
        [$entityId]
    );

    // Payment methods — payment_methods columns: method_name, method_key, icon_url
    $paymentMethods = $pdoList(
        "SELECT pm.id, pm.method_name AS name, pm.method_key AS code, pm.icon_url AS icon, epm.is_active
           FROM entity_payment_methods epm
      LEFT JOIN payment_methods pm ON pm.id = epm.payment_method_id
          WHERE epm.entity_id = ? AND epm.is_active = 1",
        [$entityId]
    );

    // Attributes — name from entities_attribute_translations; entity_type_id via entity_types.code = vendor_type
    $attributes = $pdoList(
        "SELECT COALESCE(eat.name, ea.attribute_type) AS attribute_name, eav.value
           FROM entities_attributes ea
      LEFT JOIN entities_attribute_translations eat ON eat.attribute_id = ea.id AND eat.language_code = ?
      LEFT JOIN entities_attribute_values eav ON eav.attribute_id = ea.id AND eav.entity_id = ?
          WHERE ea.entity_type_id IS NULL
             OR ea.entity_type_id = (
                    SELECT et.id FROM entity_types et
                    INNER JOIN entities ev ON et.code = ev.vendor_type
                    WHERE ev.id = ? LIMIT 1
                )
          LIMIT 20",
        [$lang, $entityId, $entityId]
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
        $row = $pdoOne("SELECT id, name, domain, status FROM tenants WHERE id = ? AND status = 'active' LIMIT 1", [$id]);
        if ($row) ResponseFormatter::success(['ok' => true, 'tenant' => $row]);
        else      ResponseFormatter::notFound('Tenant not found');
        exit;
    }

    $where  = "WHERE t.status = 'active'";
    $params = [];
    if (!empty($_GET['search'])) {
        $where .= ' AND (t.name LIKE ? OR t.domain LIKE ?)';
        $kw     = '%' . $_GET['search'] . '%';
        $params = [$kw, $kw];
    }

    $total = $pdoCount("SELECT COUNT(*) FROM tenants t $where", $params);
    $rows  = $pdoList(
        "SELECT t.id, t.name, t.domain, t.status,
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
 * Route: Entity Types (public list — used for filter dropdown)
 * GET /api/public/entity_types
 * ----------------------------------------------------- */
if ($first === 'entity_types') {
    $rows = $pdoList("SELECT id, code, name, description FROM entity_types ORDER BY id ASC");
    ResponseFormatter::success(['ok' => true, 'data' => $rows]);
    exit;
}

/* -------------------------------------------------------
 * Route: Homepage Sections
 * GET /api/public/homepage_sections?tenant_id=X&lang=Y
 * Returns active sections with translated title/subtitle
 * ----------------------------------------------------- */
if ($first === 'homepage_sections') {
    if (!$tenantId) { ResponseFormatter::success(['ok' => true, 'data' => []]); exit; }
    $rows = $pdoList(
        "SELECT hs.id, hs.section_type, hs.component, hs.layout_type, hs.layout_config,
                hs.items_per_row, hs.background_color, hs.text_color, hs.padding,
                hs.custom_css, hs.data_source, hs.sort_order, hs.is_active,
                COALESCE(hst.title, hs.title)       AS title,
                COALESCE(hst.subtitle, hs.subtitle) AS subtitle
           FROM homepage_sections hs
      LEFT JOIN homepage_section_translations hst
             ON hst.section_id = hs.id AND hst.language_code = ?
          WHERE hs.tenant_id = ? AND hs.is_active = 1
          ORDER BY hs.sort_order ASC, hs.id ASC",
        [$lang, $tenantId]
    );
    ResponseFormatter::success(['ok' => true, 'data' => $rows]);
    exit;
}

/* -------------------------------------------------------
 * Route: Banners (public listing — active, date-ranged)
 * GET /api/public/banners?tenant_id=X[&position=X]
 * ----------------------------------------------------- */
if ($first === 'banners') {
    if (!$tenantId) { ResponseFormatter::success(['ok' => true, 'data' => []]); exit; }
    $banWhere  = 'WHERE b.tenant_id = ? AND b.is_active = 1
                    AND (b.start_date IS NULL OR b.start_date <= NOW())
                    AND (b.end_date   IS NULL OR b.end_date   >= NOW())';
    $banParams = [$tenantId];
    if (!empty($_GET['position'])) { $banWhere .= ' AND b.position = ?'; $banParams[] = $_GET['position']; }
    $rows = $pdoList(
        "SELECT b.id, b.title, b.subtitle, b.image_url, b.mobile_image_url,
                b.link_url, b.link_text, b.background_color, b.text_color, b.sort_order, b.position
           FROM banners b $banWhere ORDER BY b.sort_order ASC, b.id ASC LIMIT 20",
        $banParams
    );
    ResponseFormatter::success(['ok' => true, 'data' => $rows]);
    exit;
}

/* -------------------------------------------------------
 * Route: Discounts (public active discounts for tenant)
 * GET /api/public/discounts?tenant_id=X&lang=Y
 * ----------------------------------------------------- */
if ($first === 'discounts') {
    if (!$tenantId) { ResponseFormatter::success(['ok' => true, 'data' => []]); exit; }
    $rows = $pdoList(
        "SELECT d.id, d.code, d.type, d.auto_apply, d.currency_code,
                d.starts_at, d.ends_at, d.max_redemptions, d.current_redemptions,
                COALESCE(dt.name, d.code) AS title,
                dt.description, dt.terms_and_conditions
           FROM discounts d
      LEFT JOIN discount_translations dt ON dt.discount_id = d.id AND dt.language_code = ?
          WHERE d.entity_id IN (SELECT id FROM entities WHERE tenant_id = ?)
            AND d.status = 'active'
            AND (d.starts_at IS NULL OR d.starts_at <= NOW())
            AND (d.ends_at   IS NULL OR d.ends_at   >= NOW())
          ORDER BY d.id DESC LIMIT 20",
        [$lang, $tenantId]
    );
    ResponseFormatter::success(['ok' => true, 'data' => $rows]);
    exit;
}

/* -------------------------------------------------------
 * Route: Brands (public listing)
 * GET /api/public/brands?tenant_id=X&lang=Y[&is_featured=1][&per=N][&page=N]
 * ----------------------------------------------------- */
if ($first === 'brands') {
    if (!$tenantId) {
        ResponseFormatter::success(['data' => [], 'meta' => ['total' => 0, 'page' => 1, 'per_page' => $per, 'total_pages' => 0]]);
        exit;
    }
    $bWhere  = 'WHERE b.tenant_id = ? AND b.is_active = 1';
    $bParams = [$tenantId];
    if (!empty($_GET['is_featured'])) { $bWhere .= ' AND b.is_featured = 1'; }

    $bCount = $pdoCount("SELECT COUNT(*) FROM brands b $bWhere", $bParams);
    $rows   = $pdoList(
        "SELECT b.id, b.slug, b.logo_url, b.website_url, b.is_featured,
                COALESCE(bt.name, b.slug) AS name,
                COALESCE(bt.description, '') AS description
           FROM brands b
      LEFT JOIN brand_translations bt ON bt.brand_id = b.id AND bt.language_code = ?
         $bWhere
         ORDER BY b.is_featured DESC, b.sort_order ASC, b.id ASC
         LIMIT $per OFFSET $offset",
        array_merge([$lang], $bParams)
    );
    ResponseFormatter::success([
        'data' => $rows,
        'meta' => [
            'total'       => $bCount,
            'page'        => $page,
            'per_page'    => $per,
            'total_pages' => (int)ceil($bCount / $per),
        ],
    ]);
    exit;
}

/* -------------------------------------------------------
 * /api/public/cart — DB-backed user cart
 * Requires authenticated session.
 * Sub-paths: (none)=GET, add=POST, update=POST, remove=POST, clear=POST
 * ----------------------------------------------------- */
if ($first === 'cart') {
    $cartMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $subPath    = strtolower($segments[1] ?? '');

    // Require authenticated user (uses top-level session, same as admin)
    $cartUserId = (int)($_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? 0));
    if (!$cartUserId) {
        ResponseFormatter::error('Login required', 401);
        exit;
    }
    if (!$pdo instanceof PDO) {
        ResponseFormatter::error('Database unavailable', 503);
        exit;
    }

    $cartTenantId = $tenantId ?? 1;

    // Helper: get or create the user's active cart
    $getOrCreateCart = function () use ($pdo, $cartUserId, $cartTenantId): int {
        $st = $pdo->prepare("SELECT id FROM carts WHERE user_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1");
        $st->execute([$cartUserId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) return (int)$row['id'];
        $ins = $pdo->prepare(
            "INSERT INTO carts (entity_id, user_id, session_id, status, ip_address)
             VALUES (1, ?, ?, 'active', ?)"
        );
        $ins->execute([$cartUserId, session_id() ?: null, $_SERVER['REMOTE_ADDR'] ?? null]);
        return (int)$pdo->lastInsertId();
    };

    // Helper: refresh cart totals after any item change
    $refreshCartTotals = function (int $cid) use ($pdo): void {
        $st = $pdo->prepare("SELECT SUM(quantity) AS ti, SUM(total) AS tot FROM cart_items WHERE cart_id = ?");
        $st->execute([$cid]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        $pdo->prepare("UPDATE carts SET total_items = ?, subtotal = ?, total_amount = ?, last_activity_at = NOW() WHERE id = ?")
            ->execute([(int)($row['ti'] ?? 0), (float)($row['tot'] ?? 0), (float)($row['tot'] ?? 0), $cid]);
    };

    // ── GET /api/public/cart ─────────────────────────────
    if ($cartMethod === 'GET' && $subPath === '') {
        $cartRow = $pdoOne(
            "SELECT id, total_items, subtotal, total_amount, currency_code, status
               FROM carts WHERE user_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1",
            [$cartUserId]
        );
        if (!$cartRow) {
            ResponseFormatter::success(['cart' => null, 'items' => []]);
            exit;
        }
        $cartItems = $pdoList(
            "SELECT id, product_id, entity_id, product_name, sku, quantity,
                    unit_price, sale_price, subtotal, total, currency_code
               FROM cart_items WHERE cart_id = ? ORDER BY added_at ASC",
            [(int)$cartRow['id']]
        );
        ResponseFormatter::success(['cart' => $cartRow, 'items' => $cartItems]);
        exit;
    }

    // ── POST /api/public/cart/add ────────────────────────
    if ($subPath === 'add' && $cartMethod === 'POST') {
        $raw  = file_get_contents('php://input');
        $body = ($raw && str_starts_with(trim((string)($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json'))
              ? (json_decode($raw, true) ?? []) : $_POST;

        $pId   = (int)($body['product_id'] ?? 0);
        $pName = trim((string)($body['product_name'] ?? ''));
        $pSku  = trim((string)($body['sku'] ?? ''));
        $price = (float)($body['unit_price'] ?? $body['price'] ?? 0);
        $qty   = max(1, (int)($body['qty'] ?? 1));
        $eid   = max(1, (int)($body['entity_id'] ?? 1));

        if (!$pId || !$pName) {
            ResponseFormatter::error('product_id and product_name are required', 422);
            exit;
        }
        try {
            $cid      = $getOrCreateCart();
            $existing = $pdoOne(
                "SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ? LIMIT 1",
                [$cid, $pId]
            );
            if ($existing) {
                $newQty = (int)$existing['quantity'] + $qty;
                $sub    = round($price * $newQty, 2);
                $pdo->prepare("UPDATE cart_items SET quantity = ?, subtotal = ?, total = ?, updated_at = NOW() WHERE id = ?")
                    ->execute([$newQty, $sub, $sub, (int)$existing['id']]);
            } else {
                $sub = round($price * $qty, 2);
                $pdo->prepare(
                    "INSERT INTO cart_items
                       (cart_id, product_id, entity_id, product_name, sku, quantity,
                        unit_price, subtotal, total, currency_code)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'SAR')"
                )->execute([$cid, $pId, $eid, $pName, $pSku, $qty, $price, $sub, $sub]);
            }
            $refreshCartTotals($cid);
            ResponseFormatter::success(['ok' => true, 'cart_id' => $cid], 'Item added', 201);
        } catch (Throwable $ex) {
            ResponseFormatter::error('Failed to add item: ' . $ex->getMessage(), 500);
        }
        exit;
    }

    // ── POST /api/public/cart/update ─────────────────────
    if ($subPath === 'update' && $cartMethod === 'POST') {
        $raw  = file_get_contents('php://input');
        $body = ($raw && str_starts_with(trim((string)($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json'))
              ? (json_decode($raw, true) ?? []) : $_POST;
        $itemId = (int)($body['item_id'] ?? 0);
        $qty    = max(1, (int)($body['qty'] ?? 1));
        if (!$itemId) { ResponseFormatter::error('item_id required', 422); exit; }
        try {
            $item = $pdoOne(
                "SELECT ci.id, ci.unit_price, ci.cart_id FROM cart_items ci
                   INNER JOIN carts c ON c.id = ci.cart_id
                   WHERE ci.id = ? AND c.user_id = ? LIMIT 1",
                [$itemId, $cartUserId]
            );
            if (!$item) { ResponseFormatter::notFound('Item not found'); exit; }
            $sub = round((float)$item['unit_price'] * $qty, 2);
            $pdo->prepare("UPDATE cart_items SET quantity = ?, subtotal = ?, total = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$qty, $sub, $sub, $itemId]);
            $refreshCartTotals((int)$item['cart_id']);
            ResponseFormatter::success(['ok' => true]);
        } catch (Throwable $ex) {
            ResponseFormatter::error('Failed to update item', 500);
        }
        exit;
    }

    // ── POST/DELETE /api/public/cart/remove ──────────────
    if ($subPath === 'remove' && in_array($cartMethod, ['POST', 'DELETE'], true)) {
        $raw  = file_get_contents('php://input');
        $body = ($raw && str_starts_with(trim((string)($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json'))
              ? (json_decode($raw, true) ?? []) : $_POST;
        $itemId = (int)($body['item_id'] ?? $_GET['item_id'] ?? 0);
        if (!$itemId) { ResponseFormatter::error('item_id required', 422); exit; }
        try {
            $item = $pdoOne(
                "SELECT ci.id, ci.cart_id FROM cart_items ci
                   INNER JOIN carts c ON c.id = ci.cart_id
                   WHERE ci.id = ? AND c.user_id = ? LIMIT 1",
                [$itemId, $cartUserId]
            );
            if (!$item) { ResponseFormatter::notFound('Item not found'); exit; }
            $pdo->prepare("DELETE FROM cart_items WHERE id = ?")->execute([$itemId]);
            $refreshCartTotals((int)$item['cart_id']);
            ResponseFormatter::success(['ok' => true]);
        } catch (Throwable $ex) {
            ResponseFormatter::error('Failed to remove item', 500);
        }
        exit;
    }

    // ── POST /api/public/cart/clear ──────────────────────
    if ($subPath === 'clear' && $cartMethod === 'POST') {
        try {
            $cRow = $pdoOne(
                "SELECT id FROM carts WHERE user_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1",
                [$cartUserId]
            );
            if ($cRow) {
                $pdo->prepare("DELETE FROM cart_items WHERE cart_id = ?")->execute([(int)$cRow['id']]);
                $pdo->prepare("UPDATE carts SET total_items = 0, subtotal = 0, total_amount = 0, last_activity_at = NOW() WHERE id = ?")
                    ->execute([(int)$cRow['id']]);
            }
            ResponseFormatter::success(['ok' => true]);
        } catch (Throwable $ex) {
            ResponseFormatter::error('Failed to clear cart', 500);
        }
        exit;
    }

    ResponseFormatter::error('Unknown cart action', 404);
    exit;
}

/* -------------------------------------------------------
 * POST /api/public/orders — order creation
 * Accepts: entity_id, payment_method, items (JSON), customer_name, customer_phone,
 *          delivery_address, notes, tenant_id
 * ----------------------------------------------------- */
if ($first === 'orders') {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method !== 'POST') {
        ResponseFormatter::error('Method not allowed', 405);
        exit;
    }
    if (!$pdo instanceof PDO) {
        ResponseFormatter::error('Database unavailable', 503);
        exit;
    }

    // Parse input (supports both JSON body and form POST)
    $raw  = file_get_contents('php://input');
    $body = ($raw && str_starts_with(trim((string)($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json'))
          ? (json_decode($raw, true) ?? [])
          : $_POST;

    $entityId   = isset($body['entity_id'])  && is_numeric($body['entity_id'])  ? (int)$body['entity_id']  : 0;
    $pmCode     = trim((string)($body['payment_method'] ?? ''));
    $custName   = trim((string)($body['customer_name']  ?? ''));
    $custPhone  = trim((string)($body['customer_phone'] ?? ''));
    $address    = trim((string)($body['delivery_address'] ?? ''));
    $notes      = trim((string)($body['notes'] ?? ''));

    // Cart items — JSON array from body or hidden form field
    $rawItems = $body['items'] ?? $body['cart_items_json'] ?? '[]';
    if (is_string($rawItems)) $rawItems = json_decode($rawItems, true);
    $items = is_array($rawItems) ? $rawItems : [];

    // Require authenticated user (check both session formats)
    $sessUserId = (int)($_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? 0));
    if (!$sessUserId) {
        ResponseFormatter::error('Login required to place an order', 401);
        exit;
    }

    // If entity_id not provided, resolve to first active entity for the tenant
    if (!$entityId && $tenantId) {
        $eRow = $pdoOne(
            "SELECT id FROM entities WHERE tenant_id = ? AND status = 'approved' ORDER BY id ASC LIMIT 1",
            [$tenantId]
        );
        $entityId = $eRow ? (int)$eRow['id'] : 1;
    } elseif (!$entityId) {
        $entityId = 1; // global fallback
    }

    // Validate
    if (!$custName || !$custPhone || empty($items)) {
        ResponseFormatter::error('customer_name, customer_phone, and items are required', 422);
        exit;
    }
    if (!$tenantId) {
        ResponseFormatter::error('tenant_id is required', 422);
        exit;
    }

    // Calculate totals
    $subtotal = 0.0;
    foreach ($items as $it) {
        $price = (float)($it['price'] ?? 0);
        $qty   = max(1, (int)($it['qty'] ?? 1));
        $subtotal += $price * $qty;
    }
    $subtotal     = round($subtotal, 2);
    $taxAmount    = 0.0;
    $grandTotal   = $subtotal;
    $currencyCode = trim((string)($body['currency_code'] ?? 'SAR'));

    // Generate order number: ORD-{tenantId}-{timestamp}-{rand}
    $orderNumber = 'ORD-' . $tenantId . '-' . time() . '-' . rand(100, 999);

    // Verify order number uniqueness
    $checkSt = $pdo->prepare('SELECT id FROM orders WHERE order_number = ? LIMIT 1');
    $checkSt->execute([$orderNumber]);
    if ($checkSt->fetch()) {
        $orderNumber .= '-' . rand(10, 99); // collision resolution
    }

    try {
        $pdo->beginTransaction();

        // Insert order
        $oSt = $pdo->prepare(
            "INSERT INTO orders
               (tenant_id, entity_id, order_number, user_id, status, payment_status,
                subtotal, tax_amount, shipping_cost, discount_amount, total_amount, grand_total,
                currency_code, customer_notes, ip_address)
             VALUES (?, ?, ?, ?, 'pending', 'pending',
                     ?, 0, 0, 0, ?, ?,
                     ?, ?, ?)"
        );
        $oSt->execute([
            $tenantId, $entityId, $orderNumber, $sessUserId,
            $subtotal, $grandTotal, $grandTotal,
            $currencyCode, $notes,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
        $orderId = (int)$pdo->lastInsertId();

        // Insert order items
        $iSt = $pdo->prepare(
            "INSERT INTO order_items
               (tenant_id, order_id, entity_id, product_id, product_name, sku,
                quantity, unit_price, subtotal, total)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        foreach ($items as $it) {
            $pId    = (int)($it['id'] ?? 0);
            $pName  = (string)($it['name'] ?? '');
            $pSku   = (string)($it['sku']  ?? '');
            $qty    = max(1, (int)($it['qty'] ?? 1));
            $price  = (float)($it['price'] ?? 0);
            $itSub  = round($price * $qty, 2);
            if (!$pId || !$pName) continue;
            $iSt->execute([$tenantId, $orderId, $entityId, $pId, $pName, $pSku, $qty, $price, $itSub, $itSub]);
        }

        $pdo->commit();

        ResponseFormatter::success([
            'ok'           => true,
            'id'           => $orderId,
            'order_number' => $orderNumber,
        ], 'Order created', 201);
    } catch (Throwable $ex) {
        try { $pdo->rollBack(); } catch (Throwable $rb) {}
        ResponseFormatter::error('Order creation failed', 500);
    }
    exit;
}

/* -------------------------------------------------------
 * POST /api/public/job_applications — guest job application
 * Accepts: job_id, full_name, email, phone, cover_letter,
 *          cv_file_url, portfolio_url, linkedin_url
 * ----------------------------------------------------- */
if ($first === 'job_applications') {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        ResponseFormatter::error('Method not allowed', 405);
        exit;
    }
    if (!$pdo instanceof PDO) { ResponseFormatter::error('Database unavailable', 503); exit; }

    $raw  = file_get_contents('php://input');
    $body = ($raw && str_starts_with(trim((string)($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json'))
          ? (json_decode($raw, true) ?? []) : $_POST;

    $jobId        = isset($body['job_id'])  && is_numeric($body['job_id'])  ? (int)$body['job_id']  : 0;
    $fullName     = trim((string)($body['full_name']     ?? $body['name']    ?? ''));
    $email        = trim((string)($body['email']         ?? ''));
    $phone        = trim((string)($body['phone']         ?? ''));
    $coverLetter  = trim((string)($body['cover_letter']  ?? ''));
    $cvFileUrl    = trim((string)($body['cv_file_url']   ?? ''));
    $portfolioUrl = trim((string)($body['portfolio_url'] ?? ''));
    $linkedinUrl  = trim((string)($body['linkedin_url']  ?? ''));

    // Require authenticated user (check both session formats)
    $jobSessUserId = (int)($_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? 0));
    if (!$jobSessUserId) {
        ResponseFormatter::error('Login required to apply for a job', 401);
        exit;
    }

    if (!$jobId || !$fullName || !$email) {
        ResponseFormatter::error('job_id, full_name and email are required', 422);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        ResponseFormatter::error('Invalid email address', 422);
        exit;
    }

    // Verify job exists and is open
    $jobRow = $pdoOne(
        "SELECT id FROM jobs WHERE id = ? AND status NOT IN ('cancelled','filled','closed') LIMIT 1",
        [$jobId]
    );
    if (!$jobRow) { ResponseFormatter::notFound('Job not found or no longer accepting applications'); exit; }

    try {
        $st = $pdo->prepare(
            "INSERT INTO job_applications
               (job_id, user_id, full_name, email, phone,
                cover_letter, portfolio_url, linkedin_url, cv_file_url,
                status, ip_address)
             VALUES (?, ?, ?, ?, ?,
                     ?, ?, ?, ?,
                     'submitted', ?)"
        );
        $st->execute([
            $jobId, $jobSessUserId,
            $fullName, $email, $phone,
            $coverLetter, $portfolioUrl, $linkedinUrl, $cvFileUrl,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
        $appId = (int)$pdo->lastInsertId();
        ResponseFormatter::success(['ok' => true, 'id' => $appId], 'Application submitted', 201);
    } catch (Throwable $ex) {
        ResponseFormatter::error('Application submission failed', 500);
    }
    exit;
}

/* -------------------------------------------------------
 * Route: User Addresses (requires login)
 * GET  /api/public/addresses           — list user's addresses
 * POST /api/public/addresses           — add new address
 * DELETE /api/public/addresses/{id}    — delete address
 * ----------------------------------------------------- */
if ($first === 'addresses') {
    $addrSessUser   = $_SESSION['user'] ?? null;
    $addrSessUserId = isset($addrSessUser['id']) ? (int)$addrSessUser['id'] : 0;
    // Also accept user_id from session (scalar form)
    if (!$addrSessUserId && !empty($_SESSION['user_id'])) {
        $addrSessUserId = (int)$_SESSION['user_id'];
    }
    if (!$addrSessUserId) {
        ResponseFormatter::error('Login required', 401); exit;
    }

    $addrId = isset($segments[1]) && ctype_digit((string)$segments[1]) ? (int)$segments[1] : 0;

    if ($_SERVER['REQUEST_METHOD'] === 'DELETE' || ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_method'] ?? '') === 'DELETE')) {
        if (!$addrId) { ResponseFormatter::error('Address ID required', 422); exit; }
        $addrRow = $pdoOne('SELECT id FROM addresses WHERE id = ? AND owner_id = ? AND owner_type = "user" LIMIT 1', [$addrId, $addrSessUserId]);
        if (!$addrRow) { ResponseFormatter::notFound('Address not found'); exit; }
        try {
            $pdo->prepare('DELETE FROM addresses WHERE id = ?')->execute([$addrId]);
            ResponseFormatter::success(['ok' => true]);
        } catch (Throwable $_) { ResponseFormatter::error('Delete failed', 500); }
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $addrLine1  = trim($_POST['address_line1'] ?? '');
        $addrLine2  = trim($_POST['address_line2'] ?? '');
        $cityId     = (int)($_POST['city_id']     ?? 0);
        $countryId  = (int)($_POST['country_id']  ?? 0);
        $postalCode = trim($_POST['postal_code']  ?? '');
        $isPrimary  = !empty($_POST['is_primary']) ? 1 : 0;
        if (!$addrLine1) { ResponseFormatter::error('address_line1 is required', 422); exit; }

        try {
            if ($isPrimary) {
                // Clear existing primary for this user
                $pdo->prepare('UPDATE addresses SET is_primary = 0 WHERE owner_id = ? AND owner_type = "user"')->execute([$addrSessUserId]);
            }
            $st = $pdo->prepare(
                'INSERT INTO addresses (owner_type, owner_id, address_line1, address_line2, city_id, country_id, postal_code, is_primary)
                 VALUES ("user", ?, ?, ?, ?, ?, ?, ?)'
            );
            $st->execute([$addrSessUserId, $addrLine1, $addrLine2 ?: null, $cityId ?: null, $countryId ?: null, $postalCode ?: null, $isPrimary]);
            ResponseFormatter::success(['ok' => true, 'id' => (int)$pdo->lastInsertId()], 'Address added', 201);
        } catch (Throwable $_) { ResponseFormatter::error('Failed to save address', 500); }
        exit;
    }

    // GET — list addresses for logged-in user
    $addrRows = $pdoList(
        "SELECT a.id, a.address_line1, a.address_line2, a.postal_code, a.is_primary,
                c.name AS city_name, co.name AS country_name
           FROM addresses a
      LEFT JOIN cities c ON c.id = a.city_id
      LEFT JOIN countries co ON co.id = a.country_id
          WHERE a.owner_id = ? AND a.owner_type = 'user'
          ORDER BY a.is_primary DESC, a.id DESC",
        [$addrSessUserId]
    );
    ResponseFormatter::success($addrRows);
    exit;
}


ResponseFormatter::notFound('Public route not found: /' . ($first ?: ''));