<?php
declare(strict_types=1);
/**
 * frontend/public/index.php
 * ─────────────────────────────────────────────────────────────────────────────
 * QOOQZ — Global Public Homepage  [v2.1.0 — Production]
 *
 * Fixes vs v2.0.0
 * ───────────────
 *  FIX-1  _ad_link() defined ONCE here; removed duplicate from ad_ads.php.
 *  FIX-2  IntersectionObserver lives ONLY in Section 10; removed from ad_ads.php.
 *  FIX-3  'search' match arm separated so 'banners' logic is unambiguous.
 *  FIX-4  Section-level ad_stats race-condition note added; UNIQUE KEY required in schema.
 *  FIX-5  __qzAdClick() is the ONLY click-tracking entry point (ad_ads uses it too).
 *
 * Schema migration required (run once):
 *   ALTER TABLE ads MODIFY COLUMN target_type
 *     ENUM('url','product','category','entity','brand','auction','job','page')
 *     DEFAULT 'url';
 *   ALTER TABLE ad_stats ADD UNIQUE KEY uq_ad_stats_ad_date (ad_id, date);
 *
 * @package  QOOQZ\Frontend\Public
 * @version  2.1.0
 */

// ── Bootstrap ────────────────────────────────────────────────────────────────
require_once dirname(__DIR__) . '/includes/public_context.php';

$ctx      = $GLOBALS['PUB_CONTEXT'];
$lang     = $ctx['lang'];
$dir      = $ctx['dir'];
$theme    = $ctx['theme'];
$tenantId = (int)$ctx['tenant_id'];
$apiBase  = pub_api_url('');

$GLOBALS['PUB_APP_NAME']   = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH']  = '/frontend/public';
$GLOBALS['PUB_PAGE_TITLE'] = t('hero.title') . ' — QOOQZ';
$GLOBALS['PUB_PAGE_DESC']  = t('hero.subtitle');


// ═══════════════════════════════════════════════════════════════════════════════
//  SECTION 1 — CSS SANITISERS
// ═══════════════════════════════════════════════════════════════════════════════

if (!function_exists('_pub_safe_color')) {
    function _pub_safe_color(string $v): string {
        $v = trim($v);
        if ($v === '') return '';
        if (preg_match('/^#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{1,5})?$/', $v))         return $v;
        if (preg_match('/^(?:rgb|rgba|hsl|hsla)\(\s*[\d\s%,.\/ ]+\)$/i', $v))    return $v;
        if (preg_match('/^[a-zA-Z]{2,30}$/', $v))                                 return $v;
        if (preg_match('/^var\(--[a-zA-Z0-9_-]{1,80}\)$/', $v))                  return $v;
        return '';
    }
}

if (!function_exists('_pub_safe_padding')) {
    function _pub_safe_padding(string $v): string {
        $v = trim($v);
        if ($v === '') return '';
        $unit   = '(?:\d+(?:\.\d+)?(?:px|em|rem|%|vh|vw)?)';
        $single = $unit . '(?:\s+' . $unit . '){0,3}';
        return preg_match('/^' . $single . '$/', $v) ? $v : '';
    }
}

if (!function_exists('_pub_safe_css')) {
    function _pub_safe_css(string $css): string {
        $css = str_replace(['<', '>'], '', $css);
        $css = preg_replace('/\bexpression\s*\(/i', '', $css);
        $css = preg_replace('/\bbehaviour\s*:/i',   '', $css);
        $css = preg_replace('/@import\b/i',         '', $css);
        $css = preg_replace('/url\s*\(\s*["\']?\s*(?:data|javascript):/i', 'url(about:', $css);
        return $css;
    }
}


// ═══════════════════════════════════════════════════════════════════════════════
//  SECTION 2 — AD LINK BUILDER  [FIX-1: single authoritative definition]
//  ad_ads.php component no longer redeclares this function.
//  All target_type values that exist in the ENUM are handled here.
// ═══════════════════════════════════════════════════════════════════════════════

