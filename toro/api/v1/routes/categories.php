<?php
/**
 * TORO — v1/routes/categories.php
 * مسارات التصنيفات
 *
 * $router هو instance من Shared\Core\Kernel
 * يُحقن من bootstrap عبر loadRoutes()
 */

declare(strict_types=1);

// ── تحميل ملفات التصنيفات ────────────────────────────────────
$_catPath = __DIR__ . '/../Modules/Categories';
require_once $_catPath . '/Contracts/CategoriesRepositoryInterface.php';
require_once $_catPath . '/DTO/CreateCategoryDTO.php';
require_once $_catPath . '/DTO/UpdateCategoryDTO.php';
require_once $_catPath . '/Validators/CategoriesValidator.php';
require_once $_catPath . '/Repositories/PdoCategoriesRepository.php';
require_once $_catPath . '/Services/CategoriesService.php';
require_once $_catPath . '/Controllers/CategoriesController.php';
unset($_catPath);

// ── قراءة التصنيفات (عام) ────────────────────────────────────

// GET /v1/categories — قائمة التصنيفات
$router->addRoute('GET', '/v1/categories',
    'CategoriesController@index',
    ['V1\Middleware\ThrottleMiddleware:120,60']
);

// GET /v1/categories/{id} — تصنيف بالـ ID
$router->addRoute('GET', '/v1/categories/{id}',
    'CategoriesController@show',
    ['V1\Middleware\ThrottleMiddleware:120,60']
);

// GET /v1/categories/slug/{slug} — تصنيف بالـ slug
$router->addRoute('GET', '/v1/categories/slug/{slug}',
    'CategoriesController@showBySlug',
    ['V1\Middleware\ThrottleMiddleware:120,60']
);

// GET /v1/categories/{id}/translations — كل ترجمات التصنيف
$router->addRoute('GET', '/v1/categories/{id}/translations',
    'CategoriesController@translations',
    ['V1\Middleware\ThrottleMiddleware:120,60']
);

// ── إدارة التصنيفات (أدمن فقط) ──────────────────────────────

// POST /v1/categories — إنشاء تصنيف
$router->addRoute('POST', '/v1/categories',
    'CategoriesController@store',
    ['V1\Middleware\AuthMiddleware', 'V1\Middleware\AdminMiddleware', 'V1\Middleware\ThrottleMiddleware:30,60']
);

// PUT /v1/categories/{id} — تعديل تصنيف
$router->addRoute('PUT', '/v1/categories/{id}',
    'CategoriesController@update',
    ['V1\Middleware\AuthMiddleware', 'V1\Middleware\AdminMiddleware', 'V1\Middleware\ThrottleMiddleware:30,60']
);

// DELETE /v1/categories/{id} — حذف تصنيف
$router->addRoute('DELETE', '/v1/categories/{id}',
    'CategoriesController@destroy',
    ['V1\Middleware\AuthMiddleware', 'V1\Middleware\AdminMiddleware', 'V1\Middleware\ThrottleMiddleware:20,60']
);