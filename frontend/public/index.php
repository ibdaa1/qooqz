<?php
declare(strict_types=1);
/**
 * frontend/public/index.php
 * QOOQZ — Global Public Homepage [Production]
 * يعرض كل الأقسام مع fallback آمن
 */

require_once dirname(__DIR__) . '/includes/public_context.php';

$ctx      = $GLOBALS['PUB_CONTEXT'];
$lang     = $ctx['lang'];
$dir      = $ctx['dir'];
$tenantId = (int)$ctx['tenant_id'];
$apiBase  = pub_api_url('');

$GLOBALS['PUB_APP_NAME']  = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH'] = '/frontend/public';
$GLOBALS['PUB_PAGE_TITLE']= t('hero.title') . ' — QOOQZ';
$GLOBALS['PUB_PAGE_DESC'] = t('hero.subtitle');

/**
 * CSS Sanitiser
 */
function _pub_safe_color(string $v): string {
    $v = trim($v);
    if ($v === '') return '';
    if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $v)) return $v;
    if (preg_match('/^(rgb|hsl)a?\(\s*[\d\s%,.]+\)$/i', $v)) return $v;
    if (preg_match('/^[a-zA-Z- ]{2,30}$/', $v)) return $v;
    if (preg_match('/^var\(--[a-z0-9_-]+\)$/i', $v)) return $v;
    return '';
}

/**
 * Component Registry
 */
const PUB_COMPONENT_MAP = [
    'search'     => 'ad_search',
    'categories' => 'ad_categories',
    'products'   => 'ad_products',
    'deals'      => 'ad_deals',
    'brands'     => 'ad_brands',
    'entities'   => 'ad_entities',
    'tenants'    => 'ad_tenants',
    'auctions'   => 'ad_auctions',
    'jobs'       => 'ad_jobs',
    'slider'     => 'ad_slider',
    'ads'        => 'ad_ads',
    'native'     => 'ad_native',
    'stats'      => 'ad_stats',
    'html'       => 'ad_html',
    'custom'     => 'ad_custom',
];

function pub_resolve_component(array $section): ?string {
    $stored = trim($section['component'] ?? '');
    if ($stored !== '') return $stored;
    $type = strtolower(trim($section['section_type'] ?? ''));
    return PUB_COMPONENT_MAP[$type] ?? null;
}