if (!function_exists('_ad_link')) {
    /**
     * Convert (target_type, target_value) → safe absolute URL.
     *
     * Supported types match the ads.target_type ENUM (post-migration):
     *   url | product | category | entity | brand | auction | job | page
     *
     * @param  string $type  target_type column value
     * @param  string $value target_value column value
     * @return string        Absolute URL or '#' on failure / unknown type
     */
    function _ad_link(string $type, string $value): string {
        if ($value === '') return '#';

        return match ($type) {
            'url' => (static function (string $v): string {
                $scheme = parse_url($v, PHP_URL_SCHEME);
                return ($scheme !== null && in_array(strtolower($scheme), ['http', 'https'], true))
                    ? $v
                    : '#';
            })($value),
            'product'  => '/frontend/public/product.php?id='    . urlencode($value),
            'category' => '/frontend/public/categories.php?id=' . urlencode($value),
            'entity'   => '/frontend/public/entity.php?id='     . urlencode($value),
            'brand'    => '/frontend/public/brands.php?id='     . urlencode($value),
            'auction'  => '/frontend/public/auction.php?id='    . urlencode($value),
            'job'      => '/frontend/public/job.php?id='        . urlencode($value),
            'page'     => '/frontend/public/page.php?slug='     . urlencode($value),
            default    => '#',
        };
    }
}

/**
 * Whether an ad link should open in a new tab.
 * Only external URLs (target_type = 'url') open externally.
 */
function _ad_is_external(string $type, string $href): bool {
    return $type === 'url' && $href !== '#';
}


// ═══════════════════════════════════════════════════════════════════════════════
//  SECTION 3 — COMPONENT REGISTRY
// ═══════════════════════════════════════════════════════════════════════════════

const PUB_COMPONENT_MAP = [
    // Commerce
    'categories' => 'ad_categories',
    'products'   => 'ad_products',
    'deals'      => 'ad_deals',
    'brands'     => 'ad_brands',
    // Community / services
    'entities'   => 'ad_entities',
    'tenants'    => 'ad_tenants',
    'auctions'   => 'ad_auctions',
    'jobs'       => 'ad_jobs',
    // Layout / UI
    'slider'     => 'ad_slider',
    'banners'    => 'ad_slider',   // legacy alias
    'banner'     => 'ad_banner',
    'search'     => 'ad_search',
    'html'       => 'ad_html',
    // Ads
    'native'     => 'ad_native',
    'ads'        => 'ad_ads',
    // Widgets
    'stats'      => 'ad_stats',
    'custom'     => 'ad_custom',
];

function pub_resolve_component(array $section): ?string {
    $stored = trim($section['component'] ?? '');
    if ($stored !== '') return $stored;

    $type = strtolower(trim($section['section_type'] ?? ''));
    if (isset(PUB_COMPONENT_MAP[$type])) return PUB_COMPONENT_MAP[$type];

    if (defined('PUB_DEBUG') && PUB_DEBUG) {
        error_log(sprintf(
            '[QOOQZ:homepage] Unknown section_type "%s" (id=%d) — skipped.',
            $type,
            (int)($section['id'] ?? 0)
        ));
    }
    return null;
}


// ═══════════════════════════════════════════════════════════════════════════════
//  SECTION 4 — getSectionData()
// ═══════════════════════════════════════════════════════════════════════════════

