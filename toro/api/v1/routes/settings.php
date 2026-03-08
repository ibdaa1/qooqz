<?php
/**
 * TORO — v1/routes/settings.php
 * مسارات الإعدادات
 *
 * Prefixes:
 *   /v1/public/settings  — بدون مصادقة
 *   /v1/admin/settings   — يتطلب Auth + Admin
 *
 * $router هو instance من Shared\Core\Kernel
 */

declare(strict_types=1);

// ── تحميل ملفات Settings ─────────────────────────────────────
$_settPath = __DIR__ . '/../modules/Settings';
require_once $_settPath . '/Contracts/SettingsRepositoryInterface.php';
require_once $_settPath . '/DTO/SettingDTO.php';
require_once $_settPath . '/Validators/SettingsValidator.php';
require_once $_settPath . '/Repositories/PdoSettingsRepository.php';
require_once $_settPath . '/Services/SettingsService.php';
require_once $_settPath . '/Controllers/SettingsController.php';
unset($_settPath);

$_publicMw = ['V1\Middleware\ThrottleMiddleware:120,60'];
$_authAdmin = ['V1\Middleware\AuthMiddleware', 'V1\Middleware\AdminMiddleware'];

// ════════════════════════════════════════════════════════════
// PUBLIC ROUTES  →  /v1/public/settings
// ════════════════════════════════════════════════════════════

foreach (['/v1/settings/public', '/v1/public/settings'] as $_path) {
    $router->addRoute('GET', $_path, 'SettingsController@getPublic', $_publicMw);
}

// ════════════════════════════════════════════════════════════
// ADMIN ROUTES  →  /v1/admin/settings
// ════════════════════════════════════════════════════════════

// GET — قائمة الإعدادات (group, is_public, search, limit, offset)
foreach (['/v1/settings', '/v1/admin/settings'] as $_path) {
    $router->addRoute('GET', $_path, 'SettingsController@index',
        array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:60,60']));
}

// GET — إعدادات مجموعة معينة
foreach (['/v1/settings/group/{group}', '/v1/admin/settings/group/{group}'] as $_path) {
    $router->addRoute('GET', $_path, 'SettingsController@byGroup',
        array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:60,60']));
}

// GET — إعداد واحد بالـ ID
foreach (['/v1/settings/{id}', '/v1/admin/settings/{id}'] as $_path) {
    $router->addRoute('GET', $_path, 'SettingsController@show',
        array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:60,60']));
}

// POST — إنشاء إعداد جديد
foreach (['/v1/settings', '/v1/admin/settings'] as $_path) {
    $router->addRoute('POST', $_path, 'SettingsController@store',
        array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60']));
}

// PUT — تعديل قيمة إعداد
foreach (['/v1/settings/{id}', '/v1/admin/settings/{id}'] as $_path) {
    $router->addRoute('PUT', $_path, 'SettingsController@update',
        array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60']));
}

// DELETE — حذف إعداد
foreach (['/v1/settings/{id}', '/v1/admin/settings/{id}'] as $_path) {
    $router->addRoute('DELETE', $_path, 'SettingsController@destroy',
        array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:20,60']));
}

unset($_publicMw, $_authAdmin, $_path);
