<?php
/**
 * TORO — v1/routes/theme.php
 * مسارات الثيم — /v1/theme/*
 *
 * Admin-only routes are in routes/admin.php (/v1/admin/theme)
 * Public routes are in routes/public.php (/v1/public/theme)
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

$_authAdmin = ['V1\Middleware\AuthMiddleware', 'V1\Middleware\AdminMiddleware'];

// GET /v1/theme/css — CSS variables للثيم النشط (بدون مصادقة)
$router->addRoute('GET', '/v1/theme/css',
    'ThemeController@getCss',
    ['V1\Middleware\ThrottleMiddleware:120,60']
);

// GET /v1/theme — قائمة ألوان الثيم
$router->addRoute('GET', '/v1/theme',
    'ThemeController@index',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:60,60'])
);

// GET /v1/theme/{id} — لون واحد بالـ ID
$router->addRoute('GET', '/v1/theme/{id}',
    'ThemeController@show',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:60,60'])
);

// POST /v1/theme — إضافة لون جديد
$router->addRoute('POST', '/v1/theme',
    'ThemeController@store',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60'])
);

// PUT /v1/theme/{id} — تعديل لون
$router->addRoute('PUT', '/v1/theme/{id}',
    'ThemeController@update',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60'])
);

// DELETE /v1/theme/{id} — حذف لون
$router->addRoute('DELETE', '/v1/theme/{id}',
    'ThemeController@destroy',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:20,60'])
);

unset($_authAdmin);