function getSectionData(string $dataSource, string $apiBase, string $lang, int $tenantId): array {
    $dataSource = trim($dataSource);
    if ($dataSource === '') return [];

    [$type, $filter] = array_pad(explode(':', $dataSource, 2), 2, '');
    $type   = strtolower($type);
    $filter = trim($filter);

    $base = sprintf(
        'lang=%s&tenant_id=%d&per=12&page=1',
        urlencode($lang),
        $tenantId
    );

    return match ($type) {

        // ── Storefront ────────────────────────────────────────────────────
        'categories' => pub_fetch(
            $apiBase . 'public/categories?' . $base
            . ($filter === 'featured' ? '&featured=1' : '')
        )['data']['data'] ?? [],

        'products' => pub_fetch(
            $apiBase . 'public/products?' . $base
            . match ($filter) {
                'featured' => '&is_featured=1',
                'new'      => '&is_new=1',
                'sale'     => '&on_sale=1',
                default    => '',
            }
        )['data']['data'] ?? [],

        'deals' => pub_fetch(
            $apiBase . 'public/discounts?tenant_id=' . $tenantId
            . '&lang=' . urlencode($lang) . '&per=12&page=1'
            . ($filter === 'today' ? '&expires_today=1' : '')
            . ($filter === 'flash' ? '&type=flash'      : '')
        )['data']['data'] ?? [],

        'brands' => pub_fetch(
            $apiBase . 'public/brands?' . $base
            . ($filter === 'featured' ? '&is_featured=1' : '')
        )['data']['data'] ?? [],

        // ── Banners / Slider ──────────────────────────────────────────────
        // FIX-3: 'search' gets its own arm; banners logic is unambiguous.
        'search' => [],   // search bar requires no external data

        'banners' => (static function () use ($apiBase, $tenantId, $filter): array {
            $pos  = ($filter !== '' && $filter !== 'all') ? '&position=' . urlencode($filter) : '';
            $data = pub_fetch($apiBase . 'public/banners?tenant_id=' . $tenantId . $pos)['data']['data']
                 ?? pub_fetch($apiBase . 'public/banners?tenant_id=' . $tenantId . $pos)['data']
                 ?? [];
            // Fallback: position-filter yielded nothing → load all banners
            if (empty($data) && $pos !== '') {
                $data = pub_fetch($apiBase . 'public/banners?tenant_id=' . $tenantId)['data']['data']
                     ?? pub_fetch($apiBase . 'public/banners?tenant_id=' . $tenantId)['data']
                     ?? [];
            }
            return is_array($data) ? $data : [];
        })(),

        // ── Community & Services ──────────────────────────────────────────
        'entities' => (static function () use ($apiBase, $base, $filter): array {
            $extra = match ($filter) {
                'featured' => '&is_featured=1',
                'verified' => '&is_verified=1',
                default    => '',
            };
            $data = pub_fetch($apiBase . 'public/entities?' . $base . $extra)['data']['data'] ?? [];
            if (empty($data) && $extra !== '') {
                $data = pub_fetch($apiBase . 'public/entities?' . $base)['data']['data'] ?? [];
            }
            return $data;
        })(),

        'tenants' => pub_fetch(
            $apiBase . 'public/tenants?lang=' . urlencode($lang) . '&per=12&page=1'
            . ($filter === 'active' ? '&status=active' : '')
        )['data']['data'] ?? [],

        // ── Auctions ──────────────────────────────────────────────────────
        'auctions' => pub_fetch(
            $apiBase . 'public/auctions?lang=' . urlencode($lang)
            . '&tenant_id=' . $tenantId . '&per=6&page=1'
            . match ($filter) {
                'featured'  => '&featured=1&status=active',
                'scheduled' => '&status=scheduled',
                'ended'     => '&status=ended',
                default     => '&status=active',
            }
        )['data']['auctions'] ?? [],

        // ── Jobs ──────────────────────────────────────────────────────────
        'jobs' => pub_fetch(
            $apiBase . 'public/jobs?lang=' . urlencode($lang) . '&per=8&page=1'
            . ($filter === 'featured' ? '&is_featured=1' : '')
            . ($filter === 'urgent'   ? '&is_urgent=1'   : '')
            . ($filter === 'remote'   ? '&is_remote=1'   : '')
        )['data']['data'] ?? [],

        // ── Ads (placement-aware) ─────────────────────────────────────────
        'ads' => (static function () use ($apiBase, $tenantId, $lang, $filter): array {
            $url = $apiBase . 'public/ads?tenant_id=' . $tenantId . '&lang=' . urlencode($lang);
            if ($filter !== '') {
                $url .= '&placement_key=' . urlencode($filter);
            }
            $result = pub_fetch($url);
            $data   = $result['data']['data'] ?? $result['data'] ?? [];
            return is_array($data) ? $data : [];
        })(),

        // ── Self-fetching / no external data ─────────────────────────────
        'stats', 'html', 'custom' => [],

        // ── Unknown ───────────────────────────────────────────────────────
        default => (static function () use ($type): array {
            error_log('[QOOQZ:getSectionData] Unhandled data_source type: "' . $type . '"');
            return [];
        })(),
    };
}


// ═══════════════════════════════════════════════════════════════════════════════
//  SECTION 5 — "View all" link map + full-width component list
// ═══════════════════════════════════════════════════════════════════════════════

const PUB_VIEW_ALL_MAP = [
    'ad_categories' => '/frontend/public/categories.php',
    'ad_products'   => '/frontend/public/products.php',
    'ad_deals'      => '/frontend/public/products.php?sale=1',
    'ad_brands'     => '/frontend/public/brands.php',
    'ad_entities'   => '/frontend/public/entities.php',
    'ad_tenants'    => '/frontend/public/tenants.php',
    'ad_auctions'   => '/frontend/public/auctions.php',
    'ad_jobs'       => '/frontend/public/jobs.php',
];

const PUB_FULL_WIDTH_COMPONENTS = [
    'ad_slider',
    'ad_search',
    'ad_banner',
    'ad_html',
];


