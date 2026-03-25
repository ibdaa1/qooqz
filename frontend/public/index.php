<?php
declare(strict_types=1);
/**
 * frontend/public/index.php
 * ─────────────────────────────────────────────────────────────────────────────
 * QOOQZ — Global Public Homepage
 *
 * Architecture
 * ────────────
 *  • 100 % DB-driven: all sections come from `homepage_sections`.
 *  • Each section delegates rendering to components/{component}.php.
 *  • getSectionData() resolves the `data_source` field to a live API payload.
 *  • Security: every output goes through e(); CSS values are sanitised before
 *    they touch a style attribute; custom CSS has tag-injection stripped.
 *  • Performance: sections are fetched in one API call; section data calls are
 *    deferred to render-time so only visible sections hit the API.
 *  • Internationalisation: lang + dir come from PUB_CONTEXT, so RTL (ar, he,
 *    fa, ur) and LTR languages work without any code changes.
 *
 * @package  QOOQZ\Frontend\Public
 * @version  2.0.0
 */

// ── Bootstrap ────────────────────────────────────────────────────────────────
require_once dirname(__DIR__) . '/includes/public_context.php';

$ctx      = $GLOBALS['PUB_CONTEXT'];
$lang     = $ctx['lang'];
$dir      = $ctx['dir'];           // 'rtl' | 'ltr'
$theme    = $ctx['theme'];
$tenantId = (int)$ctx['tenant_id'];
$apiBase  = pub_api_url('');

// Page meta — consumed by partials/header.php
$GLOBALS['PUB_APP_NAME']   = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH']  = '/frontend/public';
$GLOBALS['PUB_PAGE_TITLE'] = t('hero.title') . ' — QOOQZ';
$GLOBALS['PUB_PAGE_DESC']  = t('hero.subtitle');


// ═══════════════════════════════════════════════════════════════════════════════
//  SECTION 1 — CSS SANITISERS
//  Each helper returns either the safe value or an empty string.
//  They are defined once (guard prevents double-declaration across includes).
// ═══════════════════════════════════════════════════════════════════════════════

if (!function_exists('_pub_safe_color')) {
    /**
     * Allow only provably-safe CSS colour values.
     * Accepted: #hex3/4/6/8 · rgb/rgba/hsl/hsla(…) · named · var(--token)
     */
    function _pub_safe_color(string $v): string {
        $v = trim($v);
        if ($v === '') return '';
        if (preg_match('/^#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{1,5})?$/', $v))              return $v;
        if (preg_match('/^(?:rgb|rgba|hsl|hsla)\(\s*[\d\s%,.\/ ]+\)$/i', $v))         return $v;
        if (preg_match('/^[a-zA-Z]{2,30}$/', $v))                                      return $v;
        if (preg_match('/^var\(--[a-zA-Z0-9_-]{1,80}\)$/', $v))                       return $v;
        return '';
    }
}

if (!function_exists('_pub_safe_padding')) {
    /**
     * Allow 1–4 non-negative CSS length values.
     * Accepted units: px · em · rem · % · vh · vw · (unitless 0)
     */
    function _pub_safe_padding(string $v): string {
        $v = trim($v);
        if ($v === '') return '';
        $unit   = '(?:\d+(?:\.\d+)?(?:px|em|rem|%|vh|vw)?)';
        $single = $unit . '(?:\s+' . $unit . '){0,3}';
        return preg_match('/^' . $single . '$/', $v) ? $v : '';
    }
}

if (!function_exists('_pub_safe_css')) {
    /**
     * Strip anything that could turn custom_css into an XSS vector.
     * Removes: opening/closing tags · url() with data: or javascript: schemes ·
     *          expression() · behaviour: · @import · </style closing trick.
     */
    function _pub_safe_css(string $css): string {
        // Kill tag openers/closers
        $css = str_replace(['<', '>'], '', $css);
        // Kill dangerous CSS functions
        $css = preg_replace('/\bexpression\s*\(/i', '', $css);
        $css = preg_replace('/\bbehaviour\s*:/i',   '', $css);
        $css = preg_replace('/@import\b/i',         '', $css);
        // Kill data:/javascript: inside url()
        $css = preg_replace('/url\s*\(\s*["\']?\s*(?:data|javascript):/i', 'url(about:', $css);
        return $css;
    }
}


// ═══════════════════════════════════════════════════════════════════════════════
//  SECTION 2 — AD LINK BUILDER
//  Centralised so index.php and ad_ads component share identical logic.
// ═══════════════════════════════════════════════════════════════════════════════