function getSectionData(string $dataSource, string $sectionType, string $apiBase, string $lang, int $tenantId): array {
    // Resolve type: data_source first, fall back to section_type
    $ds = trim($dataSource);
    if ($ds === '') $ds = trim($sectionType);
    if ($ds === '') return [];

    [$type, $filter] = array_pad(explode(':', $ds, 2), 2, '');
    $type   = strtolower(trim($type));
    $filter = strtolower(trim($filter));
    $limit  = 12;

    // PDO-first: direct DB queries are faster and more reliable on shared hosting
    // than loopback HTTP calls which can time out or deadlock.
    $pdo = pub_get_pdo();
    if ($pdo) {
        try {
            switch ($type) {
                case 'brands': {
                    $extra  = $filter === 'featured' ? ' AND b.is_featured = 1' : '';
                    $st = $pdo->prepare(
                        "SELECT b.id, b.slug, b.website_url, b.is_featured,
                                COALESCE(bt.name, b.slug) AS name,
                                COALESCE(bt.description, '') AS description,
                                (SELECT i.url FROM images i
                                  WHERE i.owner_id = b.id ORDER BY i.id ASC LIMIT 1) AS logo_url
                           FROM brands b
                      LEFT JOIN brand_translations bt
                             ON bt.brand_id = b.id AND bt.language_code = ?
                          WHERE b.tenant_id = ? AND b.is_active = 1{$extra}
                          ORDER BY b.is_featured DESC, b.sort_order ASC, b.id ASC
                          LIMIT {$limit}"
                    );
                    $st->execute([$lang, $tenantId]);
                    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
                }

                case 'entities': {
                    $extra  = $filter === 'verified' ? ' AND e.is_verified = 1' : '';
                    $st = $pdo->prepare(
                        "SELECT e.id,
                                COALESCE(NULLIF(TRIM(et.store_name), ''), e.store_name) AS store_name,
                                e.slug, e.vendor_type, e.is_verified,
                                (SELECT i.url FROM images i
                                  WHERE i.owner_id = e.id ORDER BY i.id ASC LIMIT 1) AS logo_url
                           FROM entities e
                      LEFT JOIN entity_translations et
                             ON et.entity_id = e.id AND et.language_code = ?
                          WHERE e.tenant_id = ? AND e.status NOT IN ('suspended','rejected'){$extra}
                          ORDER BY e.is_verified DESC, e.joined_at DESC
                          LIMIT {$limit}"
                    );
                    $st->execute([$lang, $tenantId]);
                    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    // If the verified filter returned no results (no verified entities yet),
                    // fall back to showing all active entities so the section is never empty
                    // when entities exist in the DB but none have been verified yet.
                    if (empty($rows) && $filter === 'verified') {
                        $st2 = $pdo->prepare(
                            "SELECT e.id,
                                    COALESCE(NULLIF(TRIM(et.store_name), ''), e.store_name) AS store_name,
                                    e.slug, e.vendor_type, e.is_verified,
                                    (SELECT i.url FROM images i
                                      WHERE i.owner_id = e.id ORDER BY i.id ASC LIMIT 1) AS logo_url
                               FROM entities e
                          LEFT JOIN entity_translations et
                                 ON et.entity_id = e.id AND et.language_code = ?
                              WHERE e.tenant_id = ? AND e.status NOT IN ('suspended','rejected')
                              ORDER BY e.is_verified DESC, e.joined_at DESC
                              LIMIT {$limit}"
                        );
                        $st2->execute([$lang, $tenantId]);
                        $rows = $st2->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    }
                    return $rows;
                }

                case 'categories': {
                    $extra  = '';
                    $params = [$lang];
                    if ($tenantId) { $extra .= ' AND c.tenant_id = ?'; $params[] = $tenantId; }
                    if ($filter === 'featured') { $extra .= ' AND c.is_featured = 1'; }
                    $st = $pdo->prepare(
                        "SELECT c.id, COALESCE(ct.name, c.slug) AS name, c.slug, c.is_featured,
                                (SELECT i.url FROM images i
                                  WHERE i.owner_id = c.id
                                  ORDER BY (i.image_type_id = 1) DESC, i.id ASC LIMIT 1) AS image_url
                           FROM categories c
                      LEFT JOIN category_translations ct
                             ON ct.category_id = c.id AND ct.language_code = ?
                          WHERE c.is_active = 1{$extra}
                          ORDER BY c.sort_order ASC, c.id ASC
                          LIMIT {$limit}"
                    );
                    $st->execute($params);
                    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
                }

                case 'products': {
                    $extra  = '';
                    $params = [$lang];
                    if ($tenantId) { $extra .= ' AND p.tenant_id = ?'; $params[] = $tenantId; }
                    if ($filter === 'featured') { $extra .= ' AND p.is_featured = 1'; }
                    $st = $pdo->prepare(
                        "SELECT p.id, COALESCE(pt.name, p.slug) AS name, p.slug, p.is_featured,
                                p.stock_quantity, p.stock_status, p.rating_average, p.rating_count,
                                (SELECT pp.price FROM product_pricing pp
                                  WHERE pp.product_id = p.id ORDER BY pp.id ASC LIMIT 1) AS price,
                                (SELECT pp.currency_code FROM product_pricing pp
                                  WHERE pp.product_id = p.id ORDER BY pp.id ASC LIMIT 1) AS currency_code,
                                (SELECT i.url FROM images i
                                  WHERE i.owner_id = p.id
                                  ORDER BY (i.image_type_id = 2) DESC, i.id ASC LIMIT 1) AS image_url,
                                NULL AS image_thumb_url
                           FROM products p
                      LEFT JOIN product_translations pt
                             ON pt.product_id = p.id AND pt.language_code = ?
                          WHERE p.is_active = 1{$extra}
                          ORDER BY p.id DESC
                          LIMIT {$limit}"
                    );
                    $st->execute($params);
                    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
                }

                case 'deals': {
                    $st = $pdo->prepare(
                        "SELECT d.id, d.code, d.type, d.status,
                                COALESCE(dt.name, d.code) AS title,
                                dt.description
                           FROM discounts d
                      LEFT JOIN discount_translations dt
                             ON dt.discount_id = d.id AND dt.language_code = ?
                          WHERE d.entity_id IN (SELECT id FROM entities WHERE tenant_id = ?)
                            AND d.status NOT IN ('cancelled','deleted')
                          ORDER BY d.id DESC
                          LIMIT {$limit}"
                    );
                    $st->execute([$lang, $tenantId]);
                    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
                }

                case 'tenants': {
                    $st = $pdo->prepare(
                        "SELECT t.id, t.name, t.slug, t.status,
                                (SELECT i.url FROM images i
                                  WHERE i.tenant_id = t.id ORDER BY i.id ASC LIMIT 1) AS logo_url
                           FROM tenants t
                          WHERE t.status = 'active'
                          ORDER BY t.id ASC
                          LIMIT {$limit}"
                    );
                    $st->execute([]);
                    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
                }

                case 'auctions': {
                    $extra  = '';
                    $params = [$lang];
                    if ($tenantId) { $extra .= ' AND a.tenant_id = ?'; $params[] = $tenantId; }
                    $st = $pdo->prepare(
                        "SELECT a.id, a.slug, a.auction_type, a.status,
                                a.starting_price, a.current_price, a.buy_now_price,
                                a.start_date, a.end_date, a.is_featured,
                                (SELECT c.code FROM currencies c WHERE c.id = a.currency_id LIMIT 1) AS currency_code,
                                (SELECT i.url FROM images i
                                  WHERE i.owner_id = a.product_id ORDER BY i.id ASC LIMIT 1) AS image_url,
                                (SELECT at2.title FROM auction_translations at2
                                  WHERE at2.auction_id = a.id AND at2.language_code = ? LIMIT 1) AS title
                           FROM auctions a
                          WHERE a.status NOT IN ('cancelled','closed'){$extra}
                          ORDER BY a.is_featured DESC, a.end_date ASC
                          LIMIT 8"
                    );
                    $st->execute($params);
                    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
                }

                case 'jobs': {
                    $st = $pdo->prepare(
                        "SELECT j.id, COALESCE(jt.job_title, j.slug) AS title,
                                j.job_type AS employment_type, j.is_remote, j.is_featured,
                                j.application_deadline AS deadline,
                                j.salary_min, j.salary_max, j.salary_currency,
                                j.created_at
                           FROM jobs j
                      LEFT JOIN job_translations jt
                             ON jt.job_id = j.id AND jt.language_code = ?
                          WHERE j.status NOT IN ('cancelled','filled','closed')
                          ORDER BY j.is_featured DESC, j.created_at DESC
                          LIMIT 8"
                    );
                    $st->execute([$lang]);
                    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
                }

                default: break; // fall through to HTTP for 'ads', etc.
            }
        } catch (\Throwable $e) {
            // PDO query failed — fall through to HTTP fallback below.
            // To debug DB issues, uncomment:
            // error_log('[getSectionData] PDO error for type=' . $type . ': ' . $e->getMessage());
        }
    }

    // HTTP fallback (for ads and when PDO is unavailable or fails)
    return match ($type) {
        'ads' => (static function () use ($apiBase, $tenantId, $lang, $filter) {
            $url = $apiBase . "public/ads?tenant_id=$tenantId&lang=" . urlencode($lang);
            if ($filter !== '') $url .= '&placement_key=' . urlencode($filter);
            else $url .= '&limit=20';
            $result = pub_fetch($url);
            return $result['data']['data'] ?? $result['data'] ?? [];
        })(),
        'categories' => pub_fetch($apiBase . "public/categories?lang=" . urlencode($lang) . "&tenant_id=$tenantId&per=12")['data']['data'] ?? [],
        'products'   => pub_fetch($apiBase . "public/products?lang=" . urlencode($lang) . "&tenant_id=$tenantId&per=12")['data']['data'] ?? [],
        'deals'      => pub_fetch($apiBase . "public/discounts?tenant_id=$tenantId&lang=" . urlencode($lang) . "&per=12")['data']['data'] ?? [],
        'brands'     => pub_fetch($apiBase . "public/brands?lang=" . urlencode($lang) . "&tenant_id=$tenantId&per=12")['data']['data'] ?? [],
        'entities'   => pub_fetch($apiBase . "public/entities?lang=" . urlencode($lang) . "&tenant_id=$tenantId&per=12")['data']['data'] ?? [],
        'tenants'    => pub_fetch($apiBase . "public/tenants?lang=" . urlencode($lang) . "&per=12")['data']['data'] ?? [],
        'auctions'   => pub_fetch($apiBase . "public/auctions?lang=" . urlencode($lang) . "&tenant_id=$tenantId&per=8")['data']['auctions'] ?? [],
        'jobs'       => pub_fetch($apiBase . "public/jobs?lang=" . urlencode($lang) . "&per=8")['data']['data'] ?? [],
        'search', 'stats', 'html', 'custom' => [],
        default => [],
    };
}

