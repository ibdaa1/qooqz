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

// ════════════════════════════════════════════════════════════
// ATTRIBUTES  →  /v1/admin/attributes/*
// ════════════════════════════════════════════════════════════

// POST — إنشاء سمة
$router->addRoute('POST', '/v1/admin/attributes',
    'AttributesController@store',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60'])
);

// PUT — تعديل سمة
$router->addRoute('PUT', '/v1/admin/attributes/{id}',
    'AttributesController@update',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60'])
);

// DELETE — حذف سمة
$router->addRoute('DELETE', '/v1/admin/attributes/{id}',
    'AttributesController@destroy',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:20,60'])
);

// ════════════════════════════════════════════════════════════
// ATTRIBUTE VALUES  →  /v1/admin/attribute-values/*
// ════════════════════════════════════════════════════════════

// POST — إنشاء قيمة سمة
$router->addRoute('POST', '/v1/admin/attribute-values',
    'AttributeValuesController@store',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60'])
);

// PUT — تعديل قيمة سمة
$router->addRoute('PUT', '/v1/admin/attribute-values/{id}',
    'AttributeValuesController@update',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60'])
);

// DELETE — حذف قيمة سمة
$router->addRoute('DELETE', '/v1/admin/attribute-values/{id}',
    'AttributeValuesController@destroy',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:20,60'])
);

// ════════════════════════════════════════════════════════════
// IMAGE TYPES  →  /v1/admin/image-types/*
// ════════════════════════════════════════════════════════════

// POST — إنشاء نوع صورة
$router->addRoute('POST', '/v1/admin/image-types',
    'ImageTypesController@store',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60'])
);

// PUT — تعديل نوع صورة
$router->addRoute('PUT', '/v1/admin/image-types/{id}',
    'ImageTypesController@update',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60'])
);

// DELETE — حذف نوع صورة
$router->addRoute('DELETE', '/v1/admin/image-types/{id}',
    'ImageTypesController@destroy',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:20,60'])
);

// ════════════════════════════════════════════════════════════
// IMAGES  →  /v1/admin/images/*
// ════════════════════════════════════════════════════════════

// GET — قائمة الصور (أدمن)
$router->addRoute('GET', '/v1/admin/images',
    'ImagesController@index',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:60,60'])
);

// POST — رفع صورة (multipart)
$router->addRoute('POST', '/v1/admin/images/upload',
    'ImagesController@upload',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60'])
);

// POST — إنشاء سجل صورة بالرابط
$router->addRoute('POST', '/v1/admin/images',
    'ImagesController@store',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60'])
);

// PUT — تعديل صورة
$router->addRoute('PUT', '/v1/admin/images/{id}',
    'ImagesController@update',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60'])
);

// DELETE — حذف صورة
$router->addRoute('DELETE', '/v1/admin/images/{id}',
    'ImagesController@destroy',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:20,60'])
);

// PATCH — تعيين صورة رئيسية
$router->addRoute('PATCH', '/v1/admin/images/{id}/set-main',
    'ImagesController@setMain',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60'])
);

// ════════════════════════════════════════════════════════════
// PRODUCTS  →  /v1/admin/products/*
// ════════════════════════════════════════════════════════════

// POST — إنشاء منتج
$router->addRoute('POST', '/v1/admin/products',
    'ProductsController@store',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60'])
);

// PUT — تعديل منتج
$router->addRoute('PUT', '/v1/admin/products/{id}',
    'ProductsController@update',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60'])
);

// DELETE — حذف منتج
$router->addRoute('DELETE', '/v1/admin/products/{id}',
    'ProductsController@destroy',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:20,60'])
);

unset($_authAdmin);