// ═══════════════════════════════════════════════════════════════════════════════
//  SECTION 6 — Pre-resolve card styles + include header
// ═══════════════════════════════════════════════════════════════════════════════

include dirname(__DIR__) . '/partials/header.php';

$_cardStyles = [
    'entities' => [
        'inline' => pub_card_inline_style('entities'),
        'class'  => pub_card_css_class('entities'),
    ],
    'tenants' => [
        'inline' => pub_card_inline_style('tenants'),
        'class'  => pub_card_css_class('tenants'),
    ],
    'product' => [
        'inline' => pub_card_inline_style('product'),
        'class'  => pub_card_css_class('product'),
        'img'    => pub_card_img_style('product'),
    ],
    'category' => [
        'inline' => pub_card_inline_style('category'),
        'class'  => pub_card_css_class('category'),
        'img'    => pub_card_img_style('category'),
    ],
    'auction' => [
        'inline' => pub_card_inline_style('auction'),
        'class'  => pub_card_css_class('auction'),
        'img'    => pub_card_img_style('auction'),
    ],
    'job' => [
        'inline' => pub_card_inline_style('job'),
        'class'  => pub_card_css_class('job'),
    ],
    'promo' => [
        'inline' => pub_card_inline_style('promo'),
        'class'  => pub_card_css_class('promo'),
    ],
    'feature' => [
        'inline' => pub_card_inline_style('feature'),
        'class'  => pub_card_css_class('feature'),
    ],
];

$componentsDir = __DIR__ . '/components';


// ═══════════════════════════════════════════════════════════════════════════════
//  SECTION 7 — Fetch sections from API
// ═══════════════════════════════════════════════════════════════════════════════

$sectionsResp = pub_fetch(
    $apiBase . 'public/homepage_sections?tenant_id=' . $tenantId . '&lang=' . urlencode($lang)
);
$sections = $sectionsResp['data']['data'] ?? $sectionsResp['data'] ?? [];

if (!is_array($sections) || (!empty($sections) && array_keys($sections) !== range(0, count($sections) - 1))) {
    $sections = [];
}


// ═══════════════════════════════════════════════════════════════════════════════
//  SECTION 8 — Render sections
// ═══════════════════════════════════════════════════════════════════════════════

$_entitiesRenderedViaSection = false;
?>
<div id="pub-homepage-sections" role="main">
<?php foreach ($sections as $section):

    // ── Resolve component ──────────────────────────────────────────────────
    $component = pub_resolve_component($section);
    if ($component === null) continue;

    $componentFile = $componentsDir . '/' . basename($component) . '.php';
    if (!is_file($componentFile)) {
        if (defined('PUB_DEBUG') && PUB_DEBUG) {
            error_log(sprintf(
                '[QOOQZ:homepage] Component file not found: %s.php (section id=%d)',
                $component,
                (int)($section['id'] ?? 0)
            ));
        }
        continue;
    }

    // ── Fetch section data ─────────────────────────────────────────────────
    $sectionData = getSectionData(
        $section['data_source'] ?? '',
        $apiBase,
        $lang,
        $tenantId
    );

    if ($component === 'ad_entities' && !empty($sectionData)) {
        $_entitiesRenderedViaSection = true;
    }

    // ── Build section inline style ─────────────────────────────────────────
    $secBg      = _pub_safe_color($section['background_color'] ?? '');
    $secText    = _pub_safe_color($section['text_color'] ?? '');
    $secPadding = _pub_safe_padding($section['padding'] ?? '');
    $secCss     = _pub_safe_css($section['custom_css'] ?? '');

    $sStyle = '';
    if ($secBg)      $sStyle .= 'background-color:' . e($secBg) . ';';
    if ($secText)    $sStyle .= 'color:'             . e($secText) . ';';
    if ($secPadding) $sStyle .= 'padding:'           . e($secPadding) . ';';

    // ── Section meta ───────────────────────────────────────────────────────
    $secTitle    = trim($section['title']    ?? '');
    $secSub      = trim($section['subtitle'] ?? '');
    $viewAllLink = PUB_VIEW_ALL_MAP[$component] ?? '';
    $isFullWidth = in_array($component, PUB_FULL_WIDTH_COMPONENTS, true);

    $sectionAttr = sprintf(
        ' data-section-id="%d" data-component="%s"',
        (int)($section['id'] ?? 0),
        e($component)
    );
