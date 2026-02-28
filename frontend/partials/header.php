<?php
/**
 * Frontend Header Partial — QOOQZ Global Public Interface
 * Requires: frontend/includes/public_context.php (or $GLOBALS['PUB_CONTEXT'])
 * Supports: RTL/LTR, mobile-first, dynamic theme colors, all-language t() system
 */

// Resolve context
$_ctx  = $GLOBALS['PUB_CONTEXT'] ?? [];
$lang  = $_ctx['lang'] ?? 'ar';
$dir   = $_ctx['dir']  ?? 'rtl';
$theme = $_ctx['theme'] ?? [];
$_seo  = $_ctx['seo']  ?? [];
$_user = $_ctx['user'] ?? [];
$_isLoggedIn = !empty($_user['id']);
$_appName   = $GLOBALS['PUB_APP_NAME']  ?? 'QOOQZ';
$_pageTitle = $GLOBALS['PUB_PAGE_TITLE'] ?? ($_seo['title'] ?? $_appName);
$_pageDesc  = $GLOBALS['PUB_PAGE_DESC']  ?? ($_seo['description'] ?? '');
$_basePath  = rtrim($GLOBALS['PUB_BASE_PATH'] ?? '/frontend/public', '/');

if (!function_exists('e')) {
    function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
}
if (!function_exists('t')) {
    function t(string $key, array $r = []): string { return $key; }
}

// Nav items using translation system
$_navItems = [
    t('nav.home')       => $_basePath . '/index.php',
    t('nav.products')   => $_basePath . '/products.php',
    t('nav.categories') => $_basePath . '/categories.php',
    t('nav.offers')     => $_basePath . '/discounts.php',
    t('nav.jobs')       => $_basePath . '/jobs.php',
    t('nav.entities')   => $_basePath . '/entities.php',
    t('nav.tenants')    => $_basePath . '/tenants.php',
];
$_cartUrl   = $_basePath . '/cart.php';
$_cartLabel = e(t('nav.cart'));

// Font: Cairo for Arabic/RTL, Inter for LTR
$_fontUrl = $dir === 'rtl'
    ? 'https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap'
    : 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap';
