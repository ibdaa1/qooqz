<?php
/**
 * TORO — v1/routes/role-permissions.php
 * مسارات صلاحيات الأدوار (الجدول الوسيط)
 *
 * Prefixes:
 *   /v1/public/role-permissions    — بدون مصادقة (للقراءة فقط)
 *   /v1/admin/role-permissions     — يتطلب Auth + Admin
 *
 * $router هو instance من Shared\Core\Kernel
 */

declare(strict_types=1);

// ── تحميل ملفات صلاحيات الأدوار ─────────────────────────────
$_rpPath = __DIR__ . '/../modules/RolePermissions';
require_once $_rpPath . '/Contracts/RolePermissionsRepositoryInterface.php';
require_once $_rpPath . '/DTO/AttachPermissionsDTO.php';
require_once $_rpPath . '/DTO/SyncPermissionsDTO.php';
require_once $_rpPath . '/Validators/RolePermissionsValidator.php';
require_once $_rpPath . '/Repositories/PdoRolePermissionsRepository.php';
require_once $_rpPath . '/Services/RolePermissionsService.php';
require_once $_rpPath . '/Controllers/RolePermissionsController.php';
unset($_rpPath);

$_publicMw  = ['V1\Middleware\ThrottleMiddleware:120,60'];
$_authAdmin = ['V1\Middleware\AuthMiddleware', 'V1\Middleware\AdminMiddleware'];

// ════════════════════════════════════════════════════════════
// PUBLIC ROUTES  →  /v1/public/role-permissions
// ════════════════════════════════════════════════════════════

// GET — صلاحيات دور معين (public)
foreach (['/v1/role-permissions/role/{roleId}', '/v1/public/role-permissions/role/{roleId}'] as $_path) {
    $router->addRoute('GET', $_path, 'RolePermissionsController@getPermissionsByRole', $_publicMw);
}

// GET — أدوار صلاحية معينة (public)
foreach (['/v1/role-permissions/permission/{permissionId}', '/v1/public/role-permissions/permission/{permissionId}'] as $_path) {
    $router->addRoute('GET', $_path, 'RolePermissionsController@getRolesByPermission', $_publicMw);
}

// GET — التحقق من وجود علاقة محددة (public)
foreach (['/v1/role-permissions/exists', '/v1/public/role-permissions/exists'] as $_path) {
    $router->addRoute('GET', $_path, 'RolePermissionsController@exists', $_publicMw);
}

// ════════════════════════════════════════════════════════════
// ADMIN ROUTES  →  /v1/admin/role-permissions
// ════════════════════════════════════════════════════════════

// POST — إرفاق صلاحيات بدور (إضافة بدون مسح القديم)
foreach (['/v1/role-permissions/attach', '/v1/admin/role-permissions/attach'] as $_path) {
    $router->addRoute('POST', $_path, 'RolePermissionsController@attach',
        array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60']));
}

// POST — فصل صلاحيات عن دور (حذف مجموعة)
foreach (['/v1/role-permissions/detach', '/v1/admin/role-permissions/detach'] as $_path) {
    $router->addRoute('POST', $_path, 'RolePermissionsController@detach',
        array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60']));
}

// POST — مزامنة صلاحيات دور (استبدال كامل)
foreach (['/v1/role-permissions/sync', '/v1/admin/role-permissions/sync'] as $_path) {
    $router->addRoute('POST', $_path, 'RolePermissionsController@sync',
        array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60']));
}

// DELETE — حذف علاقة محددة (دور + صلاحية)
foreach (['/v1/role-permissions', '/v1/admin/role-permissions'] as $_path) {
    $router->addRoute('DELETE', $_path, 'RolePermissionsController@destroy',
        array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:20,60']));
}

unset($_publicMw, $_authAdmin, $_path);