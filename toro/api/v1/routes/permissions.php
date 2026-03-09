<?php
/**
 * TORO — v1/routes/permissions.php
 * مسارات الصلاحيات
 *
 * Prefixes:
 *   /v1/public/permissions — بدون مصادقة (للقراءة فقط)
 *   /v1/admin/permissions  — يتطلب Auth + Admin
 *
 * $router هو instance من Shared\Core\Kernel
 */

declare(strict_types=1);

// ── تحميل ملفات الصلاحيات ────────────────────────────────────
$_permsPath = __DIR__ . '/../modules/Permissions';
require_once $_permsPath . '/Contracts/PermissionsRepositoryInterface.php';
require_once $_permsPath . '/DTO/CreatePermissionDTO.php';
require_once $_permsPath . '/DTO/UpdatePermissionDTO.php';
require_once $_permsPath . '/Validators/PermissionsValidator.php';
require_once $_permsPath . '/Repositories/PdoPermissionsRepository.php';
require_once $_permsPath . '/Services/PermissionsService.php';
require_once $_permsPath . '/Controllers/PermissionsController.php';
unset($_permsPath);

$_publicMw  = ['V1\Middleware\ThrottleMiddleware:120,60'];
$_authAdmin = ['V1\Middleware\AuthMiddleware', 'V1\Middleware\AdminMiddleware'];

// ════════════════════════════════════════════════════════════
// PUBLIC ROUTES  →  /v1/public/permissions
// ════════════════════════════════════════════════════════════

// GET — قائمة الصلاحيات (public) مع إمكانية التصفية بالمجموعة
foreach (['/v1/permissions', '/v1/public/permissions'] as $_path) {
    $router->addRoute('GET', $_path, 'PermissionsController@index', $_publicMw);
}

// GET — صلاحية بالـ ID (public)
foreach (['/v1/permissions/{id}', '/v1/public/permissions/{id}'] as $_path) {
    $router->addRoute('GET', $_path, 'PermissionsController@show', $_publicMw);
}

// GET — صلاحية بالـ slug (public)
foreach (['/v1/permissions/slug/{slug}', '/v1/public/permissions/slug/{slug}'] as $_path) {
    $router->addRoute('GET', $_path, 'PermissionsController@showBySlug', $_publicMw);
}

// GET — الصلاحيات مجمعة حسب المجموعة (public)
foreach (['/v1/permissions/grouped', '/v1/public/permissions/grouped'] as $_path) {
    $router->addRoute('GET', $_path, 'PermissionsController@grouped', $_publicMw);
}

// ════════════════════════════════════════════════════════════
// ADMIN ROUTES  →  /v1/admin/permissions
// ════════════════════════════════════════════════════════════

// POST — إنشاء صلاحية
foreach (['/v1/permissions', '/v1/admin/permissions'] as $_path) {
    $router->addRoute('POST', $_path, 'PermissionsController@store',
        array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60']));
}

// PUT — تعديل صلاحية
foreach (['/v1/permissions/{id}', '/v1/admin/permissions/{id}'] as $_path) {
    $router->addRoute('PUT', $_path, 'PermissionsController@update',
        array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60']));
}

// DELETE — حذف صلاحية
foreach (['/v1/permissions/{id}', '/v1/admin/permissions/{id}'] as $_path) {
    $router->addRoute('DELETE', $_path, 'PermissionsController@destroy',
        array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:20,60']));
}

unset($_publicMw, $_authAdmin, $_path);