?>
<!doctype html>
<html lang="<?= e($lang) ?>" dir="<?= e($dir) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="<?= e($theme['primary'] ?? '#FF0000') ?>">

    <title><?= e($_pageTitle) ?></title>
    <?php if ($_pageDesc): ?><meta name="description" content="<?= e($_pageDesc) ?>"><?php endif; ?>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="<?= e($_fontUrl) ?>" rel="stylesheet">

    <!-- Public UI styles — ?v= cache-busting: forces browser to re-download on every deploy -->
    <?php
    $_pubCssV = @filemtime(FRONTEND_BASE . '/assets/css/public.css') ?: '1';
    $_pubHjV  = @filemtime(FRONTEND_BASE . '/assets/js/homepage-engine.js') ?: '1';
    ?>
    <link rel="stylesheet" href="/frontend/assets/css/public.css?v=<?= $_pubCssV ?>">
    <!-- Homepage section engine (loaded early so init() can be called inline) -->
    <script src="/frontend/assets/js/homepage-engine.js?v=<?= $_pubHjV ?>"></script>

    <!-- Theme: inject CSS variables from DB color_settings -->
    <?php if (!empty($theme)): ?>
    <style id="pubThemeVars">
    :root {
        --pub-primary:    <?= e($theme['primary']    ?? '#FF0000') ?>;
        --pub-secondary:  <?= e($theme['secondary']  ?? '#10B981') ?>;
        --pub-accent:     <?= e($theme['accent']     ?? '#F59E0B') ?>;
        --pub-bg:         <?= e($theme['background'] ?? '#0d0d0d') ?>;
        --pub-surface:    <?= e($theme['surface']    ?? '#1a1a2e') ?>;
        --pub-text:       <?= e($theme['text']       ?? '#FFFFFF') ?>;
        --pub-muted:      <?= e($theme['text_muted'] ?? '#B0B0B0') ?>;
        --pub-border:     <?= e($theme['border']     ?? '#333333') ?>;
        --pub-header-bg:  <?= e($theme['header_bg']  ?? $theme['primary'] ?? '#FF0000') ?>;
        --pub-footer-bg:  <?= e($theme['footer_bg']  ?? '#1a1a2e') ?>;
    }
    .pub-header  { background: var(--pub-header-bg) !important; }
    .pub-hero    { background: linear-gradient(135deg, var(--pub-header-bg) 0%, var(--pub-accent) 100%) !important; }
    </style>
    <?php if (!empty($theme['generated_css'])): ?>
    <style id="pubDynamicTheme"><?= $theme['generated_css'] ?></style>
    <?php endif; ?>
    <script type="application/json" id="pubThemeData"><?= json_encode($theme, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?></script>
    <?php endif; ?>
    <?php
    // Inject PHP session user so JS can show username even when localStorage is empty
    // (happens when user logged in via admin panel instead of frontend login.php).
    // Only safe non-sensitive fields are exposed: id, name, username, email.
    $_phpUser = null;
    if ($_isLoggedIn && !empty($_user['id'])) {
        $_phpUser = [
            'id'       => (int)$_user['id'],
            'name'     => $_user['name'] ?? $_user['username'] ?? '',
            'username' => $_user['username'] ?? '',
            'email'    => $_user['email']    ?? '',
        ];
    }
    ?>
    <script>window.pubSessionUser = <?= json_encode($_phpUser, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
    window.PUB_TENANT_ID = <?= (int)($ctx['tenant_id'] ?? 1) ?>;
    window.PUB_LANG = <?= json_encode($ctx['lang'] ?? 'en', JSON_HEX_TAG) ?>;
    </script>
</head>

<body class="pub-body <?= e($dir) ?>">

<!-- =============================================
     HEADER
============================================= -->
<header class="pub-header" role="banner">
    <div class="pub-container pub-header-inner">

        <!-- Logo -->
        <a href="<?= e($_basePath . '/index.php') ?>" class="pub-logo" aria-label="<?= e($_appName) ?>">
            <?php
            $_logoUrl = $theme['logo_url'] ?? '';
            // Also check static file fallback: /frontend/assets/images/logo.{png,svg,webp}
            if (empty($_logoUrl)) {
                $_baseImgDir = FRONTEND_BASE . '/assets/images/';
                foreach (['logo.png', 'logo.svg', 'logo.webp'] as $_lf) {
                    if (@file_exists($_baseImgDir . $_lf)) {
                        $_logoUrl = '/frontend/assets/images/' . $_lf;
                        break;
                    }
                }
                unset($_baseImgDir, $_lf);
            }
            if (!empty($_logoUrl)):
            ?>
                <img src="<?= e($_logoUrl) ?>" alt="<?= e($_appName) ?>" class="pub-logo-img"
                     style="width:auto;vertical-align:middle;object-fit:contain;flex-shrink:0;">
                <span class="pub-logo-name" style="margin-inline-start:4px;font-weight:700;letter-spacing:0.5px;"><?= e($_appName) ?></span>
            <?php else: ?>
                <span class="pub-logo-icon" aria-hidden="true">🌐</span>
                <?= e($_appName) ?>
            <?php endif; ?>
        </a>

        <!-- Desktop navigation -->
        <nav class="pub-nav" aria-label="<?= e(t('nav.menu_open')) ?>">
            <?php foreach ($_navItems as $label => $href): ?>
                <a href="<?= e($href) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
            <!-- Cart link with live count badge -->
            <a href="<?= e($_cartUrl) ?>" class="pub-cart-nav-link" style="position:relative;display:inline-flex;align-items:center;gap:4px;">
                🛒 <?= $_cartLabel ?>
                <span id="pubCartCount"
                      style="display:none;background:var(--pub-accent,#F59E0B);color:#000;
                             border-radius:50%;min-width:18px;height:18px;padding:0 4px;
                             font-size:0.7rem;font-weight:800;line-height:18px;
                             text-align:center;vertical-align:middle;"></span>
            </a>
            <!-- Wishlist link with live count badge -->
            <a href="/frontend/public/wishlist.php" class="pub-wishlist-badge" style="position:relative;display:inline-flex;align-items:center;gap:4px;" title="<?= e(t('nav.wishlist')) ?>">
                ♡ <?= e(t('nav.wishlist')) ?>
                <span id="pubWishlistCount"></span>
            </a>
        </nav>

        <!-- Header actions -->
        <div class="pub-header-actions">
            <!-- Login / user — no language switcher button (auto-detected) -->
            <?php if ($_isLoggedIn): ?>
                <a href="/frontend/profile.php" class="pub-login-btn" style="margin-inline-end:6px;">
                    <?= e($_user['name'] ?? $_user['username'] ?? t('nav.account')) ?>
                </a>
                <a href="/frontend/logout.php"
                   class="pub-btn pub-btn--ghost pub-btn--sm"
                   style="font-size:0.8rem;padding:4px 10px;"><?= e(t('nav.logout')) ?></a>
            <?php else: ?>
                <a href="/frontend/login.php" class="pub-login-btn"><?= e(t('nav.login')) ?></a>
            <?php endif; ?>

            <!-- Hamburger (mobile) -->
            <button class="pub-hamburger" id="pubHamburger"
                    aria-label="<?= e(t('nav.menu_open')) ?>"
                    aria-expanded="false" aria-controls="pubMobileNav">
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>
</header>

<!-- Mobile nav drawer -->
<div class="pub-mobile-nav" id="pubMobileNav" role="dialog" aria-modal="true"
     aria-label="<?= e(t('nav.menu_open')) ?>">
    <nav class="pub-mobile-nav-inner">
        <?php foreach ($_navItems as $label => $href): ?>
            <a href="<?= e($href) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
        <!-- Cart link -->
        <a href="<?= e($_cartUrl) ?>">
            🛒 <?= $_cartLabel ?>
            <span id="pubCartCountMobile"
                  style="display:none;background:var(--pub-accent,#F59E0B);color:#000;
                         border-radius:50%;min-width:18px;height:18px;padding:0 4px;
                         font-size:0.7rem;font-weight:800;line-height:18px;
                         text-align:center;vertical-align:middle;margin-inline-start:4px;"></span>
        </a>
        <!-- Wishlist link -->
        <a href="/frontend/public/wishlist.php">
            ♡ <?= e(t('nav.wishlist')) ?>
            <span id="pubWishlistCountMobile"></span>
        </a>
        <hr style="border-color:rgba(255,255,255,0.15);margin:12px 0;">
        <?php if ($_isLoggedIn): ?>
            <a href="/frontend/profile.php">👤 <?= e($_user['name'] ?? $_user['username'] ?? t('nav.account')) ?></a>
            <a href="/frontend/logout.php"><?= e(t('nav.logout')) ?></a>
        <?php else: ?>
            <a href="/frontend/login.php"><?= e(t('nav.login')) ?></a>
            <a href="/frontend/login.php?tab=register"><?= e(t('nav.register')) ?></a>
        <?php endif; ?>
    </nav>
</div>

<!-- =============================================
     PAGE CONTENT START
============================================= -->
