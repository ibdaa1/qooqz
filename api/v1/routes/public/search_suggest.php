<?php
declare(strict_types=1);
/**
 * Public API sub-route: search_suggest
 * GET /api/public/search_suggest?q=...&context=all&lang=ar&tenant_id=1
 *   OR ?popular=1&lang=ar&tenant_id=1  → returns popular/trending queries
 *
 * Returns grouped live-search suggestions:
 * {
 *   "products":   [{id, name, url, icon}, …],
 *   "categories": […],
 *   "entities":   […],
 *   "jobs":       […]
 * }
 *
 * Loaded by api/v1/routes/public.php dispatcher.
 * Variables available: $pdo, $pdoList, $lang, $tenantId
 */

/* -------------------------------------------------------
 * Helper: upsert a query into search_logs (best-effort)
 * ----------------------------------------------------- */
$trackQuery = function (string $query) use ($pdo, $lang, $tenantId): void {
    if (!$pdo instanceof PDO || strlen($query) < 2) return;
    try {
        $st = $pdo->prepare("
            INSERT INTO search_logs (query, tenant_id, lang, count, last_searched_at)
            VALUES (?, ?, ?, 1, NOW())
            ON DUPLICATE KEY UPDATE count = count + 1, last_searched_at = NOW()
        ");
        $st->execute([$query, $tenantId ?: null, $lang]);
    } catch (Throwable $e) {
        // Table may not exist yet — ignore
    }
};

/* -------------------------------------------------------
 * Popular searches: ?popular=1 — return top queries
 * ----------------------------------------------------- */
if (!empty($_GET['popular'])) {
    $popular = [];
    try {
        if ($pdo instanceof PDO) {
            $tenantCond  = $tenantId ? ' AND (tenant_id = ? OR tenant_id IS NULL)' : '';
            $tenantParam = $tenantId ? [$lang, $tenantId] : [$lang];
            $st = $pdo->prepare("
                SELECT query, SUM(count) AS total
                FROM search_logs
                WHERE lang = ? $tenantCond
                GROUP BY query
                ORDER BY total DESC
                LIMIT 8
            ");
            $st->execute($tenantParam);
            $popular = $st->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e) {
        // search_logs may not exist yet
    }
    ResponseFormatter::success(['popular' => array_map(fn($row) => (string)$row['query'], $popular)]);
    exit;
}

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    ResponseFormatter::success([
        'products'   => [],
        'categories' => [],
        'entities'   => [],
        'jobs'       => [],
    ]);
    exit;
}

// Track this query (best-effort, fires on each suggest call)
$trackQuery($q);

// context = all | products | categories | entities | jobs
$context = strtolower(trim($_GET['context'] ?? 'all'));

// Boost limit for the active context type (show more of it)
$contextLimits = [
    'products'   => 5,
    'categories' => 5,
    'entities'   => 5,
    'jobs'       => 5,
];
if (isset($contextLimits[$context])) {
    $contextLimits[$context] = 8;
}

// Escape LIKE wildcards
$safe  = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
$like  = '%' . $safe . '%';

/* -------------------------------------------------------
 * Helper: try FULLTEXT MATCH AGAINST, fall back to LIKE
 * ----------------------------------------------------- */
$ftSearch = function (string $sql, array $params, string $likeSql, array $likeParams) use ($pdo): array {
    if (!$pdo instanceof PDO) return [];
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        if ($rows) return $rows;
    } catch (Throwable $e) {
        // Fulltext index may not exist — fall through to LIKE
    }
    try {
        $st = $pdo->prepare($likeSql);
        $st->execute($likeParams);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
};

$results = [
    'products'   => [],
    'categories' => [],
    'entities'   => [],
    'jobs'       => [],
];

/* ═══════════════════════════════════════════════════════
 *  PRODUCTS
 * ═══════════════════════════════════════════════════════ */
$limit = $contextLimits['products'];

$tenantCond  = $tenantId ? ' AND p.tenant_id = ?' : '';
$tenantParam = $tenantId ? [$tenantId] : [];

// FULLTEXT on product_translations.name
$ftProductSql = "
    SELECT DISTINCT p.id,
        COALESCE(pt.name, p.slug) AS name,
        p.slug,
        MATCH(pt.name) AGAINST(? IN BOOLEAN MODE) AS score
    FROM products p
    LEFT JOIN product_translations pt
        ON pt.product_id = p.id AND pt.language_code = ?
    WHERE p.is_active = 1
      AND MATCH(pt.name) AGAINST(? IN BOOLEAN MODE)
      $tenantCond
    ORDER BY score DESC
    LIMIT $limit";

$likeProductSql = "
    SELECT DISTINCT p.id,
        COALESCE(pt.name, p.slug) AS name,
        p.slug
    FROM products p
    LEFT JOIN product_translations pt
        ON pt.product_id = p.id AND pt.language_code = ?
    WHERE p.is_active = 1
      AND (pt.name LIKE ? OR p.sku LIKE ? OR p.slug LIKE ?)
      $tenantCond
    ORDER BY p.id DESC
    LIMIT $limit";

// Build boolean-mode query: at least first term required, rest optional for lenient matching
$terms = array_values(array_filter(explode(' ', $safe)));
if (count($terms) > 1) {
    $required = '+' . $terms[0] . '*';
    $optional = implode('* ', array_slice($terms, 1)) . '*';
    $boolQ = $required . ' ' . $optional;
} else {
    $boolQ = $safe . '*';
}
$rows = $ftSearch(
    $ftProductSql,
    array_merge([$boolQ, $lang, $boolQ], $tenantParam),
    $likeProductSql,
    array_merge([$lang, $like, $like, $like], $tenantParam)
);

foreach ($rows as $r) {
    $results['products'][] = [
        'id'   => (int)$r['id'],
        'name' => (string)($r['name'] ?? ''),
        'url'  => '/frontend/public/product.php?id=' . $r['id'],
        'icon' => '🛍',
        'type' => 'product',
    ];
}

/* ═══════════════════════════════════════════════════════
 *  CATEGORIES
 * ═══════════════════════════════════════════════════════ */
$limit = $contextLimits['categories'];

$catTenantCond  = $tenantId ? ' AND c.tenant_id = ?' : '';
$catTenantParam = $tenantId ? [$tenantId] : [];

$ftCatSql = "
    SELECT DISTINCT c.id,
        COALESCE(ct.name, c.slug) AS name,
        c.slug,
        MATCH(ct.name) AGAINST(? IN BOOLEAN MODE) AS score
    FROM categories c
    LEFT JOIN category_translations ct
        ON ct.category_id = c.id AND ct.language_code = ?
    WHERE c.is_active = 1
      AND MATCH(ct.name) AGAINST(? IN BOOLEAN MODE)
      $catTenantCond
    ORDER BY score DESC
    LIMIT $limit";

$likeCatSql = "
    SELECT DISTINCT c.id,
        COALESCE(ct.name, c.slug) AS name,
        c.slug
    FROM categories c
    LEFT JOIN category_translations ct
        ON ct.category_id = c.id AND ct.language_code = ?
    WHERE c.is_active = 1
      AND (ct.name LIKE ? OR c.slug LIKE ?)
      $catTenantCond
    ORDER BY c.id DESC
    LIMIT $limit";

$rows = $ftSearch(
    $ftCatSql,
    array_merge([$boolQ, $lang, $boolQ], $catTenantParam),
    $likeCatSql,
    array_merge([$lang, $like, $like], $catTenantParam)
);

foreach ($rows as $r) {
    $results['categories'][] = [
        'id'   => (int)$r['id'],
        'name' => (string)($r['name'] ?? ''),
        'url'  => '/frontend/public/categories.php?category_id=' . $r['id'],
        'icon' => '📂',
        'type' => 'category',
    ];
}

/* ═══════════════════════════════════════════════════════
 *  ENTITIES (stores / vendors)
 * ═══════════════════════════════════════════════════════ */
$limit = $contextLimits['entities'];

$entTenantCond  = $tenantId ? ' AND e.tenant_id = ?' : '';
$entTenantParam = $tenantId ? [$tenantId] : [];

$ftEntSql = "
    SELECT DISTINCT e.id,
        COALESCE(et.store_name, e.store_name) AS name,
        e.slug,
        MATCH(et.store_name) AGAINST(? IN BOOLEAN MODE) AS score
    FROM entities e
    LEFT JOIN entity_translations et
        ON et.entity_id = e.id AND et.language_code = ?
    WHERE e.status NOT IN ('suspended','rejected')
      AND MATCH(et.store_name) AGAINST(? IN BOOLEAN MODE)
      $entTenantCond
    ORDER BY score DESC
    LIMIT $limit";

$likeEntSql = "
    SELECT DISTINCT e.id,
        COALESCE(et.store_name, e.store_name) AS name,
        e.slug
    FROM entities e
    LEFT JOIN entity_translations et
        ON et.entity_id = e.id AND et.language_code = ?
    WHERE e.status NOT IN ('suspended','rejected')
      AND (et.store_name LIKE ? OR e.store_name LIKE ? OR e.slug LIKE ?)
      $entTenantCond
    ORDER BY e.id DESC
    LIMIT $limit";

$rows = $ftSearch(
    $ftEntSql,
    array_merge([$boolQ, $lang, $boolQ], $entTenantParam),
    $likeEntSql,
    array_merge([$lang, $like, $like, $like], $entTenantParam)
);

foreach ($rows as $r) {
    $results['entities'][] = [
        'id'   => (int)$r['id'],
        'name' => (string)($r['name'] ?? ''),
        'url'  => '/frontend/public/entity.php?id=' . $r['id'],
        'icon' => '🏢',
        'type' => 'entity',
    ];
}

/* ═══════════════════════════════════════════════════════
 *  JOBS
 * ═══════════════════════════════════════════════════════ */
$limit = $contextLimits['jobs'];

$ftJobSql = "
    SELECT DISTINCT j.id,
        COALESCE(jt.job_title, j.slug) AS name,
        j.slug,
        MATCH(jt.job_title) AGAINST(? IN BOOLEAN MODE) AS score
    FROM jobs j
    LEFT JOIN job_translations jt
        ON jt.job_id = j.id AND jt.language_code = ?
    WHERE j.status NOT IN ('cancelled','filled','closed')
      AND MATCH(jt.job_title) AGAINST(? IN BOOLEAN MODE)
    ORDER BY score DESC
    LIMIT $limit";

$likeJobSql = "
    SELECT DISTINCT j.id,
        COALESCE(jt.job_title, j.slug) AS name,
        j.slug
    FROM jobs j
    LEFT JOIN job_translations jt
        ON jt.job_id = j.id AND jt.language_code = ?
    WHERE j.status NOT IN ('cancelled','filled','closed')
      AND (jt.job_title LIKE ? OR j.slug LIKE ?)
    ORDER BY j.id DESC
    LIMIT $limit";

$rows = $ftSearch(
    $ftJobSql,
    [$boolQ, $lang, $boolQ],
    $likeJobSql,
    [$lang, $like, $like]
);

foreach ($rows as $r) {
    $results['jobs'][] = [
        'id'   => (int)$r['id'],
        'name' => (string)($r['name'] ?? ''),
        'url'  => '/frontend/public/jobs.php?job_id=' . $r['id'],
        'icon' => '💼',
        'type' => 'job',
    ];
}

/* ═══════════════════════════════════════════════════════
 *  Also build flat "suggestions" array for backward compat
 * ═══════════════════════════════════════════════════════ */
$allSuggestions = [];
foreach ($results as $type => $items) {
    foreach ($items as $item) {
        $allSuggestions[] = $item;
    }
}

ResponseFormatter::success(array_merge($results, ['suggestions' => $allSuggestions]));
exit;
