<?php
/**
 * Frontend Header Partial — QOOQZ Global Public Interface
 * Requires: frontend/includes/public_context.php (or $GLOBALS['PUB_CONTEXT'])
 * Supports: RTL/LTR, mobile-first, dynamic theme colors
 */

// Resolve context
$_ctx  = $GLOBALS['PUB_CONTEXT'] ?? [];
$lang  = $_ctx['lang'] ?? ($GLOBALS['PUBLIC_UI']['lang'] ?? 'ar');
$dir   = $_ctx['dir']  ?? (in_array($lang, ['ar','fa','ur','he']) ? 'rtl' : 'ltr');
$theme = $_ctx['theme'] ?? [];
$_seo  = $_ctx['seo']  ?? ($GLOBALS['PUBLIC_UI']['seo'] ?? []);
$_user = $_ctx['user'] ?? [];
$_isLoggedIn = !empty($_user['id']);
$_appName = $GLOBALS['PUB_APP_NAME'] ?? 'QOOQZ';
$_pageTitle = $GLOBALS['PUB_PAGE_TITLE'] ?? ($_seo['title'] ?? $_appName);
$_pageDesc  = $GLOBALS['PUB_PAGE_DESC']  ?? ($_seo['description'] ?? '');

// Base path for links — adaptable to subfolder installs
$_basePath = rtrim($GLOBALS['PUB_BASE_PATH'] ?? '/frontend/public', '/');

if (!function_exists('e')) {
    function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
}

// Nav items: label => href
$_navItems = [
    ($lang === 'ar' ? 'الرئيسية'  : 'Home')     => $_basePath . '/index.php',
    ($lang === 'ar' ? 'المنتجات'  : 'Products')  => $_basePath . '/products.php',
    ($lang === 'ar' ? 'الوظائف'   : 'Jobs')      => $_basePath . '/jobs.php',
    ($lang === 'ar' ? 'الكيانات'  : 'Entities')  => $_basePath . '/entities.php',
    ($lang === 'ar' ? 'المستأجرون': 'Tenants')   => $_basePath . '/tenants.php',
];
$_altLang = ($lang === 'ar') ? 'en' : 'ar';
?>
<!doctype html>
<html lang="<?= e($lang) ?>" dir="<?= e($dir) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="<?= e($theme['primary'] ?? '#2d8cf0') ?>">

    <title><?= e($_pageTitle) ?></title>
    <?php if ($_pageDesc): ?><meta name="description" content="<?= e($_pageDesc) ?>"><?php endif; ?>

    <!-- Preconnect for Google Fonts (Cairo for Arabic) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Base styles + Public UI styles -->
    <link rel="stylesheet" href="/frontend/assets/css/main.css">
    <link rel="stylesheet" href="/frontend/assets/css/public.css">

    <!-- Theme data for JS (JSON, injected server-side) -->
    <?php if (!empty($theme)): ?>
    <script type="application/json" id="pubThemeData"><?= json_encode($theme, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?></script>
    <?php endif; ?>
</head>

<body class="pub-body <?= e($dir) ?>">

<!-- =============================================
     HEADER
============================================= -->
<header class="pub-header" role="banner">
    <div class="pub-container pub-header-inner">

        <!-- Logo -->
        <a href="<?= e($_basePath . '/index.php') ?>" class="pub-logo" aria-label="<?= e($_appName) ?>">
            <span class="pub-logo-icon" aria-hidden="true">🌐</span>
            <?= e($_appName) ?>
        </a>

        <!-- Desktop navigation -->
        <nav class="pub-nav" aria-label="<?= $lang === 'ar' ? 'التنقل الرئيسي' : 'Main navigation' ?>">
            <?php foreach ($_navItems as $label => $href): ?>
                <a href="<?= e($href) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>

        <!-- Header actions -->
        <div class="pub-header-actions">
            <!-- Language switcher -->
            <a href="?lang=<?= e($_altLang) ?>" class="pub-lang-btn" aria-label="Switch language">
                <?= $_altLang === 'ar' ? 'عر' : 'EN' ?>
            </a>

            <!-- Login / user -->
            <?php if ($_isLoggedIn): ?>
                <a href="/frontend/profile.php" class="pub-login-btn">
                    <?= e($_user['username'] ?? ($lang === 'ar' ? 'حسابي' : 'Account')) ?>
                </a>
            <?php else: ?>
                <a href="/frontend/login.html" class="pub-login-btn">
                    <?= $lang === 'ar' ? 'تسجيل الدخول' : 'Login' ?>
                </a>
            <?php endif; ?>

            <!-- Hamburger (mobile) -->
            <button class="pub-hamburger" id="pubHamburger"
                    aria-label="<?= $lang === 'ar' ? 'فتح القائمة' : 'Open menu' ?>"
                    aria-expanded="false" aria-controls="pubMobileNav">
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>
</header>

<!-- Mobile nav drawer -->
<div class="pub-mobile-nav" id="pubMobileNav" role="dialog" aria-modal="true"
     aria-label="<?= $lang === 'ar' ? 'قائمة التنقل' : 'Navigation menu' ?>">
    <nav class="pub-mobile-nav-inner">
        <?php foreach ($_navItems as $label => $href): ?>
            <a href="<?= e($href) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
        <hr style="border-color:rgba(255,255,255,0.15);margin:12px 0;">
        <?php if ($_isLoggedIn): ?>
            <a href="/frontend/profile.php"><?= $lang === 'ar' ? 'حسابي' : 'My Account' ?></a>
            <a href="/frontend/logout.php"><?= $lang === 'ar' ? 'تسجيل الخروج' : 'Logout' ?></a>
        <?php else: ?>
            <a href="/frontend/login.html"><?= $lang === 'ar' ? 'تسجيل الدخول' : 'Login' ?></a>
            <a href="/frontend/register.html"><?= $lang === 'ar' ? 'إنشاء حساب' : 'Register' ?></a>
        <?php endif; ?>
    </nav>
</div>

<!-- =============================================
     PAGE CONTENT START
============================================= -->
