<?php
declare(strict_types=1);
/**
 * Public API sub-route: categories
 * Loaded by api/v1/routes/public.php dispatcher.
 * Variables available: $pdo, $pdoList, $pdoOne, $pdoCount,
 *   $first, $segments, $lang, $page, $per, $offset, $tenantId
 */

if ($first === 'categories') {
    $id = $_GET['id'] ?? (isset($segments[1]) && ctype_digit((string)$segments[1]) ? (int)$segments[1] : null);

    if ($id) {
        $row = $pdoOne(
            "SELECT c.id, COALESCE(ct.name, c.name, c.slug) AS name, c.slug, c.description,
                    (SELECT i.url FROM images i WHERE i.owner_id = c.id ORDER BY (i.image_type_id = 1) DESC, i.id ASC LIMIT 1) AS image_url,
                    c.is_featured, c.is_active, c.parent_id, c.sort_order, c.tenant_id
               FROM categories c
          LEFT JOIN category_translations ct ON ct.category_id = c.id AND ct.language_code = ?
              WHERE c.id = ? AND c.is_active = 1 LIMIT 1",
            [$lang, (int)$id]
        );
        if ($row) ResponseFormatter::success(['ok' => true, 'category' => $row]);
        else      ResponseFormatter::notFound('Category not found');
        exit;
    }

    /* ── Tree mode: return ALL categories as a nested hierarchy ──────────── */
    if (!empty($_GET['tree'])) {
        $treeWhere  = 'WHERE c.is_active = 1';
        $treeParams = [];
        if ($tenantId) { $treeWhere .= ' AND c.tenant_id = ?'; $treeParams[] = $tenantId; }

        $allCats = $pdoList(
            "SELECT c.id, COALESCE(ct.name, c.name, c.slug) AS name, c.slug,
                    (SELECT i.url FROM images i WHERE i.owner_id = c.id ORDER BY (i.image_type_id = 1) DESC, i.id ASC LIMIT 1) AS image_url,
                    c.is_featured, c.parent_id, c.sort_order, c.tenant_id
               FROM categories c
          LEFT JOIN category_translations ct ON ct.category_id = c.id AND ct.language_code = ?
              $treeWhere ORDER BY c.parent_id ASC, c.sort_order ASC, c.id ASC",
            array_merge([$lang], $treeParams)
        );

        /* Build tree from flat list */
        $map = [];
        foreach ($allCats as $cat) {
            $cat['children'] = [];
            $map[(int)$cat['id']] = $cat;
        }
        $tree = [];
        foreach (array_keys($map) as $catId) {
            $pid = (int)($map[$catId]['parent_id'] ?? 0);
            if ($pid && isset($map[$pid])) {
                $map[$pid]['children'][] =& $map[$catId];
            } else {
                $tree[] =& $map[$catId];
            }
        }

        ResponseFormatter::success([
            'ok'   => true,
            'data' => $tree,
            'meta' => ['total' => count($allCats)],
        ]);
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
    if (!empty($_GET['search'])) {
        $kw = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($_GET['search'])) . '%';
        $where .= ' AND (c.slug LIKE ? OR c.name LIKE ? OR EXISTS (SELECT 1 FROM category_translations ct2 WHERE ct2.category_id = c.id AND ct2.name LIKE ?))';
        $whereParams[] = $kw;
        $whereParams[] = $kw;
        $whereParams[] = $kw;
    }

    $total = $pdoCount("SELECT COUNT(*) FROM categories c $where", $whereParams);
    $rows  = $pdoList(
        "SELECT c.id, COALESCE(ct.name, c.name, c.slug) AS name, c.slug,
                (SELECT i.url FROM images i WHERE i.owner_id = c.id ORDER BY (i.image_type_id = 1) DESC, i.id ASC LIMIT 1) AS image_url,
                c.is_featured, c.is_active, c.parent_id, c.sort_order, c.tenant_id,
                (SELECT COUNT(*) FROM products p
                  INNER JOIN product_categories pc ON pc.product_id = p.id AND pc.category_id = c.id
                  WHERE p.is_active = 1) AS product_count
           FROM categories c
      LEFT JOIN category_translations ct ON ct.category_id = c.id AND ct.language_code = ?
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