if (!function_exists('_ad_link')) {
    /**
     * Convert a (target_type, target_value) pair into a safe absolute URL.
     *
     * @param  string $type  'url' | 'product' | 'category' | 'entity' | …
     * @param  string $value Raw value from DB
     * @return string        Absolute path or '#' on failure
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


// ═══════════════════════════════════════════════════════════════════════════════
//  SECTION 3 — COMPONENT REGISTRY
//  Single source of truth: section_type → component file name.
//  The homepage_sections API query also uses this mapping so both stay in sync.
//  To add a new type: add one entry here — no other file needs changing.
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

/**
 * Resolve component name from a homepage_sections row.
 *
 * Priority:
 *   1. Non-empty `component` column in the DB row (operator override).
 *   2. PUB_COMPONENT_MAP lookup by `section_type`.
 *   3. null — section is skipped; a debug log entry is written.
 */
function pub_resolve_component(array $section): ?string {
    $stored = trim($section['component'] ?? '');
    if ($stored !== '') return $stored;

    $type = strtolower(trim($section['section_type'] ?? ''));
    if (isset(PUB_COMPONENT_MAP[$type])) return PUB_COMPONENT_MAP[$type];

    // Unknown type — log but never surface an error to visitors
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
//  Resolves `data_source` (e.g. "products:featured") into a data array by
//  calling the appropriate public API endpoint.
//  Returns [] on any error — the component must handle empty gracefully.
// ═══════════════════════════════════════════════════════════════════════════════

function getSectionData(string $dataSource, string $apiBase, string $lang, int $tenantId): array {
    $dataSource = trim($dataSource);
    if ($dataSource === '') return [];

    [$type, $filter] = array_pad(explode(':', $dataSource, 2), 2, '');
    $type   = strtolower($type);
    $filter = trim($filter);

    // Common query-string used by most endpoints
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
            . ($filter === 'today'  ? '&expires_today=1'   : '')
            . ($filter === 'flash'  ? '&type=flash'        : '')
        )['data']['data'] ?? [],

        'brands' => pub_fetch(
            $apiBase . 'public/brands?' . $base
            . ($filter === 'featured' ? '&is_featured=1' : '')
        )['data']['data'] ?? [],

        // ── Banners / Slider ──────────────────────────────────────────────
        'banners', 'search' => (static function () use ($apiBase, $tenantId, $filter, $type): array {
            if ($type === 'search') return [];  // search bar needs no external data
            $pos    = ($filter !== '' && $filter !== 'all') ? '&position=' . urlencode($filter) : '';
            $data   = pub_fetch($apiBase . 'public/banners?tenant_id=' . $tenantId . $pos)['data']['data']
                   ?? pub_fetch($apiBase . 'public/banners?tenant_id=' . $tenantId . $pos)['data']
                   ?? [];
            // Fallback: position-filtered returned empty → load all banners
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
            // Fallback if filter yields nothing
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
        )['data']['auctions'] ?? [],   // NOTE: auctions route key differs from other routes

        // ── Jobs ──────────────────────────────────────────────────────────
        'jobs' => pub_fetch(
            $apiBase . 'public/jobs?lang=' . urlencode($lang) . '&per=8&page=1'
            . ($filter === 'featured' ? '&is_featured=1' : '')
            . ($filter === 'urgent'   ? '&is_urgent=1'   : '')
            . ($filter === 'remote'   ? '&is_remote=1'   : '')
        )['data']['data'] ?? [],

        // ── Ads (placement-aware; falls back to direct in ads.php) ────────
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
//  SECTION 5 — "View all" link map
//  Only components that logically have a browse page get a link.
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

/**
 * Components that own their full-width wrapper.
 * They receive no pub-container and no section-head injection.
 */
const PUB_FULL_WIDTH_COMPONENTS = [
    'ad_slider',
    'ad_search',
    'ad_banner',
    'ad_html',
];


// ═══════════════════════════════════════════════════════════════════════════════
//  SECTION 6 — Pre-resolve card styles
//  Read once from the theme; passed as $_cardStyles into every component.
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

// Defensive: must be an indexed array (array_is_list requires PHP 8.1+; use array_keys check instead)
if (!is_array($sections) || (!empty($sections) && array_keys($sections) !== range(0, count($sections) - 1))) {
    $sections = [];
}


// ═══════════════════════════════════════════════════════════════════════════════
//  SECTION 8 — Render sections
// ═══════════════════════════════════════════════════════════════════════════════

// Track whether an entities section actually rendered data (for the standalone
// entities fallback below — we only skip the fallback if data was truly shown).
$_entitiesRenderedViaSection = false;
?>
<div id="pub-homepage-sections" role="main">
<?php foreach ($sections as $section):

    // ── Resolve component ──────────────────────────────────────────────────
    $component = pub_resolve_component($section);
    if ($component === null) continue;   // unknown type — skip silently

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

    // Track whether entities were shown via a DB-driven section
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

    // ── Data attribute for JS hooks (e.g. lazy-load, analytics) ───────────
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
//  Always rendered below DB-driven sections.
//  Tracks impressions (IntersectionObserver ≥50 % viewport, ≥1 s dwell)
//  and clicks (fetch POST, keepalive) against ad_stats.
// ═══════════════════════════════════════════════════════════════════════════════

$_adsResult = pub_fetch(
    $apiBase . 'public/ads?tenant_id=' . $tenantId . '&lang=' . urlencode($lang)
);
// Extract the ads list: {"success":true,"data":{"ok":true,"data":[...]}}
$_adsRaw  = $_adsResult['data']['data'] ?? [];
$_adsData = is_array($_adsRaw)
    ? array_values(array_filter($_adsRaw, static fn($a) => !empty($a['id'])))
    : [];
?>

<?php if (!empty($_adsData)): ?>
<section class="pub-section homepage-section pub-ads-section" aria-label="<?= e(t('ads.section_title', 'إعلانات')) ?>">
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
            $_adExternal = ($_adType === 'url' && $_adHref !== '#');
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
//  SECTION 9b — Standalone Entities Section
//  Rendered when no DB-driven entities section already showed entity data.
//  Uses $_entitiesRenderedViaSection tracked in the section loop above.
// ═══════════════════════════════════════════════════════════════════════════════

$_entData = [];  // default — populated below if fallback is needed
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
<section class="pub-section homepage-section pub-entities-section" aria-label="<?= e(t('entities.section_title', 'بائعون مميزون')) ?>">
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
            $_entId   = (int)($_ent['id'] ?? 0);
            $_entName = trim((string)($_ent['store_name'] ?? $_ent['name'] ?? ''));
            $_entType = trim((string)($_ent['vendor_type'] ?? ''));
            $_entLogo = (string)($_ent['logo_url'] ?? '');
            $_entVerified = !empty($_ent['is_verified']);
            if ($_entId === 0) continue;
        ?>
        <a href="/frontend/public/entity.php?id=<?= $_entId ?>"
           class="pub-entity-card"
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
//  SECTION 10 — Ad tracking script (view + click)
//  Extracted into one <script> block at the bottom of the page.
//  __qzAdClick() is called from onclick attributes above.
//  IntersectionObserver is scoped to .pub-ad-card[data-ad-id].
// ═══════════════════════════════════════════════════════════════════════════════
?>
<script>
(function () {
    'use strict';

    var API  = '/api/public/ads/';
    var OPTS = { method: 'POST', keepalive: true };

    // ── Click tracking ─────────────────────────────────────────────────────
    window.__qzAdClick = function (adId) {
        if (!adId) return;
        fetch(API + adId + '/click', OPTS).catch(function () {});
    };

    // ── View / impression tracking ─────────────────────────────────────────
    if (!('IntersectionObserver' in window)) return;

    var viewed = new Set();
    var timers = {};

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            var el   = entry.target;
            var id   = el.dataset.adId;
            if (!id || id === '0' || viewed.has(id)) return;

            if (entry.isIntersecting) {
                if (timers[id]) return;                            // timer already running
                timers[id] = setTimeout(function () {
                    if (!viewed.has(id)) {
                        viewed.add(id);
                        fetch(API + id + '/view', OPTS).catch(function () {});
                    }
                    delete timers[id];
                }, 1000);                                          // 1 s dwell = 1 impression
            } else {
                clearTimeout(timers[id]);
                delete timers[id];
            }
        });
    }, { threshold: 0.5 });

    document.querySelectorAll('.pub-ad-card[data-ad-id]').forEach(function (el) {
        observer.observe(el);
    });
})();
</script>


<?php
// ═══════════════════════════════════════════════════════════════════════════════
//  SECTION 11 — Homepage engine initialisation
//  PubHomepageEngine is defined in public.js and handles:
//    • Lazy section refresh   • Real-time auction countdowns
//    • Wishlist / compare sync
// ═══════════════════════════════════════════════════════════════════════════════
?>
<script>
if (typeof PubHomepageEngine !== 'undefined') {
    PubHomepageEngine.init(<?= (int)$tenantId ?>, '<?= e($lang) ?>', '<?= e($dir) ?>');
}
</script>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>
