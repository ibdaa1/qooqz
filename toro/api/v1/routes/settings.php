<?php
/**
 * TORO — v1/routes/settings.php
 * مسارات الإعدادات — /v1/settings/*
 *
 * Admin-only routes are in routes/admin.php (/v1/admin/settings)
 * Public routes are in routes/public.php (/v1/public/settings)
 *
 * $router هو instance من Shared\Core\Kernel
 */

declare(strict_types=1);

// ── تحميل ملفات Settings ─────────────────────────────────────
$_settPath = __DIR__ . '/../modules/Settings';
require_once $_settPath . '/Contracts/SettingsRepositoryInterface.php';
require_once $_settPath . '/DTO/SettingDTO.php';
require_once $_settPath . '/Validators/SettingsValidator.php';
require_once $_settPath . '/Repositories/PdoSettingsRepository.php';
require_once $_settPath . '/Services/SettingsService.php';
require_once $_settPath . '/Controllers/SettingsController.php';
unset($_settPath);

$_authAdmin = ['V1\Middleware\AuthMiddleware', 'V1\Middleware\AdminMiddleware'];

// GET /v1/settings/public — الإعدادات العامة للموقع (بدون مصادقة)
$router->addRoute('GET', '/v1/settings/public',
    'SettingsController@getPublic',
    ['V1\Middleware\ThrottleMiddleware:120,60']
);

// GET /v1/settings — قائمة الإعدادات
$router->addRoute('GET', '/v1/settings',
    'SettingsController@index',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:60,60'])
);

// GET /v1/settings/group/{group} — إعدادات مجموعة معينة
$router->addRoute('GET', '/v1/settings/group/{group}',
    'SettingsController@byGroup',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:60,60'])
);

// GET /v1/settings/{id} — إعداد واحد بالـ ID
$router->addRoute('GET', '/v1/settings/{id}',
    'SettingsController@show',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:60,60'])
);

// POST /v1/settings — إنشاء إعداد جديد
$router->addRoute('POST', '/v1/settings',
    'SettingsController@store',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60'])
);

// PUT /v1/settings/{id} — تعديل قيمة إعداد
$router->addRoute('PUT', '/v1/settings/{id}',
    'SettingsController@update',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60'])
);

// DELETE /v1/settings/{id} — حذف إعداد
$router->addRoute('DELETE', '/v1/settings/{id}',
    'SettingsController@destroy',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:20,60'])
);

unset($_authAdmin);