?>
<section class="pub-section homepage-section homepage-section--<?= e($component) ?>"
         <?= $sStyle ? 'style="' . $sStyle . '"' : '' ?>
         <?= $sectionAttr ?>>

<?php if ($secCss !== ''): ?>
    <style data-section="<?= (int)($section['id'] ?? 0) ?>"><?= $secCss ?></style>
<?php endif; ?>

<?php if ($isFullWidth): ?>
    <?php include $componentFile; ?>
<?php else: ?>
    <div class="pub-container">

        <?php if ($secTitle !== ''): ?>
        <div class="pub-section-head">
            <h2 class="pub-section-title"><?= e($secTitle) ?></h2>
            <?php if ($viewAllLink !== ''): ?>
            <a href="<?= e($viewAllLink) ?>"
               class="pub-section-link"
               aria-label="<?= e(t('sections.view_all')) ?> — <?= e($secTitle) ?>">
                <?= e(t('sections.view_all')) ?>
            </a>
            <?php endif; ?>
        </div>
        <?php if ($secSub !== ''): ?>
        <p class="pub-section-sub"><?= e($secSub) ?></p>
        <?php endif; ?>
        <?php endif; ?>

        <?php include $componentFile; ?>

    </div>
<?php endif; ?>
</section>
<?php endforeach; ?>
</div><!-- #pub-homepage-sections -->


<?php
// ═══════════════════════════════════════════════════════════════════════════════
//  SECTION 9 — Standalone Ads Section
// ═══════════════════════════════════════════════════════════════════════════════

$_adsResult = pub_fetch(
    $apiBase . 'public/ads?tenant_id=' . $tenantId . '&lang=' . urlencode($lang)
);
$_adsRaw  = $_adsResult['data']['data'] ?? [];
$_adsData = is_array($_adsRaw)
    ? array_values(array_filter($_adsRaw, static fn($a) => !empty($a['id'])))
    : [];
?>

<?php if (!empty($_adsData)): ?>
<section class="pub-section homepage-section pub-ads-section"
         aria-label="<?= e(t('ads.section_title', 'إعلانات')) ?>">
    <div class="pub-container">
        <div class="pub-section-head">
            <h2 class="pub-section-title"><?= e(t('ads.section_title', 'إعلانات')) ?></h2>
        </div>
        <div class="pub-ads-grid">
        <?php foreach ($_adsData as $_ad):
            $_adId       = (int)($_ad['id'] ?? 0);
            $_adTitle    = trim((string)($_ad['title'] ?? ''));
            $_adDesc     = trim((string)($_ad['description'] ?? ''));
            $_adImg      = (string)($_ad['image_url'] ?? $_ad['thumb_url'] ?? '');
            $_adType     = (string)($_ad['target_type']  ?? '');
            $_adVal      = (string)($_ad['target_value'] ?? '');
            $_adHref     = _ad_link($_adType, $_adVal);
            // FIX-5: external flag used to conditionally add target="_blank"
            $_adExternal = _ad_is_external($_adType, $_adHref);
            if ($_adId === 0) continue;
        ?>
        <a href="<?= e($_adHref) ?>"
           class="pub-ad-card"
           <?= $_adExternal ? 'target="_blank" rel="noopener noreferrer"' : '' ?>
           data-ad-id="<?= $_adId ?>"
           onclick="__qzAdClick(<?= $_adId ?>)">

            <?php if ($_adImg !== ''): ?>
            <div class="pub-ad-img-wrap">
                <img src="<?= e(pub_img($_adImg)) ?>"
                     alt="<?= e($_adTitle) ?>"
                     class="pub-ad-img"
                     loading="lazy"
                     decoding="async">
            </div>
            <?php endif; ?>

            <?php if ($_adTitle !== '' || $_adDesc !== ''): ?>
            <div class="pub-ad-body">
                <?php if ($_adTitle !== ''): ?>
                <p class="pub-ad-title"><?= e($_adTitle) ?></p>
                <?php endif; ?>
                <?php if ($_adDesc !== ''): ?>
                <p class="pub-ad-desc"><?= e($_adDesc) ?></p>
                <?php endif; ?>
                <span class="pub-ad-badge" aria-hidden="true"><?= e(t('ads.sponsored', 'إعلان')) ?></span>
            </div>
            <?php endif; ?>

        </a>
        <?php endforeach; ?>
        </div><!-- .pub-ads-grid -->
    </div><!-- .pub-container -->
</section><!-- .pub-ads-section -->
<?php endif; ?>


