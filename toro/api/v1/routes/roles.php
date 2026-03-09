<?php
/**
 * TORO — v1/routes/roles.php
 * مسارات الأدوار
 *
 * Prefixes:
 *   /v1/public/roles    — بدون مصادقة (للقراءة فقط)
 *   /v1/admin/roles     — يتطلب Auth + Admin
 *
 * $router هو instance من Shared\Core\Kernel
 */

declare(strict_types=1);

// ── تحميل ملفات الأدوار ────────────────────────────────────
$_rolesPath = __DIR__ . '/../modules/Roles';
require_once $_rolesPath . '/Contracts/RolesRepositoryInterface.php';
require_once $_rolesPath . '/DTO/CreateRoleDTO.php';
require_once $_rolesPath . '/DTO/UpdateRoleDTO.php';
require_once $_rolesPath . '/Validators/RolesValidator.php';
require_once $_rolesPath . '/Repositories/PdoRolesRepository.php';
require_once $_rolesPath . '/Services/RolesService.php';
require_once $_rolesPath . '/Controllers/RolesController.php';
unset($_rolesPath);

$_publicMw  = ['V1\Middleware\ThrottleMiddleware:120,60'];
$_authAdmin = ['V1\Middleware\AuthMiddleware', 'V1\Middleware\AdminMiddleware'];

// ════════════════════════════════════════════════════════════
// PUBLIC ROUTES  →  /v1/public/roles
// ════════════════════════════════════════════════════════════

// GET — قائمة الأدوار (public)
foreach (['/v1/roles', '/v1/public/roles'] as $_path) {
    $router->addRoute('GET', $_path, 'RolesController@index', $_publicMw);
}

// GET — دور بالـ ID (public)
foreach (['/v1/roles/{id}', '/v1/public/roles/{id}'] as $_path) {
    $router->addRoute('GET', $_path, 'RolesController@show', $_publicMw);
}

// GET — دور بالـ slug (public)
foreach (['/v1/roles/slug/{slug}', '/v1/public/roles/slug/{slug}'] as $_path) {
    $router->addRoute('GET', $_path, 'RolesController@showBySlug', $_publicMw);
}

// ════════════════════════════════════════════════════════════
// ADMIN ROUTES  →  /v1/admin/roles
// ════════════════════════════════════════════════════════════

// POST — إنشاء دور
foreach (['/v1/roles', '/v1/admin/roles'] as $_path) {
    $router->addRoute('POST', $_path, 'RolesController@store',
        array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60']));
}

// PUT — تعديل دور
foreach (['/v1/roles/{id}', '/v1/admin/roles/{id}'] as $_path) {
    $router->addRoute('PUT', $_path, 'RolesController@update',
        array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60']));
}

// DELETE — حذف دور
foreach (['/v1/roles/{id}', '/v1/admin/roles/{id}'] as $_path) {
    $router->addRoute('DELETE', $_path, 'RolesController@destroy',
        array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:20,60']));
}

unset($_publicMw, $_authAdmin, $_path);