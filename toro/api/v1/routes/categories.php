<?php
/**
 * TORO — v1/routes/categories.php
 * مسارات التصنيفات — /v1/categories/*
 *
 * Admin-only routes are in routes/admin.php (/v1/admin/categories)
 * Public routes are in routes/public.php (/v1/public/categories)
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

// GET /v1/categories — قائمة التصنيفات (عام)
$router->addRoute('GET', '/v1/categories',
    'CategoriesController@index',
    ['V1\Middleware\ThrottleMiddleware:120,60']
);

// GET /v1/categories/{id} — تصنيف بالـ ID (عام)
$router->addRoute('GET', '/v1/categories/{id}',
    'CategoriesController@show',
    ['V1\Middleware\ThrottleMiddleware:120,60']
);

// GET /v1/categories/slug/{slug} — تصنيف بالـ slug (عام)
$router->addRoute('GET', '/v1/categories/slug/{slug}',
    'CategoriesController@showBySlug',
    ['V1\Middleware\ThrottleMiddleware:120,60']
);

// GET /v1/categories/{id}/translations — كل ترجمات التصنيف (عام)
$router->addRoute('GET', '/v1/categories/{id}/translations',
    'CategoriesController@translations',
    ['V1\Middleware\ThrottleMiddleware:120,60']
);

$_authAdmin = ['V1\Middleware\AuthMiddleware', 'V1\Middleware\AdminMiddleware'];

// POST /v1/categories — إنشاء تصنيف
$router->addRoute('POST', '/v1/categories',
    'CategoriesController@store',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60'])
);

// PUT /v1/categories/{id} — تعديل تصنيف
$router->addRoute('PUT', '/v1/categories/{id}',
    'CategoriesController@update',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60'])
);

// DELETE /v1/categories/{id} — حذف تصنيف
$router->addRoute('DELETE', '/v1/categories/{id}',
    'CategoriesController@destroy',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:20,60'])
);

unset($_authAdmin);
