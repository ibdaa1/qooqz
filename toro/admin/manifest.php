<?php
/**
 * TORO Admin — manifest.php
 *
 * Dynamic PWA Web App Manifest.
 * Reads brand name and theme colour from the database so the manifest is
 * always consistent with the live design system.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/db_helpers.php';

$settings    = adminGetSettings();
$brandName   = $settings['site_name'] ?? 'TORO';
$shortName   = mb_substr($brandName, 0, 12, 'UTF-8');
$description = $settings['site_description'] ?? ($brandName . ' Admin Panel');

// Derive theme_color from DB theme colors (--clr-primary), fall back to indigo
$themeColor  = '#6366f1';
$bgColor     = '#0f172a';
$themeCss    = adminGetThemeCss();
if ($themeCss && preg_match('/--clr-primary\s*:\s*([^;]+);/', $themeCss, $m)) {
    $themeColor = trim($m[1]);
}
if ($themeCss && preg_match('/--clr-bg\s*:\s*([^;]+);/', $themeCss, $m)) {
    $bgColor = trim($m[1]);
}

$manifest = [
    'name'             => $brandName . ' Admin',
    'short_name'       => $shortName,
    'description'      => $description,
    'start_url'        => '/toro/admin/index.php',
    'scope'            => '/toro/admin/',
    'display'          => 'standalone',
    'orientation'      => 'any',
    'theme_color'      => $themeColor,
    'background_color' => $bgColor,
    'lang'             => 'ar',
    'dir'              => 'rtl',
    'categories'       => ['business', 'productivity'],
    'icons'            => [
        [
            'src'     => '/toro/admin/assets/icons/icon-192.png',
            'sizes'   => '192x192',
            'type'    => 'image/png',
            'purpose' => 'any maskable',
        ],
        [
            'src'     => '/toro/admin/assets/icons/icon-512.png',
            'sizes'   => '512x512',
            'type'    => 'image/png',
            'purpose' => 'any maskable',
        ],
    ],
    'screenshots' => [],
    'shortcuts' => [
        [
            'name'        => 'Dashboard',
            'url'         => '/toro/admin/index.php',
            'description' => 'لوحة التحكم',
        ],
        [
            'name'        => 'Products',
            'url'         => '/toro/admin/pages/products.php',
            'description' => 'إدارة المنتجات',
        ],
    ],
];

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=300');
echo json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
