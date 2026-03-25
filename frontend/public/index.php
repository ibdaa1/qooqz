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

function getSectionData(string $dataSource, string $apiBase, string $lang, int $tenantId): array {
    $dataSource = trim($dataSource);
    if ($dataSource === '') return [];

    [$type, $filter] = array_pad(explode(':', $dataSource, 2), 2, '');
    $type = strtolower($type);
    $filter = trim($filter);

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

// Fetch sections
$sectionsResp = pub_fetch($apiBase . "public/homepage_sections?tenant_id=$tenantId&lang=" . urlencode($lang));
$sections = $sectionsResp['data']['data'] ?? [];

// Sort sections by sort_order
usort($sections, fn($a, $b) => ($a['sort_order'] ?? 999) <=> ($b['sort_order'] ?? 999));
?>

<div id="pub-homepage-sections" role="main">
<?php foreach ($sections as $section):
    if (!($section['is_active'] ?? 0)) continue;

    $component = pub_resolve_component($section);
    if (!$component) continue;

    $componentFile = $componentsDir . '/' . basename($component) . '.php';
    $sectionData = getSectionData($section['data_source'] ?? '', $apiBase, $lang, $tenantId);

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