<?php
// ═══════════════════════════════════════════════════════════════════════════════
//  SECTION 9b — Standalone Entities fallback
// ═══════════════════════════════════════════════════════════════════════════════

$_entData = [];
if (!$_entitiesRenderedViaSection) {
    $_entResult = pub_fetch(
        $apiBase . 'public/entities?tenant_id=' . $tenantId . '&lang=' . urlencode($lang) . '&per=12&page=1'
    );
    $_entRaw  = $_entResult['data']['data'] ?? [];
    $_entData = is_array($_entRaw)
        ? array_values(array_filter($_entRaw, static fn($e) => !empty($e['id'])))
        : [];
}
?>

<?php if (!$_entitiesRenderedViaSection && !empty($_entData)): ?>
<section class="pub-section homepage-section pub-entities-section"
         aria-label="<?= e(t('entities.section_title', 'بائعون مميزون')) ?>">
    <div class="pub-container">
        <div class="pub-section-head">
            <h2 class="pub-section-title"><?= e(t('entities.section_title', 'بائعون مميزون')) ?></h2>
            <a href="/frontend/public/entities.php"
               class="pub-section-link"
               aria-label="<?= e(t('sections.view_all', 'عرض الكل')) ?>">
                <?= e(t('sections.view_all', 'عرض الكل')) ?>
            </a>
        </div>
        <div class="pub-grid-md">
        <?php foreach ($_entData as $_ent):
            $_entId       = (int)($_ent['id'] ?? 0);
            $_entName     = trim((string)($_ent['store_name'] ?? $_ent['name'] ?? ''));
            $_entType     = trim((string)($_ent['vendor_type'] ?? ''));
            $_entLogo     = (string)($_ent['logo_url'] ?? '');
            $_entVerified = !empty($_ent['is_verified']);
            if ($_entId === 0) continue;
        ?>
        <a href="/frontend/public/entity.php?id=<?= $_entId ?>"
           class="pub-entity-card"
           data-track-type="entity"
           data-track-id="<?= $_entId ?>"
           style="text-decoration:none;">
            <div class="pub-entity-avatar">
                <?php if ($_entLogo !== ''): ?>
                    <img src="<?= e(pub_img($_entLogo, 'entity_logo')) ?>"
                         alt="<?= e($_entName) ?>"
                         loading="lazy"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                    <span style="display:none;" aria-label="">🏢</span>
                <?php else: ?>
                    <span aria-label="<?= e(t('entities.logo_placeholder', 'شعار الكيان')) ?>">🏢</span>
                <?php endif; ?>
            </div>
            <div class="pub-entity-info">
                <p class="pub-entity-name"><?= e($_entName) ?></p>
                <?php if ($_entType !== ''): ?>
                    <p class="pub-entity-desc"><?= e($_entType) ?></p>
                <?php endif; ?>
                <?php if ($_entVerified): ?>
                    <span class="pub-entity-verified">✅ <?= e(t('entities.verified', 'موثق')) ?></span>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
        </div><!-- .pub-grid-md -->
    </div><!-- .pub-container -->
</section><!-- .pub-entities-section -->
<?php endif; ?>


