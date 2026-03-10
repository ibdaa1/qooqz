<?php
/**
 * TORO — v1/routes/brands.php
 * مسارات الماركات — /v1/brands/*
 *
 * Admin-only routes are in routes/admin.php (/v1/admin/brands)
 * Public routes are in routes/public.php (/v1/public/brands)
 *
 * $router هو instance من Shared\Core\Kernel
 */

declare(strict_types=1);

// ── تحميل ملفات الماركات ──────────────────────────────────────
$_brandPath = __DIR__ . '/../modules/Brands';
require_once $_brandPath . '/Contracts/BrandsRepositoryInterface.php';
require_once $_brandPath . '/DTO/CreateBrandDTO.php';
require_once $_brandPath . '/DTO/UpdateBrandDTO.php';
require_once $_brandPath . '/Validators/BrandsValidator.php';
require_once $_brandPath . '/Repositories/PdoBrandsRepository.php';
require_once $_brandPath . '/Services/BrandsService.php';
require_once $_brandPath . '/Controllers/BrandsController.php';
unset($_brandPath);

// GET /v1/brands — قائمة الماركات (عام)
$router->addRoute('GET', '/v1/brands',
    'BrandsController@index',
    ['V1\Middleware\ThrottleMiddleware:120,60']
);

// GET /v1/brands/{id} — ماركة بالـ ID (عام)
$router->addRoute('GET', '/v1/brands/{id}',
    'BrandsController@show',
    ['V1\Middleware\ThrottleMiddleware:120,60']
);

// GET /v1/brands/slug/{slug} — ماركة بالـ slug (عام)
$router->addRoute('GET', '/v1/brands/slug/{slug}',
    'BrandsController@showBySlug',
    ['V1\Middleware\ThrottleMiddleware:120,60']
);

// GET /v1/brands/{id}/translations — كل ترجمات الماركة (عام)
$router->addRoute('GET', '/v1/brands/{id}/translations',
    'BrandsController@translations',
    ['V1\Middleware\ThrottleMiddleware:120,60']
);

$_authAdmin = ['V1\Middleware\AuthMiddleware', 'V1\Middleware\AdminMiddleware'];

// POST /v1/brands — إنشاء ماركة
$router->addRoute('POST', '/v1/brands',
    'BrandsController@store',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60'])
);

// PUT /v1/brands/{id} — تعديل ماركة
$router->addRoute('PUT', '/v1/brands/{id}',
    'BrandsController@update',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60'])
);

// DELETE /v1/brands/{id} — حذف ماركة
$router->addRoute('DELETE', '/v1/brands/{id}',
    'BrandsController@destroy',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:20,60'])
);

unset($_authAdmin);
