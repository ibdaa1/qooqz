<?php
declare(strict_types=1);
/**
 * frontend/partials/header.php — Production v3.1
 * QOOQZ — Public Interface Header
 *
 * ─ Fixes vs v3.0 ──────────────────────────────────────────────
 *   FIX-1  Include guard → prevents double-render (PUB_HEADER_INCLUDED)
 *   FIX-2  Mobile search bar → shown as second row on ≤640px screens
 *   FIX-3  Inline hamburger script → data-bound='1' flag so public.js
 *          can detect and replace it without double-binding
 * ──────────────────────────────────────────────────────────────
 *
 * Design Principles
 *   1. Single source of CSS variables → set HERE, no JS overrides
 *   2. No !important on colors       → natural cascade specificity wins
 *   3. No race condition             → all CSS injected before <body>
 *   4. No duplication of vars        → underscore + hyphen in one pass
 *   5. generated_css from DB         → button/card concrete classes
 *
 * Responsibilities:
 *   1. HTML <head> with public CSS, fonts, meta tags, SEO
 *   2. <header> bar: logo + hamburger toggle + search bar
 *      (search renders inline on desktop, second row on mobile)
 *   3. Opens <div class="pub-layout"> and includes menu.php sidebar
 *   4. Opens <main class="pub-main-content"> for page content
 *
 * footer.php closes </main> and </div>.
 * menu.php renders the sidebar navigation independently.
 */

// ════════════════════════════════════════════════════════════
// 0-A. Include guard — prevent double-render
// ════════════════════════════════════════════════════════════
if (defined('PUB_HEADER_INCLUDED')) {
    return;
}
define('PUB_HEADER_INCLUDED', true);

// ════════════════════════════════════════════════════════════
// 0-B. Guard: no CLI, no direct /api/ access
// ════════════════════════════════════════════════════════════
if (php_sapi_name() === 'cli') {
    return;
}
if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
    http_response_code(403);
    exit('Direct access denied');
}

// ════════════════════════════════════════════════════════════
// 1. Context — loaded by public_context.php before this file
// ════════════════════════════════════════════════════════════
$_ctx      = $GLOBALS['PUB_CONTEXT'] ?? [];
$lang      = $_ctx['lang'] ?? 'ar';
$dir       = $_ctx['dir']  ?? 'rtl';
$theme     = $_ctx['theme'] ?? [];
$_seo      = $_ctx['seo']  ?? [];
$_user     = $_ctx['user'] ?? [];
$_tenantId = (int)($_ctx['tenant_id'] ?? 1);
$_isLoggedIn = !empty($_user['id']);
$_appName   = $GLOBALS['PUB_APP_NAME']  ?? 'QOOQZ';
$_pageTitle = $GLOBALS['PUB_PAGE_TITLE'] ?? ($_seo['title'] ?? $_appName);
$_pageDesc  = $GLOBALS['PUB_PAGE_DESC']  ?? ($_seo['description'] ?? '');
$_basePath  = rtrim($GLOBALS['PUB_BASE_PATH'] ?? '/frontend/public', '/');
$_authPath  = '/frontend';

// ════════════════════════════════════════════════════════════
// 2. Helpers
// ════════════════════════════════════════════════════════════
if (!function_exists('e')) {
    function e($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
if (!function_exists('t')) {
    function t(string $key, string|array $r = []): string {
        return is_string($r) ? $r : $key;
    }
}

// ════════════════════════════════════════════════════════════
// 3. Theme → CSS custom properties
//    Processes: color_settings, font_settings, design_settings
//    Creates both underscore AND hyphen variants in a single pass
// ════════════════════════════════════════════════════════════

if (!function_exists('_pub_safe_css_val')) {
    function _pub_safe_css_val(string $v): string {
        $v = trim($v);
        if ($v === '') return '';
        $v = preg_replace('/[{};`]/', '', $v);
        if ($v === '') return '';
        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $v)) return $v;
        if (preg_match('/^(rgb|rgba|hsl|hsla)\(\s*[\d\s%,.\/]+\)$/i', $v)) return $v;
        if (preg_match('/^[a-zA-Z]{2,30}$/', $v)) return $v;
        if (preg_match('/^var\(--[a-zA-Z0-9_-]+\)$/', $v)) return $v;
        if (preg_match('/^[\d.]+(px|em|rem|%|vh|vw|pt|ch|ex)(\s+[\d.]+(px|em|rem|%|vh|vw|pt|ch|ex))*$/', $v)) return $v;
        if (preg_match('/^[\d.]{1,6}$/', $v)) return $v;
        if (preg_match('/^[\d\s.]+(px|em|rem)?\s+(rgba?\([\d\s%,.\/]+\))$/i', $v)) return $v;
        if (preg_match('/^[\d.]+(px)?\s+[\d.]+(px)?\s+[\d.]+(px)?(\s+[\d.]+(px)?)?\s+(rgba?\([\d\s%,.\/]+\)|#[0-9a-fA-F]{3,8})$/i', $v)) return $v;
        if (preg_match('/^"[a-zA-Z0-9\s\-]{1,60}"$/', $v)) return $v;
        if (preg_match('/^[a-zA-Z0-9\s,"\'\-]+$/', $v) && strlen($v) <= 200) return $v;
        return '';
    }
}

$_cssVars = [];

$_setVar = function (string $key, string $value) use (&$_cssVars): void {
    if ($value === '') return;
    $safe = _pub_safe_css_val($value);
    if ($safe === '') return;
    $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '', $key);
    $keyU = '--' . str_replace('-', '_', $sanitized);
    $keyH = '--' . str_replace('_', '-', $sanitized);
    $_cssVars[$keyU] = $safe;
    if ($keyH !== $keyU) {
        $_cssVars[$keyH] = $safe;
    }
};

// ── Color settings ────────────────────────────────────────
foreach ($theme['color_settings'] ?? [] as $cs) {
    $k = trim($cs['setting_key'] ?? '');
    $v = trim($cs['color_value']  ?? ($cs['setting_value'] ?? ''));
    if ($k !== '' && $v !== '') {
        $_setVar($k, $v);
    }
}

// ── Font settings ─────────────────────────────────────────
foreach ($theme['font_settings'] ?? [] as $f) {
    $k = trim($f['setting_key'] ?? '');
    if ($k === '') continue;
    if (!empty($f['font_family'])) $_setVar("{$k}_family", $f['font_family']);
    if (!empty($f['font_size']))   $_setVar("{$k}_size",   $f['font_size']);
    if (!empty($f['font_weight'])) $_setVar("{$k}_weight", (string)$f['font_weight']);
}

// ── Design settings ───────────────────────────────────────
foreach ($theme['design_settings'] ?? [] as $d) {
    $k = trim($d['setting_key']   ?? '');
    $v = trim($d['setting_value'] ?? '');
    if ($k !== '' && $v !== '' && $k !== 'logo_url') {
        $_setVar($k, $v);
    }
}

// ── Button styles → CSS variables ────────────────────────
foreach ($theme['buttons'] ?? [] as $b) {
    $slug = trim($b['slug'] ?? '');
    if ($slug === '') continue;
    $slug = preg_replace('/[^a-z0-9-]/', '-', strtolower($slug));
    if (!empty($b['background_color']))       $_setVar("btn-{$slug}-bg",           (string)$b['background_color']);
    if (!empty($b['text_color']))             $_setVar("btn-{$slug}-color",        (string)$b['text_color']);
    if (!empty($b['border_color']))           $_setVar("btn-{$slug}-border",       (string)$b['border_color']);
    if (isset($b['border_width']))            $_setVar("btn-{$slug}-border-width", (int)$b['border_width'] . 'px');
    if (isset($b['border_radius']))           $_setVar("btn-{$slug}-radius",       (int)$b['border_radius'] . 'px');
    if (!empty($b['padding']))                $_setVar("btn-{$slug}-padding",      (string)$b['padding']);
    if (!empty($b['font_size']))              $_setVar("btn-{$slug}-font-size",    (is_numeric($b['font_size']) ? $b['font_size'] . 'px' : (string)$b['font_size']));
    if (!empty($b['font_weight']))            $_setVar("btn-{$slug}-font-weight",  (string)$b['font_weight']);
    if (!empty($b['hover_background_color'])) $_setVar("btn-{$slug}-hover-bg",     (string)$b['hover_background_color']);
    if (!empty($b['hover_text_color']))       $_setVar("btn-{$slug}-hover-color",  (string)$b['hover_text_color']);
    if (!empty($b['hover_border_color']))     $_setVar("btn-{$slug}-hover-border", (string)$b['hover_border_color']);
}