<?php
// ═══════════════════════════════════════════════════════════════════════════════
//  SECTION 10 — Ad tracking script  [FIX-2: single Observer, lives ONLY here]
//
//  __qzAdClick(id) — global; called from ALL ad markup on the page.
//  IntersectionObserver — queries ALL .pub-ad-card[data-ad-id] once, after DOM ready.
//
//  FIX-4 note: ad_stats MUST have UNIQUE KEY uq_ad_stats_ad_date (ad_id, date)
//  so the PHP INSERT IGNORE in the API actually prevents duplicate rows under
//  concurrent requests. Run the schema migration before deploying.
// ═══════════════════════════════════════════════════════════════════════════════
?>
<script>
(function () {
    'use strict';

    var API  = '/api/public/ads/';
    var OPTS = { method: 'POST', keepalive: true };

    // ── Unique-per-user deduplication via localStorage ─────────────────────
    // All ad interactions for today are stored in a single JSON object under
    // key "qz_ad_track_{YYYY-MM-DD}" to avoid per-event localStorage iteration.
    // The key for the previous day is removed once on init (one-time cleanup).
    var today = new Date().toISOString().slice(0, 10); // YYYY-MM-DD
    var STORE_KEY = 'qz_ad_track_' + today;
    var _track = null; // in-memory cache, populated lazily

    function _getTrack() {
        if (_track !== null) return _track;
        try {
            var raw = localStorage.getItem(STORE_KEY);
            _track = raw ? JSON.parse(raw) : {};
            if (typeof _track !== 'object' || _track === null) _track = {};
        } catch (e) {
            _track = {};
        }
        // One-time cleanup: remove yesterday's tracking key (and older)
        try {
            for (var i = localStorage.length - 1; i >= 0; i--) {
                var k = localStorage.key(i);
                if (k && k.slice(0, 13) === 'qz_ad_track_' && k !== STORE_KEY) {
                    localStorage.removeItem(k);
                }
            }
        } catch (e) {}
        return _track;
    }

    function _alreadyRecorded(field, adId) {
        try {
            var t = _getTrack();
            return !!(t[field + adId]);
        } catch (e) {
            return false; // localStorage unavailable (private mode etc.) — allow tracking
        }
    }

    function _markRecorded(field, adId) {
        try {
            var t = _getTrack();
            t[field + adId] = 1;
            localStorage.setItem(STORE_KEY, JSON.stringify(t));
        } catch (e) {}
    }

    // ── Click tracking ─────────────────────────────────────────────────────
    // Exposed globally so inline onclick="__qzAdClick(id)" works from any
    // component on the page (ad_ads.php, standalone section, etc.)
    window.__qzAdClick = function (adId) {
        if (!adId) return;
        if (_alreadyRecorded('c', adId)) return; // already clicked today
        _markRecorded('c', adId);
        fetch(API + adId + '/click', OPTS).catch(function () {});
    };

    // ── View / impression tracking ─────────────────────────────────────────
    if (!('IntersectionObserver' in window)) return;

    var timers = {};

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            var el   = entry.target;
            var id   = el.dataset.adId;
            if (!id || id === '0') return;

            if (entry.isIntersecting) {
                if (timers[id]) return;
                if (_alreadyRecorded('v', id)) return; // already viewed today
                timers[id] = setTimeout(function () {
                    if (!_alreadyRecorded('v', id)) {
                        _markRecorded('v', id);
                        fetch(API + id + '/view', OPTS).catch(function () {});
                    }
                    delete timers[id];
                }, 1000);
            } else {
                clearTimeout(timers[id]);
                delete timers[id];
            }
        });
    }, { threshold: 0.5 });

    // Observe every ad card on the page — rendered by any component or section.
    document.querySelectorAll('[data-ad-id]').forEach(function (el) {
        if (el.dataset.adId && el.dataset.adId !== '0') observer.observe(el);
    });
})();
</script>


