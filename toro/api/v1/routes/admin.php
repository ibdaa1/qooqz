<?php
/**
 * TORO — v1/routes/admin.php
 * مسارات لوحة الإدارة — /v1/admin/*
 *
 * جميع المسارات هنا تتطلب Auth + AdminMiddleware.
 * هذا الملف مستقل تماماً عن routes/public.php.
 *
 * $router هو instance من Shared\Core\Kernel
 */

declare(strict_types=1);

$_authAdmin = ['V1\Middleware\AuthMiddleware', 'V1\Middleware\AdminMiddleware'];

// ════════════════════════════════════════════════════════════
// AUTH  →  /v1/admin/auth/*
// ════════════════════════════════════════════════════════════

$router->addRoute('POST', '/v1/admin/auth/logout',
    'AuthController@logout',
    ['V1\Middleware\AuthMiddleware']
);

$router->addRoute('GET', '/v1/admin/auth/me',
    'AuthController@me',
    ['V1\Middleware\AuthMiddleware']
);

$router->addRoute('POST', '/v1/admin/auth/change-password',
    'AuthController@changePassword',
    ['V1\Middleware\AuthMiddleware', 'V1\Middleware\ThrottleMiddleware:5,60']
);

// ════════════════════════════════════════════════════════════
// SETTINGS  →  /v1/admin/settings/*
// ════════════════════════════════════════════════════════════

// GET — قائمة الإعدادات
$router->addRoute('GET', '/v1/admin/settings',
    'SettingsController@index',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:60,60'])
);

// GET — إعدادات مجموعة معينة
$router->addRoute('GET', '/v1/admin/settings/group/{group}',
    'SettingsController@byGroup',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:60,60'])
);

// GET — إعداد واحد بالـ ID
$router->addRoute('GET', '/v1/admin/settings/{id}',
    'SettingsController@show',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:60,60'])
);

// POST — إنشاء إعداد جديد
$router->addRoute('POST', '/v1/admin/settings',
    'SettingsController@store',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60'])
);

// PUT — تعديل قيمة إعداد
$router->addRoute('PUT', '/v1/admin/settings/{id}',
    'SettingsController@update',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60'])
);

// DELETE — حذف إعداد
$router->addRoute('DELETE', '/v1/admin/settings/{id}',
    'SettingsController@destroy',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:20,60'])
);

// ════════════════════════════════════════════════════════════
// THEME  →  /v1/admin/theme/*
// ════════════════════════════════════════════════════════════

// GET — قائمة ألوان الثيم
$router->addRoute('GET', '/v1/admin/theme',
    'ThemeController@index',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:60,60'])
);

// GET — لون واحد بالـ ID
$router->addRoute('GET', '/v1/admin/theme/{id}',
    'ThemeController@show',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:60,60'])
);

// POST — إضافة لون جديد
$router->addRoute('POST', '/v1/admin/theme',
    'ThemeController@store',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60'])
);

// PUT — تعديل لون
$router->addRoute('PUT', '/v1/admin/theme/{id}',
    'ThemeController@update',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60'])
);

// DELETE — حذف لون
$router->addRoute('DELETE', '/v1/admin/theme/{id}',
    'ThemeController@destroy',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:20,60'])
);

// ════════════════════════════════════════════════════════════
// CATEGORIES  →  /v1/admin/categories/*
// ════════════════════════════════════════════════════════════

// POST — إنشاء تصنيف
$router->addRoute('POST', '/v1/admin/categories',
    'CategoriesController@store',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60'])
);

// PUT — تعديل تصنيف
$router->addRoute('PUT', '/v1/admin/categories/{id}',
    'CategoriesController@update',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60'])
);

// DELETE — حذف تصنيف
$router->addRoute('DELETE', '/v1/admin/categories/{id}',
    'CategoriesController@destroy',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:20,60'])
);

// ════════════════════════════════════════════════════════════
// BRANDS  →  /v1/admin/brands/*
// ════════════════════════════════════════════════════════════

// POST — إنشاء ماركة
$router->addRoute('POST', '/v1/admin/brands',
    'BrandsController@store',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60'])
);

// PUT — تعديل ماركة
$router->addRoute('PUT', '/v1/admin/brands/{id}',
    'BrandsController@update',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60'])
);

// DELETE — حذف ماركة
$router->addRoute('DELETE', '/v1/admin/brands/{id}',
    'BrandsController@destroy',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:20,60'])
);

unset($_authAdmin);