// ── Card styles → CSS variables ───────────────────────────
foreach ($theme['cards'] ?? [] as $c) {
    $slug = trim($c['slug'] ?? '');
    if ($slug === '') continue;
    $slug = preg_replace('/[^a-z0-9-]/', '-', strtolower($slug));
    if (!empty($c['background_color'])) $_setVar("card-{$slug}-bg",           (string)$c['background_color']);
    if (!empty($c['border_color']))     $_setVar("card-{$slug}-border",       (string)$c['border_color']);
    if (isset($c['border_width']))      $_setVar("card-{$slug}-border-width", (int)$c['border_width'] . 'px');
    if (isset($c['border_radius']))     $_setVar("card-{$slug}-radius",       (int)$c['border_radius'] . 'px');
    if (!empty($c['shadow_style']))     $_setVar("card-{$slug}-shadow",       (string)$c['shadow_style']);
    if (!empty($c['padding']))          $_setVar("card-{$slug}-padding",      (string)$c['padding']);
    if (!empty($c['text_color']))       $_setVar("card-{$slug}-text",         (string)$c['text_color']);
}

// ── Alias vars for compatibility ──────────────────────────
$_aliasVars = [];
$_getVar = function (string ...$names) use (&$_cssVars): string {
    foreach ($names as $n) {
        if (isset($_cssVars[$n]) && $_cssVars[$n] !== '') return $_cssVars[$n];
    }
    return '';
};
$_alias = function (string $target, string ...$sources) use (&$_cssVars, $_getVar, &$_aliasVars): void {
    if (isset($_cssVars[$target]) && $_cssVars[$target] !== '') return;
    $v = $_getVar(...$sources);
    if ($v !== '') $_aliasVars[$target] = $v;
};

$_alias('--surface-color',       '--surface_color', '--background-secondary', '--background_secondary');
$_alias('--card-bg',             '--card_bg',       '--surface-color', '--background-secondary');
$_alias('--input-bg',            '--input_bg',      '--surface-color', '--background-secondary');
$_alias('--input-background',    '--input_background', '--input-bg', '--input_bg');
$_alias('--background-tertiary', '--background_tertiary', '--background-secondary');
$_alias('--danger-color',        '--danger_color',  '--error-color',   '--error_color');
$_alias('--error-color',         '--error_color',   '--danger-color',  '--danger_color');
$_alias('--info-color',          '--info_color',    '--primary-color', '--primary_color');
$_alias('--text-secondary',      '--text_secondary', '--text-muted', '--text-light');
$_alias('--text-tertiary',       '--text_tertiary',  '--text-secondary', '--text_secondary');
$_alias('--border-color',        '--border_color',  '--border', '--divider-color');
$_alias('--input-placeholder',   '--input_placeholder', '--text-secondary', '--text_secondary');
$_alias('--sidebar-hover',       '--sidebar_hover',  '--primary-color', '--primary_color');
$_alias('--sidebar-active',      '--sidebar_active', '--primary-color', '--primary_color');

// Build :root CSS block
$_themeVars = '';
$_allVars   = array_merge($_cssVars, $_aliasVars);
if (!empty($_allVars)) {
    $parts = [];
    foreach ($_allVars as $name => $value) {
        $parts[] = '    ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
                 . ': '   . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . ';';
    }
    $_themeVars = implode("\n", $parts);
}

// ════════════════════════════════════════════════════════════
// 4. Font detection + DB font links
// ════════════════════════════════════════════════════════════
$_fontUrl = $dir === 'rtl'
    ? 'https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap'
    : 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap';

$_dbFontLinks  = [];
$_systemFonts  = [
    'system-ui','ui-sans-serif','ui-serif','ui-monospace',
    'sans-serif','serif','monospace','cursive','fantasy',
    'inherit','initial','unset',
    'arial','verdana','helvetica','helvetica neue','georgia',
    'times','times new roman','courier','courier new',
    'impact','trebuchet ms','comic sans ms','tahoma',
    'lucida','palatino','garamond',
];
$_trustedFontHosts = ['fonts.googleapis.com', 'fonts.gstatic.com'];
foreach ($theme['font_settings'] ?? [] as $_f) {
    if (empty($_f['font_family'])) continue;
    if (!empty($_f['font_url'])) {
        $_url     = $_f['font_url'];
        $_urlHost = parse_url($_url, PHP_URL_HOST);
        if (!$_urlHost || !in_array($_urlHost, $_trustedFontHosts, true)) continue;
    } else {
        $_primary = trim(explode(',', $_f['font_family'])[0], " \"'");
        if ($_primary === '' || in_array(strtolower($_primary), $_systemFonts, true)) continue;
        $_url = 'https://fonts.googleapis.com/css2?family=' . urlencode(str_replace(' ', '+', $_primary)) . ':wght@400;500;600;700&display=swap';
    }
    if (!in_array($_url, $_dbFontLinks, true)) {
        $_dbFontLinks[] = $_url;
    }
}

// ════════════════════════════════════════════════════════════
// 5. Logo
// ════════════════════════════════════════════════════════════
$_logoUrl = '';
if (!empty($theme['design_settings']) && is_array($theme['design_settings'])) {
    foreach ($theme['design_settings'] as $d) {
        if (($d['setting_key'] ?? '') === 'logo_url' && !empty($d['setting_value'])) {
            $_logoUrl = $d['setting_value'];
            break;
        }
    }
}
if (empty($_logoUrl)) {
    foreach (['logo.png', 'logo.svg', 'logo.webp'] as $_lf) {
        if (@file_exists(FRONTEND_BASE . '/assets/images/' . $_lf)) {
            $_logoUrl = '/frontend/assets/images/' . $_lf;
            break;
        }
    }
}

// ════════════════════════════════════════════════════════════
// 6. Cache-busting helper
// ════════════════════════════════════════════════════════════
if (!function_exists('_pub_asset_ver')) {
    function _pub_asset_ver(string $path): string {
        $full = ($_SERVER['DOCUMENT_ROOT'] ?? '') . $path;
        return file_exists($full) ? (string)filemtime($full) : '1';
    }
}

// ════════════════════════════════════════════════════════════
// 7. Theme-color for PWA meta tag
// ════════════════════════════════════════════════════════════
$_themeColor = $_allVars['--primary-color'] ?? $_allVars['--primary_color']
            ?? ($theme['primary'] ?? '#2d8cf0');
if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $_themeColor) && !preg_match('/^[a-zA-Z]{2,20}$/', $_themeColor)) {
    $_themeColor = '#2d8cf0';
}

?>
<!doctype html>
<html lang="<?= e($lang) ?>" dir="<?= e($dir) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="index, follow">
    <meta name="referrer" content="strict-origin-when-cross-origin">

    <!-- SEO -->
    <title><?= e($_pageTitle) ?></title>
    <?php if ($_pageDesc): ?>
    <meta name="description" content="<?= e($_pageDesc) ?>">
    <?php endif; ?>

    <!-- Open Graph -->
    <meta property="og:title"       content="<?= e($_pageTitle) ?>">
    <meta property="og:description" content="<?= e($_pageDesc) ?>">
    <meta property="og:type"        content="website">
    <meta property="og:site_name"   content="<?= e($_appName) ?>">

    <!-- PWA / Mobile -->
    <meta name="mobile-web-app-capable"                content="yes">
    <meta name="apple-mobile-web-app-capable"          content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title"            content="<?= e($_appName) ?>">
    <meta name="theme-color" content="<?= e($_themeColor) ?>">
    <link rel="manifest" href="/frontend/manifest.json">
    <link rel="apple-touch-icon" href="/images/default-image.png">

    <!-- DNS / Preconnect -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <!-- Fonts (default + DB fonts) -->
    <link rel="preload" href="<?= e($_fontUrl) ?>" as="style"
          onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="<?= e($_fontUrl) ?>"></noscript>
    <?php foreach ($_dbFontLinks as $_dbFont): ?>
    <link rel="preload" href="<?= e($_dbFont) ?>" as="style"
          onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="<?= e($_dbFont) ?>"></noscript>
    <?php endforeach; ?>

    <!-- Design Tokens (variables.css) — loaded FIRST -->
    <link rel="stylesheet"
          href="/frontend/assets/css/variables.css?v=<?= _pub_asset_ver('/frontend/assets/css/variables.css') ?>">

    <!-- CSS VARIABLES (single source of truth from DB) -->
    <style id="pub-theme-vars">