<?php
// ═══════════════════════════════════════════════════════════════════════════════
//  SECTION 11 — Core-events tracking script
//
//  Tracks view / click for all entity cards rendered on this page:
//    products, entities, brands, categories, auctions, jobs.
//
//  Cards must carry  data-track-type="<entity_type>"  and
//                    data-track-id="<entity_id>"  attributes.
//
//  Events are de-duplicated in localStorage (daily key) to avoid
//  flooding the DB with repeated view/click rows per session.
//  All writes go to POST /api/public/events → core_events table.
// ═══════════════════════════════════════════════════════════════════════════════
?>
<script>
(function () {
    'use strict';

    var API_EVENTS = '/api/public/events';
    var today      = new Date().toISOString().slice(0, 10); // YYYY-MM-DD
    var STORE_KEY  = 'qz_ce_' + today;
    var _track     = null;

    // ── localStorage helpers ───────────────────────────────────────────────
    function _getTrack() {
        if (_track !== null) return _track;
        try {
            var raw = localStorage.getItem(STORE_KEY);
            _track = raw ? JSON.parse(raw) : {};
            if (typeof _track !== 'object' || _track === null) _track = {};
        } catch (e) {
            _track = {};
        }
        // Remove previous-day keys (one-time cleanup)
        try {
            for (var i = localStorage.length - 1; i >= 0; i--) {
                var k = localStorage.key(i);
                if (k && k.slice(0, 6) === 'qz_ce_' && k !== STORE_KEY) {
                    localStorage.removeItem(k);
                }
            }
        } catch (e) {}
        return _track;
    }

    function _recorded(key) {
        try { return !!_getTrack()[key]; } catch (e) { return false; }
    }

    function _markRecorded(key) {
        try {
            var t = _getTrack();
            t[key] = 1;
            localStorage.setItem(STORE_KEY, JSON.stringify(t));
        } catch (e) {}
    }

    function _unmarkRecorded(key) {
        try {
            var t = _getTrack();
            delete t[key];
            localStorage.setItem(STORE_KEY, JSON.stringify(t));
        } catch (e) {}
    }

    // ── Core tracking function — exposed globally ──────────────────────────
    // Used by pubAddToCart (add_to_cart), pubToggleWishlist (favorite),
    // and any page that needs to fire a manual event.
    // onFail: optional callback invoked when the API returns ok:false or on
    // network error — allows the caller to unmark localStorage so the event
    // can be retried later.
    window.pubTrackEvent = function (entityType, entityId, eventType, value, onFail) {
        if (!entityType || !entityId || !eventType) return;
        var params = 'entity_type=' + encodeURIComponent(entityType)
            + '&entity_id=' + encodeURIComponent(entityId)
            + '&event_type=' + encodeURIComponent(eventType);
        if (value !== undefined && value !== null) {
            params += '&value=' + encodeURIComponent(value);
        }
        fetch(API_EVENTS, {
            method:      'POST',
            headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:        params,
            keepalive:   true,
            credentials: 'include'
        }).then(function (resp) {
            return resp.json();
        }).then(function (json) {
            // ResponseFormatter wraps payload as {success, data: {ok:bool}}
            var ok = json && json.data && json.data.ok;
            if (!ok && typeof onFail === 'function') {
                onFail();
            }
        }).catch(function () {
            if (typeof onFail === 'function') onFail();
        });
    };

    // ── Click tracking via event delegation ───────────────────────────────
    // Registered first so it works even when IntersectionObserver is absent.
    // Capture phase so the event fires even when the element is a link.
    document.addEventListener('click', function (e) {
        var el = e.target.closest('[data-track-type][data-track-id]');
        if (!el) return;
        var type = el.dataset.trackType;
        var id   = el.dataset.trackId;
        if (!type || !id || id === '0') return;

        var key = 'c_' + type + '_' + id;
        if (_recorded(key)) return;
        _markRecorded(key);
        window.pubTrackEvent(type, parseInt(id, 10), 'click', undefined, function () {
            _unmarkRecorded(key); // allow retry later if API failed
        });
    }, true);

    // ── View tracking via IntersectionObserver ─────────────────────────────
    if (!('IntersectionObserver' in window)) return;

    var viewTimers = {};

    var viewObserver = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            var el   = entry.target;
            var type = el.dataset.trackType;
            var id   = el.dataset.trackId;
            if (!type || !id || id === '0') return;

            var key = 'v_' + type + '_' + id;

            if (entry.isIntersecting) {
                if (viewTimers[key]) return;
                if (_recorded(key)) return;
                viewTimers[key] = setTimeout(function () {
                    if (!_recorded(key)) {
                        _markRecorded(key);
                        window.pubTrackEvent(type, parseInt(id, 10), 'view', undefined, function () {
                            _unmarkRecorded(key); // allow retry later if API failed
                        });
                    }
                    delete viewTimers[key];
                }, 1000); // 1-second visibility threshold
            } else {
                clearTimeout(viewTimers[key]);
                delete viewTimers[key];
            }
        });
    }, { threshold: 0.5 });

    function _attachViewObserver(el) {
        if (el.dataset.trackId && el.dataset.trackId !== '0') {
            viewObserver.observe(el);
        }
    }

    document.querySelectorAll('[data-track-type][data-track-id]').forEach(_attachViewObserver);

    // ── Auto-observe cards added dynamically (e.g., by PubHomepageEngine) ──
    if ('MutationObserver' in window) {
        var _mutObs = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                mutation.addedNodes.forEach(function (node) {
                    if (node.nodeType !== 1) return;
                    if (node.dataset && node.dataset.trackType && node.dataset.trackId) {
                        _attachViewObserver(node);
                    }
                    if (node.querySelectorAll) {
                        node.querySelectorAll('[data-track-type][data-track-id]').forEach(_attachViewObserver);
                    }
                });
            });
        });
        _mutObs.observe(document.body, { childList: true, subtree: true });
    }
})();
</script>


<?php
// ═══════════════════════════════════════════════════════════════════════════════
//  SECTION 12 — Homepage engine initialisation
// ═══════════════════════════════════════════════════════════════════════════════
?>
<script>
if (typeof PubHomepageEngine !== 'undefined') {
    PubHomepageEngine.init(<?= (int)$tenantId ?>, '<?= e($lang) ?>', '<?= e($dir) ?>');
}
</script>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>
