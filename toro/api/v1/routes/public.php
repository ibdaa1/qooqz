<?php
/**
 * TORO — v1/routes/public.php
 * المسارات العامة — /v1/public/*
 *
 * جميع المسارات هنا بدون مصادقة (public access).
 * هذا الملف مستقل تماماً عن routes/admin.php.
 *
 * $router هو instance من Shared\Core\Kernel
 */

declare(strict_types=1);

$_publicMw = ['V1\Middleware\ThrottleMiddleware:120,60'];

// ════════════════════════════════════════════════════════════
// AUTH  →  /v1/public/auth/*
// ════════════════════════════════════════════════════════════

$router->addRoute('POST', '/v1/public/auth/login',
    'AuthController@login',
    ['V1\Middleware\ThrottleMiddleware:10,60', 'V1\Middleware\GuestMiddleware']
);

$router->addRoute('POST', '/v1/public/auth/register',
    'AuthController@register',
    ['V1\Middleware\ThrottleMiddleware:5,60', 'V1\Middleware\GuestMiddleware']
);

$router->addRoute('POST', '/v1/public/auth/refresh',
    'AuthController@refresh',
    ['V1\Middleware\ThrottleMiddleware:30,60']
);

$router->addRoute('POST', '/v1/public/auth/forgot-password',
    'AuthController@forgotPassword',
    ['V1\Middleware\ThrottleMiddleware:3,60', 'V1\Middleware\GuestMiddleware']
);

$router->addRoute('POST', '/v1/public/auth/reset-password',
    'AuthController@resetPassword',
    ['V1\Middleware\ThrottleMiddleware:5,60']
);

$router->addRoute('POST', '/v1/public/auth/oauth/google',
    'AuthController@oauthGoogle',
    ['V1\Middleware\ThrottleMiddleware:20,60']
);

$router->addRoute('POST', '/v1/public/auth/oauth/facebook',
    'AuthController@oauthFacebook',
    ['V1\Middleware\ThrottleMiddleware:20,60']
);

$router->addRoute('GET', '/v1/public/auth/verify-email/{token}',
    'AuthController@verifyEmail',
    ['V1\Middleware\ThrottleMiddleware:10,60']
);

// ════════════════════════════════════════════════════════════
// SETTINGS  →  /v1/public/settings
// ════════════════════════════════════════════════════════════

// GET — الإعدادات العامة للموقع
$router->addRoute('GET', '/v1/public/settings',
    'SettingsController@getPublic',
    $_publicMw
);

// ════════════════════════════════════════════════════════════
// THEME  →  /v1/public/theme/css
// ════════════════════════════════════════════════════════════

// GET — CSS variables للثيم النشط
$router->addRoute('GET', '/v1/public/theme/css',
    'ThemeController@getCss',
    $_publicMw
);

// ════════════════════════════════════════════════════════════
// CATEGORIES  →  /v1/public/categories/*
// ════════════════════════════════════════════════════════════

// GET — قائمة التصنيفات
$router->addRoute('GET', '/v1/public/categories',
    'CategoriesController@index',
    $_publicMw
);

// GET — تصنيف بالـ ID
$router->addRoute('GET', '/v1/public/categories/{id}',
    'CategoriesController@show',
    $_publicMw
);

// GET — تصنيف بالـ slug
$router->addRoute('GET', '/v1/public/categories/slug/{slug}',
    'CategoriesController@showBySlug',
    $_publicMw
);

// GET — كل ترجمات التصنيف
$router->addRoute('GET', '/v1/public/categories/{id}/translations',
    'CategoriesController@translations',
    $_publicMw
);

// ════════════════════════════════════════════════════════════
// BRANDS  →  /v1/public/brands/*
// ════════════════════════════════════════════════════════════

// GET — قائمة الماركات
$router->addRoute('GET', '/v1/public/brands',
    'BrandsController@index',
    $_publicMw
);

// GET — ماركة بالـ ID
$router->addRoute('GET', '/v1/public/brands/{id}',
    'BrandsController@show',
    $_publicMw
);

// GET — ماركة بالـ slug
$router->addRoute('GET', '/v1/public/brands/slug/{slug}',
    'BrandsController@showBySlug',
    $_publicMw
);

// GET — كل ترجمات الماركة
$router->addRoute('GET', '/v1/public/brands/{id}/translations',
    'BrandsController@translations',
    $_publicMw
);

unset($_publicMw);
