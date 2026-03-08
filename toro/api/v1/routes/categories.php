<?php
/**
 * TORO — v1/routes/categories.php
 * مسارات التصنيفات
 *
 * Prefixes:
 *   /v1/public/categories — بدون مصادقة
 *   /v1/admin/categories  — يتطلب Auth + Admin
 *
 * $router هو instance من Shared\Core\Kernel
 */

declare(strict_types=1);

// ── تحميل ملفات التصنيفات ────────────────────────────────────
$_catPath = __DIR__ . '/../modules/Categories';
require_once $_catPath . '/Contracts/CategoriesRepositoryInterface.php';
require_once $_catPath . '/DTO/CreateCategoryDTO.php';
require_once $_catPath . '/DTO/UpdateCategoryDTO.php';
require_once $_catPath . '/Validators/CategoriesValidator.php';
require_once $_catPath . '/Repositories/PdoCategoriesRepository.php';
require_once $_catPath . '/Services/CategoriesService.php';
require_once $_catPath . '/Controllers/CategoriesController.php';
unset($_catPath);

$_publicMw  = ['V1\Middleware\ThrottleMiddleware:120,60'];
$_authAdmin = ['V1\Middleware\AuthMiddleware', 'V1\Middleware\AdminMiddleware'];

// ════════════════════════════════════════════════════════════
// PUBLIC ROUTES  →  /v1/public/categories
// ════════════════════════════════════════════════════════════

// GET — قائمة التصنيفات
foreach (['/v1/categories', '/v1/public/categories'] as $_path) {
    $router->addRoute('GET', $_path, 'CategoriesController@index', $_publicMw);
}

// GET — تصنيف بالـ ID
foreach (['/v1/categories/{id}', '/v1/public/categories/{id}'] as $_path) {
    $router->addRoute('GET', $_path, 'CategoriesController@show', $_publicMw);
}

// GET — تصنيف بالـ slug
foreach (['/v1/categories/slug/{slug}', '/v1/public/categories/slug/{slug}'] as $_path) {
    $router->addRoute('GET', $_path, 'CategoriesController@showBySlug', $_publicMw);
}

// GET — كل ترجمات التصنيف
foreach (['/v1/categories/{id}/translations', '/v1/public/categories/{id}/translations'] as $_path) {
    $router->addRoute('GET', $_path, 'CategoriesController@translations', $_publicMw);
}

// ════════════════════════════════════════════════════════════
// ADMIN ROUTES  →  /v1/admin/categories
// ════════════════════════════════════════════════════════════

// POST — إنشاء تصنيف
foreach (['/v1/categories', '/v1/admin/categories'] as $_path) {
    $router->addRoute('POST', $_path, 'CategoriesController@store',
        array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60']));
}

// PUT — تعديل تصنيف
foreach (['/v1/categories/{id}', '/v1/admin/categories/{id}'] as $_path) {
    $router->addRoute('PUT', $_path, 'CategoriesController@update',
        array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60']));
}

// DELETE — حذف تصنيف
foreach (['/v1/categories/{id}', '/v1/admin/categories/{id}'] as $_path) {
    $router->addRoute('DELETE', $_path, 'CategoriesController@destroy',
        array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:20,60']));
}

unset($_publicMw, $_authAdmin, $_path);