:root {
<?php if ($_themeVars): ?>
<?= $_themeVars . "\n" ?>
<?php endif; ?>
}

/* ── Baseline rules ── */
body {
    background:  var(--pub-bg, var(--background-main, var(--background_main, #ffffff)));
    color:       var(--pub-text, var(--text-primary, var(--text_primary, #222831)));
    font-family: var(--body-font-family, var(--body_font-family, "Cairo", "Inter", system-ui, sans-serif));
    margin: 0;
    padding: 0;
}

.pub-header {
    background: var(--pub-header-bg, var(--header-background, var(--header_background, var(--pub-primary, #2d8cf0))));
    color:      var(--pub-header-text, var(--header-text, var(--header_text, #ffffff)));
}

.pub-sidebar {
    background: var(--pub-sidebar-bg, var(--sidebar-background, var(--sidebar_background, var(--pub-header-bg, var(--pub-primary, #2d8cf0)))));
    color:      var(--pub-sidebar-text, var(--sidebar-text, var(--sidebar_text, #ffffff)));
}

.pub-footer {
    background: var(--pub-footer-bg, var(--footer-background, var(--footer_background, #1e2a38)));
    color:      var(--pub-footer-text, var(--footer-text, var(--footer_text, rgba(255,255,255,0.8))));
}

/* ── FIX-2: Mobile search bar — second row on ≤640px ── */
/* (Handled in public.css — no override needed here) */
@media (max-width: 640px) {
    .pub-layout {
        min-height: calc(100vh - 116px);
    }
}

/* ── Header action buttons (home, wishlist, cart, login/logout) ── */
.pub-header-actions {
    display: flex;
    align-items: center;
    gap: .3rem;
    flex-shrink: 0;
    margin-inline-start: .5rem;
}
.pub-header-action-btn {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    padding: .4rem .5rem;
    border-radius: 6px;
    color: var(--pub-header-text, var(--header-text, var(--header_text, #fff)));
    text-decoration: none;
    font-size: .82rem;
    font-weight: 600;
    transition: background .15s;
    white-space: nowrap;
    border: none;
    background: transparent;
    cursor: pointer;
}
.pub-header-action-btn:hover {
    background: rgba(255,255,255,.18);
}
.pub-header-action-btn--auth {
    background: rgba(255,255,255,.12);
    border: 1px solid rgba(255,255,255,.25);
}
.pub-header-action-btn--auth:hover {
    background: rgba(255,255,255,.25);
}
@media (max-width: 640px) {
    .pub-header-action-label { display: none; }
}

/* Language switcher */
.pub-lang-switcher {
    position: relative;
}
.pub-lang-dropdown {
    position: absolute;
    top: calc(100% + 4px);
    inset-inline-end: 0;
    min-width: 130px;
    background: var(--pub-surface, #fff);
    border: 1px solid var(--pub-border, #ddd);
    border-radius: 8px;
    box-shadow: 0 6px 24px rgba(0,0,0,.15);
    z-index: 9999;
    list-style: none;
    margin: 0; padding: 4px 0;
    font-size: .88rem;
    color: var(--pub-text, #222);
}
.pub-lang-dropdown[hidden] { display: none; }
.pub-lang-dropdown__item a {
    display: block;
    padding: .45rem .85rem;
    text-decoration: none;
    color: inherit;
    white-space: nowrap;
    transition: background .12s;
    border-radius: 4px;
}
.pub-lang-dropdown__item a:hover {
    background: var(--pub-hover, #f0f4ff);
    color: var(--pub-primary, #2d8cf0);
}
.pub-lang-dropdown__item--active a {
    background: var(--pub-hover, #f0f4ff);
    color: var(--pub-primary, #2d8cf0);
    font-weight: 700;
}

/* ══════════════════════════════════════════════════════════
   CATEGORY SLIDER + MEGA MENU
   ══════════════════════════════════════════════════════════ */
.pub-cat-bar {
    position: relative;
    background: var(--pub-cat-bar-bg, var(--pub-primary, #2d8cf0));
    border-top: 1px solid rgba(255,255,255,.12);
    z-index: 900;
}
.pub-cat-bar__inner {
    display: flex;
    align-items: stretch;
    overflow-x: auto;
    scroll-behavior: smooth;
    scrollbar-width: none;
    -ms-overflow-style: none;
    gap: 0;
    position: relative;
}
.pub-cat-bar__inner::-webkit-scrollbar { display: none; }

/* Scroll arrow buttons */
.pub-cat-bar__arrow {
    position: absolute;
    top: 0; bottom: 0;
    width: 36px;
    background: linear-gradient(to var(--arrow-dir,right), var(--pub-primary,#2d8cf0) 60%, transparent);
    border: none;
    color: #fff;
    cursor: pointer;
    z-index: 2;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    padding: 0;
    opacity: 0;
    pointer-events: none;
    transition: opacity .2s;
}
.pub-cat-bar__arrow--start { inset-inline-start: 0; --arrow-dir: left; }
.pub-cat-bar__arrow--end   { inset-inline-end:   0; --arrow-dir: right; }
.pub-cat-bar--can-start .pub-cat-bar__arrow--start,
.pub-cat-bar--can-end   .pub-cat-bar__arrow--end   { opacity: 1; pointer-events: auto; }

/* Category items */
.pub-cat-item {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    padding: .55rem .9rem;
    color: rgba(255,255,255,.92);
    font-size: .83rem;
    font-weight: 600;
    white-space: nowrap;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    text-decoration: none;
    transition: background .15s, border-color .15s;
    user-select: none;
    flex-shrink: 0;
}
.pub-cat-item:hover,
.pub-cat-item.active {
    background: rgba(255,255,255,.15);
    border-bottom-color: #fff;
    color: #fff;
}
.pub-cat-item img {
    width: 22px;
    height: 22px;
    object-fit: contain;
    border-radius: 3px;
    flex-shrink: 0;
}

/* ── Mega Menu panel ── */
.pub-mega-menu {
    position: absolute;
    inset-inline-start: 0;
    inset-inline-end: 0;
    top: 100%;
    background: var(--pub-surface, #fff);
    border: 1px solid var(--pub-border, #e0e0e0);
    border-top: none;
    border-radius: 0 0 10px 10px;
    box-shadow: 0 12px 40px rgba(0,0,0,.14);
    z-index: 1000;
    display: none;
    min-height: 280px;
    overflow: hidden;
}
.pub-mega-menu.open { display: flex; }

.pub-mega-menu__col {
    flex: 1 1 0;
    padding: 1rem 1.1rem;
    border-inline-end: 1px solid var(--pub-border, #e8e8e8);
    overflow: hidden;
    min-width: 0;
}
.pub-mega-menu__col:last-child { border-inline-end: none; }

.pub-mega-menu__heading {
    font-size: .72rem;
    font-weight: 700;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: var(--pub-muted, #888);
    margin: 0 0 .6rem;
    padding-bottom: .4rem;
    border-bottom: 1px solid var(--pub-border, #eee);
}

/* Subcategory list */
.pub-mega-sub__list {
    list-style: none;
    margin: 0; padding: 0;
    display: flex;
    flex-direction: column;
    gap: .2rem;
}
.pub-mega-sub__list a {
    display: block;
    padding: .3rem .4rem;
    font-size: .85rem;
    color: var(--pub-text, #222);
    text-decoration: none;
    border-radius: 5px;
    transition: background .12s, color .12s;
}
.pub-mega-sub__list a:hover {
    background: var(--pub-hover, #f0f4ff);
    color: var(--pub-primary, #2d8cf0);
}

/* Product grid */
.pub-mega-prods {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
    gap: .55rem;
}
.pub-mega-prod {
    background: var(--pub-surface, #fff);
    border: 1px solid var(--pub-border, #eee);
    border-radius: 8px;
    overflow: hidden;
    text-decoration: none;
    color: inherit;
    display: flex;
    flex-direction: column;
    transition: box-shadow .15s, transform .12s;
}
.pub-mega-prod:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,.12);
    transform: translateY(-2px);
}
.pub-mega-prod img {
    width: 100%;
    aspect-ratio: 1;
    object-fit: cover;
}
.pub-mega-prod__info {
    padding: .35rem .4rem;
    font-size: .76rem;
}
.pub-mega-prod__name {
    font-weight: 600;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    line-height: 1.3;
    margin-bottom: .2rem;
}
.pub-mega-prod__price {
    color: var(--pub-primary, #2d8cf0);
    font-weight: 700;
    font-size: .82rem;
}

/* Brand logos */
.pub-mega-brands {
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
}
.pub-mega-brand {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 72px;
    height: 50px;
    border: 1px solid var(--pub-border, #eee);
    border-radius: 7px;
    padding: .3rem;
    text-decoration: none;
    background: var(--pub-surface, #fff);
    transition: box-shadow .15s, border-color .15s;
}
.pub-mega-brand:hover {
    box-shadow: 0 2px 10px rgba(0,0,0,.1);
    border-color: var(--pub-primary, #2d8cf0);
}
.pub-mega-brand img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}
.pub-mega-brand--text {
    font-size: .72rem;
    font-weight: 600;
    color: var(--pub-text, #222);
    text-align: center;
    word-break: break-word;
    line-height: 1.2;
}

/* Skeleton/loading shimmer */
.pub-mega-skeleton {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: pubSkeleton .9s infinite;
    border-radius: 5px;
    min-height: 18px;
    margin-bottom: .4rem;
}
@keyframes pubSkeleton {
    0%   { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* Mobile accordion for category bar */
@media (max-width: 768px) {
    .pub-cat-bar__inner {
        flex-wrap: nowrap;
        padding-bottom: 2px;
    }
    .pub-mega-menu.open {
        flex-direction: column;
        position: fixed;
        inset: 0;
        top: var(--pub-mega-mobile-top, 110px);
        z-index: 1200;
        overflow-y: auto;
        border-radius: 0;
    }
    .pub-mega-menu__col {
        border-inline-end: none;
        border-bottom: 1px solid var(--pub-border, #eee);
    }
}
    </style>

    <!-- Public Stylesheet -->
    <link rel="stylesheet"
          href="/frontend/assets/css/public.css?v=<?= _pub_asset_ver('/frontend/assets/css/public.css') ?>">

    <!-- Generated CSS (DB-driven button/card/font styles) -->
    <?php if (!empty($theme['generated_css'])): ?>
    <style id="pub-theme-generated"><?= $theme['generated_css'] ?></style>
    <?php endif; ?>

    <!-- Homepage engine JS (deferred) -->
    <script defer
            src="/frontend/assets/js/homepage-engine.js?v=<?= _pub_asset_ver('/frontend/assets/js/homepage-engine.js') ?>">
    </script>

    <!-- Slider JS (deferred) -->
    <script defer
            src="/frontend/assets/js/slider.js?v=<?= _pub_asset_ver('/frontend/assets/js/slider.js') ?>">
    </script>

    <!-- Public JS (deferred) -->
    <script defer
            src="/frontend/assets/js/public.js?v=<?= _pub_asset_ver('/frontend/assets/js/public.js') ?>">
    </script>

    <?php
    // Inject logged-in user data so public.js / pubAddToCart can read it
    // without an extra API call. Only emit when a user is authenticated.
    if ($_isLoggedIn):
        $_jsUser = json_encode([
            'id'       => (int)($_user['id']       ?? 0),
            'name'     => (string)($_user['name']     ?? ''),
            'username' => (string)($_user['username'] ?? ''),
            'email'    => (string)($_user['email']    ?? ''),
        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    ?>
    <script>window.pubSessionUser = <?= $_jsUser ?>;</script>
    <?php endif; ?>
    <script>window.__qzTenantId = <?= $_tenantId ?>;</script>
</head>

<body class="pub-body <?= e($dir) ?>">

<!-- ═══════════════════════════════════════════════
     HEADER — hamburger + logo + search
     Search: inline on desktop, second row on mobile.
═══════════════════════════════════════════════════ -->
<header class="pub-header" role="banner">
    <div class="pub-container pub-header-inner">

        <!--
            Hamburger toggle (start side).
            data-bound="1" marks this inline script as the
            temporary listener. public.js replaces it cleanly
            to avoid double-binding (FIX-3).
        -->
        <button class="pub-hamburger" id="pubHamburger"
                aria-label="<?= e(t('nav.menu_open', 'القائمة')) ?>"
                aria-expanded="false" aria-controls="pubSidebar"
                data-bound="1">
            <span></span><span></span><span></span>
        </button>

        <!-- Logo -->
        <a href="<?= e($_basePath . '/index.php') ?>" class="pub-logo" aria-label="<?= e($_appName) ?>">
            <?php if (!empty($_logoUrl)): ?>
                <img src="<?= e($_logoUrl) ?>"
                     alt="<?= e($_appName) ?>"
                     class="pub-logo-img"
                     loading="eager"
                     decoding="async">
                <span class="pub-logo-name"><?= e($_appName) ?></span>
            <?php else: ?>
                <span class="pub-logo-icon" aria-hidden="true">🌐</span>
                <span class="pub-logo-name"><?= e($_appName) ?></span>
            <?php endif; ?>
        </a>

        <!-- Global search (inline desktop / second row mobile) -->
        <form class="pub-header-search"
              method="get"
              action="<?= e($_basePath . '/search.php') ?>"
              role="search"
              autocomplete="off"
              style="position:relative;">
            <input type="hidden" name="context" value="<?= e($GLOBALS['PUB_PAGE_TYPE'] ?? 'all') ?>">
            <input type="search"
                   name="q"
                   id="pubGlobalSearchInput"
                   class="pub-header-search-input"
                   placeholder="<?= e(t('search.placeholder', 'ابحث عن منتجات، متاجر...')) ?>"
                   value="<?= e($_GET['q'] ?? '') ?>"
                   aria-label="<?= e(t('search.placeholder', 'ابحث عن منتجات، متاجر...')) ?>"
                   aria-autocomplete="list"
                   aria-controls="pubSearchSuggest"
                   style="padding-inline-end:2.4rem;">
            <!-- Clear button — shown only when input has value -->
            <button type="button"
                    id="pubSearchClear"
                    aria-label="<?= e(t('search.clear', 'مسح البحث')) ?>"
                    style="position:absolute;inset-block-start:50%;inset-inline-end:calc(100% - 2.2rem);
                           transform:translateY(-50%);background:none;border:none;cursor:pointer;
                           color:var(--pub-muted,#888);font-size:1.1rem;padding:0 .35rem;line-height:1;
                           display:<?= !empty($_GET['q']) ? 'block' : 'none' ?>;"
                    >✖</button>
            <button type="submit" class="pub-header-search-btn">
                <?= e(t('search.button', 'بحث')) ?>
            </button>
            <ul id="pubSearchSuggest" role="listbox" hidden
                style="position:absolute;top:100%;inset-inline-start:0;min-width:300px;max-width:520px;width:100%;
                       background:var(--pub-surface,#fff);border:1px solid var(--pub-border,#ddd);
                       border-radius:8px;box-shadow:0 6px 24px rgba(0,0,0,.12);
                       list-style:none;margin:4px 0 0;padding:4px 0;z-index:9999;font-size:.9rem;"></ul>
        </form>

        <!-- Header action buttons: home, wishlist, cart, login/logout -->
        <nav class="pub-header-actions" aria-label="<?= e(t('nav.actions', 'الإجراءات')) ?>">
            <a href="<?= e($_basePath . '/index.php') ?>"
               class="pub-header-action-btn"
               title="<?= e(t('nav.home', 'الرئيسية')) ?>"
               aria-label="<?= e(t('nav.home', 'الرئيسية')) ?>">
                <span aria-hidden="true">🏠</span>
                <span class="pub-header-action-label"><?= e(t('nav.home', 'الرئيسية')) ?></span>
            </a>
            <a href="<?= e($_basePath . '/wishlist.php') ?>"
               class="pub-header-action-btn"
               title="<?= e(t('nav.wishlist', 'المفضلة')) ?>"
               aria-label="<?= e(t('nav.wishlist', 'المفضلة')) ?>">
                <span aria-hidden="true">♥</span>
                <span class="pub-header-action-label"><?= e(t('nav.wishlist', 'المفضلة')) ?></span>
            </a>
            <a href="<?= e($_basePath . '/cart.php') ?>"
               class="pub-header-action-btn"
               title="<?= e(t('nav.cart', 'سلة التسوق')) ?>"
               aria-label="<?= e(t('nav.cart', 'سلة التسوق')) ?>">
                <span aria-hidden="true">🛒</span>
                <span class="pub-header-action-label"><?= e(t('nav.cart', 'سلة التسوق')) ?></span>
            </a>
            <?php if ($_isLoggedIn): ?>
            <a href="<?= e($_authPath . '/logout.php') ?>"
               class="pub-header-action-btn pub-header-action-btn--auth"
               title="<?= e(t('nav.logout', 'تسجيل الخروج')) ?>"
               aria-label="<?= e(t('nav.logout', 'تسجيل الخروج')) ?>">
                <span aria-hidden="true">↩</span>
                <span class="pub-header-action-label"><?= e(t('nav.logout', 'تسجيل الخروج')) ?></span>
            </a>
            <?php else: ?>
            <a href="<?= e($_authPath . '/login.php') ?>"
               class="pub-header-action-btn pub-header-action-btn--auth"
               title="<?= e(t('nav.login', 'تسجيل الدخول')) ?>"
               aria-label="<?= e(t('nav.login', 'تسجيل الدخول')) ?>">
                <span aria-hidden="true">👤</span>
                <span class="pub-header-action-label"><?= e(t('nav.login', 'تسجيل الدخول')) ?></span>
            </a>
            <?php endif; ?>
            <!-- Language switcher -->
            <div class="pub-lang-switcher" id="pubLangSwitcher">
                <button type="button"
                        class="pub-header-action-btn"
                        id="pubLangBtn"
                        aria-haspopup="listbox"
                        aria-expanded="false"
                        title="<?= e(t('nav.language', 'اللغة')) ?>"
                        aria-label="<?= e(t('nav.language', 'اللغة')) ?>">
                    <span aria-hidden="true">🌐</span>
                    <span class="pub-header-action-label" id="pubLangLabel"><?= e(strtoupper($lang)) ?></span>
                </button>
                <ul class="pub-lang-dropdown" id="pubLangDropdown" role="listbox" hidden></ul>
            </div>
        </nav>

    </div>
</header>

<!-- ═══════════════════════════════════════════════
     CATEGORY SLIDER + MEGA MENU
═══════════════════════════════════════════════════ -->
<nav class="pub-cat-bar" id="pubCatBar" aria-label="<?= e(t('nav.categories', 'التصنيفات')) ?>">
    <button class="pub-cat-bar__arrow pub-cat-bar__arrow--start" id="pubCatArrowStart" aria-label="<?= e(t('nav.previous', 'السابق')) ?>" tabindex="-1">&#8249;</button>
    <div class="pub-cat-bar__inner" id="pubCatBarInner">
        <!-- Items injected by JS -->
        <?php for ($i = 0; $i < 6; $i++): ?>
        <span class="pub-cat-item" style="width:90px;"><span class="pub-mega-skeleton" style="width:70px;height:14px;border-radius:6px;"></span></span>
        <?php endfor; ?>
    </div>
    <button class="pub-cat-bar__arrow pub-cat-bar__arrow--end" id="pubCatArrowEnd" aria-label="<?= e(t('nav.next', 'التالي')) ?>" tabindex="-1">&#8250;</button>

    <!-- Mega menu panel — shared, repositioned on hover -->
    <div class="pub-mega-menu" id="pubMegaMenu" role="region" aria-live="polite">
        <!-- Content injected by JS -->
    </div>
</nav>

<script>
(function () {
    'use strict';

    var TENANT_ID  = (window.__qzTenantId || 0);
    var LANG       = <?= json_encode($lang) ?>;
    var BASE_PATH  = <?= json_encode($_basePath) ?>;
    var IS_RTL     = <?= json_encode($dir === 'rtl') ?>;
    var DEBOUNCE   = 150;

    var bar        = document.getElementById('pubCatBar');
    var inner      = document.getElementById('pubCatBarInner');
    var megaMenu   = document.getElementById('pubMegaMenu');
    var arrowStart = document.getElementById('pubCatArrowStart');
    var arrowEnd   = document.getElementById('pubCatArrowEnd');

    if (!bar || !inner || !megaMenu) return;

    /* ── Cache ─────────────────────────────────────────── */
    var _catCache   = {};   // category_id → {subcats, products, brands}
    var _topCats    = null; // top-level categories array

    /* ── State ─────────────────────────────────────────── */
    var _activeId   = null;
    var _menuTimer  = null;
    var _isMobile   = window.matchMedia('(max-width:768px)').matches;

    window.matchMedia('(max-width:768px)').addEventListener('change', function (e) {
        _isMobile = e.matches;
    });

    /* ── API helper ────────────────────────────────────── */
    function apiGet(url) {
        return fetch(url, { credentials: 'include' })
            .then(function (r) { return r.ok ? r.json() : null; });
    }

    function apiBase() {
        return '/api/public/';
    }

    function tenantQ() {
        return TENANT_ID ? ('&tenant_id=' + TENANT_ID) : '';
    }

    /* ── Load top-level categories ─────────────────────── */
    function loadTopCats() {
        var url = apiBase() + 'categories?parent_id=0&per=30&lang=' + encodeURIComponent(LANG) + tenantQ();
        apiGet(url).then(function (j) {
            if (!j) return;
            var cats = (j.data && j.data.data) ? j.data.data : (Array.isArray(j.data) ? j.data : []);
            _topCats = cats;
            renderCatBar(cats);
        }).catch(function () {});
    }

    /* ── Render category bar ───────────────────────────── */
    function renderCatBar(cats) {
        inner.innerHTML = '';
        if (!cats || !cats.length) { bar.style.display = 'none'; return; }
        bar.style.display = '';
        cats.forEach(function (cat) {
            var el = document.createElement('a');
            el.className = 'pub-cat-item';
            el.href = BASE_PATH + '/categories.php?id=' + encodeURIComponent(cat.id);
            el.dataset.catId = cat.id;
            el.setAttribute('aria-haspopup', 'true');
            el.setAttribute('aria-expanded', 'false');
            if (cat.image_url) {
                var img = document.createElement('img');
                img.src = cat.image_url;
                img.alt = '';
                img.loading = 'lazy';
                el.appendChild(img);
            }
            var span = document.createElement('span');
            span.textContent = cat.name || cat.slug || '';
            el.appendChild(span);
            inner.appendChild(el);

            if (_isMobile) {
                el.addEventListener('click', function (e) {
                    e.preventDefault();
                    toggleMobileMega(cat.id, el);
                });
            } else {
                el.addEventListener('mouseenter', debounce(function () {
                    openMega(cat.id, el);
                }, DEBOUNCE));
                el.addEventListener('focus', function () { openMega(cat.id, el); });
            }
        });
        updateArrows();
    }

    /* ── Fetch mega menu data ──────────────────────────── */
    function fetchMegaData(catId) {
        if (_catCache[catId]) return Promise.resolve(_catCache[catId]);

        var langQ = '&lang=' + encodeURIComponent(LANG);
        var tQ    = tenantQ();

        var pSubs  = apiGet(apiBase() + 'categories?parent_id=' + catId + '&per=15' + langQ + tQ);
        var pProds = apiGet(apiBase() + 'products?category_id=' + catId + '&per=8' + langQ + tQ);
        var pBrands= apiGet(apiBase() + 'brands?per=10' + langQ + tQ);

        return Promise.all([pSubs, pProds, pBrands]).then(function (results) {
            var subs    = extractList(results[0]);
            var prods   = extractList(results[1]);
            var brands  = extractList(results[2]);
            var data    = { subs: subs, prods: prods, brands: brands };
            _catCache[catId] = data;
            return data;
        });
    }

    function extractList(j) {
        if (!j) return [];
        var d = j.data || j;
        if (Array.isArray(d)) return d;
        if (d && Array.isArray(d.data)) return d.data;
        return [];
    }

    /* ── Render mega menu ──────────────────────────────── */
    function renderMega(data) {
        var html = '';

        /* Column A: Subcategories */
        html += '<div class="pub-mega-menu__col">';
        html += '<p class="pub-mega-menu__heading">' + escapeHtml(<?= json_encode(t('mega.subcategories','التصنيفات الفرعية')) ?>) + '</p>';
        if (data.subs && data.subs.length) {
            html += '<ul class="pub-mega-sub__list">';
            data.subs.forEach(function (s) {
                html += '<li><a href="' + escapeAttr(BASE_PATH + '/categories.php?id=' + s.id) + '">' + escapeHtml(s.name || s.slug || '') + '</a></li>';
            });
            html += '</ul>';
        } else {
            html += '<p style="font-size:.82rem;color:var(--pub-muted,#aaa)">' + escapeHtml(<?= json_encode(t('mega.no_subcategories','لا توجد تصنيفات فرعية')) ?>) + '</p>';
        }
        html += '</div>';

        /* Column B: Featured Products */
        html += '<div class="pub-mega-menu__col">';
        html += '<p class="pub-mega-menu__heading">' + escapeHtml(<?= json_encode(t('mega.featured_products','منتجات مميزة')) ?>) + '</p>';
        if (data.prods && data.prods.length) {
            html += '<div class="pub-mega-prods">';
            data.prods.slice(0, 8).forEach(function (p) {
                var href = BASE_PATH + '/product.php?id=' + p.id;
                html += '<a class="pub-mega-prod" href="' + escapeAttr(href) + '">';
                if (p.image_url || p.thumbnail_url) {
                    html += '<img src="' + escapeAttr(p.image_url || p.thumbnail_url) + '" alt="' + escapeAttr(p.name || '') + '" loading="lazy">';
                } else {
                    html += '<div style="aspect-ratio:1;background:var(--pub-hover,#f0f0f0)"></div>';
                }
                html += '<div class="pub-mega-prod__info">';
                html += '<div class="pub-mega-prod__name">' + escapeHtml(p.name || '') + '</div>';
                if (p.price !== undefined && p.price !== null) {
                    html += '<div class="pub-mega-prod__price">' + escapeHtml(formatPrice(p.price, p.currency)) + '</div>';
                }
                html += '</div></a>';
            });
            html += '</div>';
        } else {
            html += '<p style="font-size:.82rem;color:var(--pub-muted,#aaa)">' + escapeHtml(<?= json_encode(t('mega.no_products','لا توجد منتجات')) ?>) + '</p>';
        }
        html += '</div>';

        /* Column C: Top Brands */
        html += '<div class="pub-mega-menu__col">';
        html += '<p class="pub-mega-menu__heading">' + escapeHtml(<?= json_encode(t('mega.top_brands','أبرز العلامات')) ?>) + '</p>';
        if (data.brands && data.brands.length) {
            html += '<div class="pub-mega-brands">';
            data.brands.slice(0, 10).forEach(function (b) {
                var href = BASE_PATH + '/products.php?brand_id=' + b.id;
                html += '<a class="pub-mega-brand" href="' + escapeAttr(href) + '" title="' + escapeAttr(b.name || '') + '">';
                if (b.logo_url) {
                    html += '<img src="' + escapeAttr(b.logo_url) + '" alt="' + escapeAttr(b.name || '') + '" loading="lazy">';
                } else {
                    html += '<span class="pub-mega-brand--text">' + escapeHtml(b.name || '') + '</span>';
                }
                html += '</a>';
            });
            html += '</div>';
        } else {
            html += '<p style="font-size:.82rem;color:var(--pub-muted,#aaa)">' + escapeHtml(<?= json_encode(t('mega.no_brands','لا توجد علامات')) ?>) + '</p>';
        }
        html += '</div>';

        megaMenu.innerHTML = html;
    }

    /* ── Skeleton loading ──────────────────────────────── */
    function renderSkeleton() {
        var sk = '';
        for (var c = 0; c < 3; c++) {
            sk += '<div class="pub-mega-menu__col">';
            for (var r = 0; r < 5; r++) {
                sk += '<div class="pub-mega-skeleton" style="margin-bottom:.5rem;height:' + (r === 0 ? 14 : 18) + 'px;"></div>';
            }
            sk += '</div>';
        }
        megaMenu.innerHTML = sk;
    }

    /* ── Open mega menu ────────────────────────────────── */
    function openMega(catId, itemEl) {
        clearTimeout(_menuTimer);
        _activeId = catId;

        /* Mark active item */
        inner.querySelectorAll('.pub-cat-item').forEach(function (el) {
            el.classList.toggle('active', el.dataset.catId == catId);
            el.setAttribute('aria-expanded', el.dataset.catId == catId ? 'true' : 'false');
        });

        megaMenu.classList.add('open');
        renderSkeleton();

        fetchMegaData(catId).then(function (data) {
            if (_activeId == catId) renderMega(data);
        }).catch(function () {
            megaMenu.classList.remove('open');
        });
    }

    /* ── Close mega menu ───────────────────────────────── */
    function closeMega() {
        _activeId = null;
        megaMenu.classList.remove('open');
        inner.querySelectorAll('.pub-cat-item').forEach(function (el) {
            el.classList.remove('active');
            el.setAttribute('aria-expanded', 'false');
        });
    }

    /* ── Mobile toggle ─────────────────────────────────── */
    function toggleMobileMega(catId, itemEl) {
        if (_activeId == catId && megaMenu.classList.contains('open')) {
            closeMega();
        } else {
            openMega(catId, itemEl);
        }
    }

    /* ── Mouse-leave guard (keep open while inside menu) ── */
    bar.addEventListener('mouseleave', function () {
        if (!_isMobile) {
            _menuTimer = setTimeout(closeMega, 250);
        }
    });
    bar.addEventListener('mouseenter', function () {
        clearTimeout(_menuTimer);
    });

    /* ── Close on outside click ────────────────────────── */
    document.addEventListener('click', function (e) {
        if (!bar.contains(e.target)) closeMega();
    });

    /* ── Keyboard: Escape ──────────────────────────────── */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeMega();
    });

    /* ── Scroll arrows ─────────────────────────────────── */
    function updateArrows() {
        var sl = inner.scrollLeft;
        var maxSl = inner.scrollWidth - inner.clientWidth;
        if (IS_RTL) {
            bar.classList.toggle('pub-cat-bar--can-start', sl < -2);
            bar.classList.toggle('pub-cat-bar--can-end',   sl > -(maxSl - 2));
        } else {
            bar.classList.toggle('pub-cat-bar--can-start', sl > 2);
            bar.classList.toggle('pub-cat-bar--can-end',   sl < maxSl - 2);
        }
    }
    inner.addEventListener('scroll', updateArrows, { passive: true });

    arrowStart.addEventListener('click', function () {
        inner.scrollBy({ left: IS_RTL ? 160 : -160, behavior: 'smooth' });
    });
    arrowEnd.addEventListener('click', function () {
        inner.scrollBy({ left: IS_RTL ? -160 : 160, behavior: 'smooth' });
    });

    /* ── Touch / drag scroll ───────────────────────────── */
    var _touchX = null;
    inner.addEventListener('touchstart', function (e) {
        _touchX = e.touches[0].clientX;
    }, { passive: true });
    inner.addEventListener('touchmove', function (e) {
        if (_touchX === null) return;
        var dx = _touchX - e.touches[0].clientX;
        inner.scrollLeft += dx;
        _touchX = e.touches[0].clientX;
    }, { passive: true });
    inner.addEventListener('touchend', function () { _touchX = null; });

    /* ── Utilities ─────────────────────────────────────── */
    function debounce(fn, ms) {
        var t;
        return function () {
            var ctx = this, args = arguments;
            clearTimeout(t);
            t = setTimeout(function () { fn.apply(ctx, args); }, ms);
        };
    }

    function escapeHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    }

    function escapeAttr(s) {
        return String(s).replace(/"/g,'&quot;');
    }

    function formatPrice(price, currency) {
        var n = parseFloat(price);
        if (isNaN(n)) return '';
        return n.toLocaleString(LANG === 'ar' ? 'ar-SA' : 'en-US', {
            style: 'currency',
            currency: currency || 'SAR',
            maximumFractionDigits: 2,
        });
    }

    /* ── Boot ──────────────────────────────────────────── */
    loadTopCats();

})();
</script>

<script>
/* ── Language Switcher ─────────────────────────────── */
(function () {
    'use strict';

    var btn      = document.getElementById('pubLangBtn');
    var dropdown = document.getElementById('pubLangDropdown');
    if (!btn || !dropdown) return;

    var CURRENT_LANG = <?= json_encode($lang) ?>;
    var _langs = null;

    function buildSwitchUrl(code) {
        var url = new URL(window.location.href);
        url.searchParams.set('lang', code);
        return url.toString();
    }

    function renderDropdown(langs) {
        dropdown.innerHTML = '';
        langs.forEach(function (l) {
            var li = document.createElement('li');
            li.className = 'pub-lang-dropdown__item' +
                (l.code === CURRENT_LANG ? ' pub-lang-dropdown__item--active' : '');
            li.setAttribute('role', 'option');
            li.setAttribute('aria-selected', String(l.code === CURRENT_LANG));
            var a = document.createElement('a');
            a.href = buildSwitchUrl(l.code);
            a.textContent = l.name;
            li.appendChild(a);
            dropdown.appendChild(li);
        });
    }

    function openDropdown() {
        if (_langs) {
            renderDropdown(_langs);
            dropdown.hidden = false;
            btn.setAttribute('aria-expanded', 'true');
            return;
        }
        fetch('/api/public/languages', { credentials: 'include' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (j) {
                if (!j) return;
                _langs = (j.data && Array.isArray(j.data.data)) ? j.data.data
                       : (Array.isArray(j.data) ? j.data : []);
                if (_langs.length === 0) return;
                renderDropdown(_langs);
                dropdown.hidden = false;
                btn.setAttribute('aria-expanded', 'true');
            })
            .catch(function () {});
    }

    function closeDropdown() {
        dropdown.hidden = true;
        btn.setAttribute('aria-expanded', 'false');
    }

    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        if (dropdown.hidden) { openDropdown(); } else { closeDropdown(); }
    });

    dropdown.addEventListener('click', function (e) { e.stopPropagation(); });

    document.addEventListener('click', function () { closeDropdown(); });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { closeDropdown(); }
    });
})();
</script>

<!-- ═══════════════════════════════════════════════
     LAYOUT: sidebar (menu.php) + main content
═══════════════════════════════════════════════════ -->
<div class="pub-layout">

    <?php
    // Sidebar menu — completely separate from header
    $menuFile = __DIR__ . '/menu.php';
    if (is_readable($menuFile)) {
        include $menuFile;
    }
    ?>

    <!-- Mobile sidebar backdrop -->
    <div class="pub-sidebar-backdrop" id="pubSidebarOverlay" aria-hidden="true"></div>

    <!--
        Minimal inline fallback hamburger script.
        Runs immediately so the sidebar works even before public.js loads.
        public.js will replace this listener via cloneNode() to prevent
        double-binding (FIX-3: data-bound="1" on the button is the signal).
    -->
    <script>
    (function () {
        var h = document.getElementById('pubHamburger');
        var s = document.getElementById('pubSidebar');
        var b = document.getElementById('pubSidebarOverlay');
        var c = document.getElementById('pubSidebarClose');
        if (!h || !s) return;

        function open()  {
            s.classList.add('open');
            if (b) b.classList.add('open');
            h.setAttribute('aria-expanded', 'true');
            document.body.style.overflow = 'hidden';
        }
        function close() {
            s.classList.remove('open');
            if (b) b.classList.remove('open');
            h.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
        }

        h.addEventListener('click', function () {
            s.classList.contains('open') ? close() : open();
        });
        if (b) b.addEventListener('click', close);
        if (c) c.addEventListener('click', close);
    })();
    </script>

    <!-- Live search autocomplete (grouped suggestions + clear button) -->
    <script>
    (function () {
        var inp   = document.getElementById('pubGlobalSearchInput');
        var list  = document.getElementById('pubSearchSuggest');
        var clearBtn = document.getElementById('pubSearchClear');
        if (!inp || !list) return;

        var timer = null;
        var lastQ = '';

        /* ── i18n strings (injected from PHP) ─────────────────── */
        var _s = <?= json_encode([
            'recent'        => ($GLOBALS['PUB_STRINGS']['search']['recent']        ?? 'Recent Searches'),
            'popular'       => ($GLOBALS['PUB_STRINGS']['search']['popular']       ?? 'Popular Searches'),
            'clear_history' => ($GLOBALS['PUB_STRINGS']['search']['clear_history'] ?? 'Clear History'),
            'no_recent'     => ($GLOBALS['PUB_STRINGS']['search']['no_recent']     ?? 'No recent searches'),
            'view_all'      => ($GLOBALS['PUB_STRINGS']['search']['view_all']      ?? 'View all results'),
            'type_products'   => ($GLOBALS['PUB_STRINGS']['search']['type_products']   ?? '🛍 Products'),
            'type_categories' => ($GLOBALS['PUB_STRINGS']['search']['type_categories'] ?? '📂 Categories'),
            'type_entities'   => ($GLOBALS['PUB_STRINGS']['search']['type_entities']   ?? '🏢 Stores'),
            'type_jobs'       => ($GLOBALS['PUB_STRINGS']['search']['type_jobs']       ?? '💼 Jobs'),
            'type_brands'     => ($GLOBALS['PUB_STRINGS']['search']['type_brands']     ?? '🏷 Brands'),
            'type_auctions'   => ($GLOBALS['PUB_STRINGS']['search']['type_auctions']   ?? '🔨 Auctions'),
        ], JSON_UNESCAPED_UNICODE) ?>;

        /* ── helpers ──────────────────────────────────────────── */
        function _esc(s) {
            return String(s).replace(/[&<>"']/g, function (c) {
                return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
            });
        }

        function _highlight(text, q) {
            if (!q) return _esc(text);
            var re = new RegExp('(' + q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
            return _esc(text).replace(re, '<mark style="background:rgba(255,220,0,.4);border-radius:2px;">$1</mark>');
        }

        function hide() {
            list.hidden = true;
            list.innerHTML = '';
        }

        function showClear(has) {
            if (clearBtn) clearBtn.style.display = has ? 'block' : 'none';
        }

        /* ── recent searches (localStorage) ──────────────────── */
        var _storageKey = 'qz_recent_searches';
        function _getRecent() {
            try {
                return JSON.parse(localStorage.getItem(_storageKey) || '[]');
            } catch(e) { return []; }
        }
        function _saveRecent(q) {
            if (!q || q.length < 2) return;
            var list2 = _getRecent().filter(function(x){ return x !== q; });
            list2.unshift(q);
            if (list2.length > 8) list2 = list2.slice(0, 8);
            try { localStorage.setItem(_storageKey, JSON.stringify(list2)); } catch(e){}
        }
        function _clearRecent() {
            try { localStorage.removeItem(_storageKey); } catch(e){}
        }

        /* ── grouped dropdown ─────────────────────────────────── */
        var _typeLabels = {
            products:   _s.type_products,
            categories: _s.type_categories,
            entities:   _s.type_entities,
            jobs:       _s.type_jobs,
            brands:     _s.type_brands,
            auctions:   _s.type_auctions
        };
        var _typeOrder = ['products', 'categories', 'entities', 'jobs', 'brands', 'auctions'];

        function _makeSectionHeader(label) {
            var header = document.createElement('li');
            header.setAttribute('role', 'presentation');
            header.style.cssText = 'padding:5px 14px 3px;font-size:.75rem;font-weight:700;color:var(--pub-muted,#888);text-transform:uppercase;letter-spacing:.05em;border-top:1px solid var(--pub-border,#eee);';
            header.innerHTML = label;
            return header;
        }

        function show(data, q) {
            list.innerHTML = '';
            var hasAny = false;

            _typeOrder.forEach(function (type) {
                var items = data[type];
                if (!items || !items.length) return;
                hasAny = true;

                list.appendChild(_makeSectionHeader(_typeLabels[type] || type));

                items.forEach(function (item) {
                    var li = document.createElement('li');
                    li.setAttribute('role', 'option');
                    li.style.cssText = 'padding:8px 14px;cursor:pointer;display:flex;align-items:center;gap:8px;transition:background .15s;';
                    li.innerHTML =
                        '<span style="font-size:1rem;flex-shrink:0;">' + _esc(item.icon || '🔍') + '</span>' +
                        '<span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' +
                            _highlight(item.name, q) +
                        '</span>';
                    li.addEventListener('mousedown', function (e) {
                        e.preventDefault();
                        inp.value = item.name;
                        _saveRecent(item.name);
                        hide();
                        if (item.url) {
                            window.location.href = item.url + '&q=' + encodeURIComponent(item.name);
                        } else {
                            inp.form.submit();
                        }
                    });
                    li.addEventListener('mouseover', function () { this.style.background = 'var(--pub-hover,#f5f5f5)'; });
                    li.addEventListener('mouseout',  function () { this.style.background = ''; });
                    list.appendChild(li);
                });
            });

            if (!hasAny) { hide(); return; }

            // "View all results" footer
            var footer = document.createElement('li');
            footer.setAttribute('role', 'presentation');
            footer.style.cssText = 'padding:8px 14px;border-top:1px solid var(--pub-border,#eee);text-align:center;';
            var a = document.createElement('a');
            a.style.cssText = 'color:var(--pub-primary,#0066cc);font-size:.85rem;text-decoration:none;font-weight:600;';
            a.href = inp.form.action + '?q=' + encodeURIComponent(q) +
                     '&context=' + encodeURIComponent((inp.form.querySelector('[name="context"]') || {}).value || 'all');
            a.textContent = '← ' + _s.view_all;
            a.addEventListener('mousedown', function (e) { e.preventDefault(); window.location.href = this.href; });
            footer.appendChild(a);
            list.appendChild(footer);
            list.hidden = false;
        }

        /* ── show recent + popular when input is focused & empty ── */
        var _popularCache = null;
        function fetchPopular(cb) {
            if (_popularCache !== null) { cb(_popularCache); return; }
            var url = '/api/public/search_suggest?popular=1&lang=<?= urlencode($lang) ?>' +
                      (window.__qzTenantId ? '&tenant_id=' + window.__qzTenantId : '') +
                      (window.__qzEntityId ? '&entity_id=' + window.__qzEntityId : '');
            fetch(url, {credentials: 'include'})
                .then(function(r){ return r.ok ? r.json() : null; })
                .then(function(j){
                    var arr = (j && (j.data || j).popular) ? (j.data || j).popular : [];
                    _popularCache = arr;
                    cb(arr);
                })
                .catch(function(){ _popularCache = []; cb([]); });
        }

        function showRecentAndPopular() {
            var recent = _getRecent();
            fetchPopular(function(popular) {
                list.innerHTML = '';
                var hasAny = false;

                // Recent searches
                if (recent.length > 0) {
                    hasAny = true;
                    // Section header with clear button
                    var rh = document.createElement('li');
                    rh.setAttribute('role', 'presentation');
                    rh.style.cssText = 'padding:5px 14px 3px;font-size:.75rem;font-weight:700;color:var(--pub-muted,#888);text-transform:uppercase;letter-spacing:.05em;display:flex;justify-content:space-between;align-items:center;';
                    var clearHistBtn = document.createElement('button');
                    clearHistBtn.type = 'button';
                    clearHistBtn.textContent = _s.clear_history;
                    clearHistBtn.style.cssText = 'font-size:.7rem;color:var(--pub-primary,#0066cc);background:none;border:none;cursor:pointer;padding:0;';
                    clearHistBtn.addEventListener('mousedown', function(e){
                        e.preventDefault();
                        _clearRecent();
                        hide();
                    });
                    rh.innerHTML = '<span>🕐 ' + _esc(_s.recent) + '</span>';
                    rh.appendChild(clearHistBtn);
                    list.appendChild(rh);

                    recent.forEach(function(q) {
                        var li = document.createElement('li');
                        li.setAttribute('role', 'option');
                        li.style.cssText = 'padding:8px 14px;cursor:pointer;display:flex;align-items:center;gap:8px;transition:background .15s;';
                        li.innerHTML = '<span style="font-size:1rem;flex-shrink:0;">🕐</span>' +
                            '<span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + _esc(q) + '</span>';
                        li.addEventListener('mousedown', function(e){
                            e.preventDefault();
                            inp.value = q;
                            showClear(true);
                            hide();
                            inp.form.submit();
                        });
                        li.addEventListener('mouseover', function(){ this.style.background='var(--pub-hover,#f5f5f5)'; });
                        li.addEventListener('mouseout',  function(){ this.style.background=''; });
                        list.appendChild(li);
                    });
                }

                // Popular searches
                if (popular.length > 0) {
                    hasAny = true;
                    list.appendChild(_makeSectionHeader('🔥 ' + _s.popular));
                    popular.forEach(function(q) {
                        var li = document.createElement('li');
                        li.setAttribute('role', 'option');
                        li.style.cssText = 'padding:8px 14px;cursor:pointer;display:flex;align-items:center;gap:8px;transition:background .15s;';
                        li.innerHTML = '<span style="font-size:1rem;flex-shrink:0;">🔥</span>' +
                            '<span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + _esc(q) + '</span>';
                        li.addEventListener('mousedown', function(e){
                            e.preventDefault();
                            inp.value = q;
                            showClear(true);
                            hide();
                            inp.form.submit();
                        });
                        li.addEventListener('mouseover', function(){ this.style.background='var(--pub-hover,#f5f5f5)'; });
                        li.addEventListener('mouseout',  function(){ this.style.background=''; });
                        list.appendChild(li);
                    });
                }

                if (hasAny) {
                    list.hidden = false;
                }
            });
        }

        /* ── fetch suggestions ────────────────────────────────── */
        function fetchSuggestions(q) {
            var ctx = (inp.form.querySelector('[name="context"]') || {}).value || 'all';
            var url = '/api/public/search_suggest?q=' + encodeURIComponent(q) +
                      '&context=' + encodeURIComponent(ctx) +
                      '&lang=<?= urlencode($lang) ?>' +
                      (window.__qzTenantId ? '&tenant_id=' + window.__qzTenantId : '') +
                      (window.__qzEntityId ? '&entity_id=' + window.__qzEntityId : '');
            fetch(url, {credentials: 'include'})
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (j) {
                    if (!j) return;
                    var d = (j.data) ? j.data : j;
                    // Check if grouped data present
                    if (d.products !== undefined) {
                        show(d, q);
                    } else {
                        // backward-compat flat list
                        var items = d.suggestions || [];
                        if (!items.length) { hide(); return; }
                        var fake = {products:[], categories:[], entities:[], jobs:[]};
                        items.forEach(function(it) {
                            var t = it.type || 'products';
                            if (fake[t]) fake[t].push(it);
                        });
                        show(fake, q);
                    }
                })
                .catch(function () { hide(); });
        }

        /* ── clear button ─────────────────────────────────────── */
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                inp.value = '';
                showClear(false);
                hide();
                window.location.href = window.location.pathname;
            });
        }

        /* ── input events ─────────────────────────────────────── */
        inp.addEventListener('focus', function () {
            if (inp.value.trim() === '') {
                showRecentAndPopular();
            }
        });

        inp.addEventListener('input', function () {
            clearTimeout(timer);
            var q = inp.value.trim();
            showClear(inp.value.length > 0);

            // If input cleared AND we came from a search page, go back to clean page
            if (inp.value === '') {
                // Show recent+popular instead of hiding
                showRecentAndPopular();
                if (window.location.search.indexOf('q=') !== -1) {
                    window.location.href = window.location.pathname;
                }
                return;
            }

            if (q === lastQ) return;
            lastQ = q;
            if (q.length < 2) { hide(); return; }
            timer = setTimeout(function () { fetchSuggestions(q); }, 300);
        });

        /* ── save to recent when form submitted ───────────────── */
        if (inp.form) {
            inp.form.addEventListener('submit', function() {
                _saveRecent(inp.value.trim());
            });
        }

        inp.addEventListener('blur', function () { setTimeout(hide, 200); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') hide(); });
    })();
    </script>

    <!-- Main content area -->
    <main class="pub-main-content">