// Render Header
include dirname(__DIR__) . '/partials/header.php';

$componentsDir = __DIR__ . '/components';

// Fetch homepage sections — PDO-first for reliability on shared hosting
$sections = [];
$_pdoSect = pub_get_pdo();
if ($_pdoSect) {
    try {
        $__stSect = $_pdoSect->prepare(
            "SELECT hs.id, hs.section_type, hs.layout_type,
                    hs.items_per_row, hs.background_color, hs.text_color, hs.padding,
                    hs.custom_css, hs.data_source, hs.sort_order, hs.is_active,
                    COALESCE(
                        NULLIF(TRIM(hs.component), ''),
                        CASE hs.section_type
                            WHEN 'categories' THEN 'ad_categories'
                            WHEN 'products'   THEN 'ad_products'
                            WHEN 'deals'      THEN 'ad_deals'
                            WHEN 'brands'     THEN 'ad_brands'
                            WHEN 'entities'   THEN 'ad_entities'
                            WHEN 'jobs'       THEN 'ad_jobs'
                            WHEN 'tenants'    THEN 'ad_tenants'
                            WHEN 'auctions'   THEN 'ad_auctions'
                            WHEN 'slider'     THEN 'ad_slider'
                            WHEN 'banners'    THEN 'ad_slider'
                            WHEN 'banner'     THEN 'ad_banner'
                            WHEN 'search'     THEN 'ad_search'
                            WHEN 'html'       THEN 'ad_html'
                            WHEN 'stats'      THEN 'ad_stats'
                            WHEN 'custom'     THEN 'ad_custom'
                            WHEN 'native'     THEN 'ad_native'
                            WHEN 'ads'        THEN 'ad_ads'
                            ELSE ''
                        END
                    ) AS component,
                    COALESCE(hst.title, hs.title)       AS title,
                    COALESCE(hst.subtitle, hs.subtitle) AS subtitle
               FROM homepage_sections hs
          LEFT JOIN homepage_section_translations hst
                 ON hst.section_id = hs.id AND hst.language_code = ?
              WHERE hs.tenant_id = ? AND hs.is_active = 1
              ORDER BY hs.sort_order ASC, hs.id ASC"
        );
        $__stSect->execute([$lang, $tenantId]);
        $sections = $__stSect->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (\Throwable $_) {
        // Fall through to HTTP fallback
    }
}
if (empty($sections)) {
    $sectionsResp = pub_fetch($apiBase . "public/homepage_sections?tenant_id=$tenantId&lang=" . urlencode($lang));
    $sections = $sectionsResp['data']['data'] ?? [];
}
unset($_pdoSect, $__stSect, $sectionsResp);

// Sort sections by sort_order
usort($sections, fn($a, $b) => ($a['sort_order'] ?? 999) <=> ($b['sort_order'] ?? 999));
?>

<div id="pub-homepage-sections" role="main">
<?php foreach ($sections as $section):
    if (!($section['is_active'] ?? 0)) continue;

    $component = pub_resolve_component($section);
    if (!$component) continue;

    $componentFile = $componentsDir . '/' . basename($component) . '.php';
    $sectionData = getSectionData($section['data_source'] ?? '', $section['section_type'] ?? '', $apiBase, $lang, $tenantId);

    // Build per-type card styles so components receive correct DB-driven styles
    $_cardStyles = [];
    foreach (['entities','products','tenants','brands','categories','auctions','jobs','deals'] as $_ct) {
        $_cardStyles[$_ct] = [
            'inline' => pub_card_inline_style($_ct),
            'class'  => pub_card_css_class($_ct),
        ];
    }
    unset($_ct);

    $secTitle = trim($section['title'] ?? '');
    $secSub   = trim($section['subtitle'] ?? '');
    $secBg    = _pub_safe_color($section['background_color'] ?? '');
    $sStyle   = $secBg ? 'background-color:' . htmlspecialchars($secBg) . ';' : '';
    $isFullWidth = in_array($component, ['ad_slider','ad_search','ad_html','ad_ads'], true);
?>
<section class="pub-section homepage-section homepage-section--<?= htmlspecialchars($component) ?>" <?= $sStyle ? 'style="' . $sStyle . '"' : '' ?>>
    <?php if ($isFullWidth): ?>
        <?php
        if (file_exists($componentFile)) {
            try {
                include $componentFile;
            } catch (\Throwable $e) {
                echo "<div style='color:red;'>خطأ في المكون: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        } else {
            echo "<div style='color:gray;'>ملف المكون غير موجود</div>";
        }
        ?>
    <?php else: ?>
        <div class="pub-container">
            <?php if ($secTitle !== ''): ?>
            <div class="pub-section-head">
                <h2 class="pub-section-title"><?= htmlspecialchars($secTitle) ?></h2>
                <?php if ($secSub !== ''): ?>
                <p class="pub-section-sub"><?= htmlspecialchars($secSub) ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php
            if (file_exists($componentFile)) {
                try {
                    include $componentFile;
                } catch (\Throwable $e) {
                    echo "<div style='color:red;'>خطأ في المكون: " . htmlspecialchars($e->getMessage()) . "</div>";
                }
            } else {
                echo "<div style='color:gray;'>ملف المكون غير موجود</div>";
            }
            ?>
        </div>
    <?php endif; ?>
</section>
<?php endforeach; ?>
</div>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>
