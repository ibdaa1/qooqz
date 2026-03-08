<?php
/**
 * TORO — v1/routes/theme.php
 * مسارات الثيم
 *
 * Prefixes:
 *   /v1/public/theme  — بدون مصادقة
 *   /v1/admin/theme   — يتطلب Auth + Admin
 *
 * $router هو instance من Shared\Core\Kernel
 */

declare(strict_types=1);

// ── تحميل ملفات Theme ────────────────────────────────────────
$_themePath = __DIR__ . '/../modules/Theme';
require_once $_themePath . '/Contracts/ThemeRepositoryInterface.php';
require_once $_themePath . '/DTO/CreateThemeDTO.php';
require_once $_themePath . '/DTO/UpdateThemeDTO.php';
require_once $_themePath . '/Validators/ThemeValidator.php';
require_once $_themePath . '/Repositories/PdoThemeRepository.php';
require_once $_themePath . '/Services/ThemeService.php';
require_once $_themePath . '/Controllers/ThemeController.php';
unset($_themePath);

$_publicMw  = ['V1\Middleware\ThrottleMiddleware:120,60'];
$_authAdmin = ['V1\Middleware\AuthMiddleware', 'V1\Middleware\AdminMiddleware'];

// ════════════════════════════════════════════════════════════
// PUBLIC ROUTES  →  /v1/public/theme/css
// ════════════════════════════════════════════════════════════

// CSS variables للثيم النشط
foreach (['/v1/theme/css', '/v1/public/theme/css'] as $_path) {
    $router->addRoute('GET', $_path, 'ThemeController@getCss', $_publicMw);
}

// ════════════════════════════════════════════════════════════
// ADMIN ROUTES  →  /v1/admin/theme
// ════════════════════════════════════════════════════════════

// GET — قائمة ألوان الثيم (is_active, search, limit, offset)
foreach (['/v1/theme', '/v1/admin/theme'] as $_path) {
    $router->addRoute('GET', $_path, 'ThemeController@index',
        array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:60,60']));
}

// GET — لون واحد بالـ ID
foreach (['/v1/theme/{id}', '/v1/admin/theme/{id}'] as $_path) {
    $router->addRoute('GET', $_path, 'ThemeController@show',
        array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:60,60']));
}

// POST — إضافة لون جديد
foreach (['/v1/theme', '/v1/admin/theme'] as $_path) {
    $router->addRoute('POST', $_path, 'ThemeController@store',
        array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60']));
}

// PUT — تعديل لون
foreach (['/v1/theme/{id}', '/v1/admin/theme/{id}'] as $_path) {
    $router->addRoute('PUT', $_path, 'ThemeController@update',
        array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60']));
}

// DELETE — حذف لون
foreach (['/v1/theme/{id}', '/v1/admin/theme/{id}'] as $_path) {
    $router->addRoute('DELETE', $_path, 'ThemeController@destroy',
        array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:20,60']));
}

unset($_publicMw, $_authAdmin, $_path);
