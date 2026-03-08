<?php
/**
 * TORO — v1/routes/settings.php
 * مسارات الإعدادات
 *
 * $router هو instance من Shared\Core\Kernel
 * يُحقن من bootstrap عبر loadRoutes()
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

// ── الإعدادات العامة (بدون مصادقة) ──────────────────────────

// GET /v1/settings/public — الإعدادات العامة للموقع
$router->addRoute('GET', '/v1/settings/public',
    'SettingsController@getPublic',
    ['V1\Middleware\ThrottleMiddleware:120,60']
);

// ── إدارة الإعدادات (أدمن فقط) ──────────────────────────────

// GET /v1/settings — كل الإعدادات
$router->addRoute('GET', '/v1/settings',
    'SettingsController@index',
    ['V1\Middleware\AuthMiddleware', 'V1\Middleware\AdminMiddleware', 'V1\Middleware\ThrottleMiddleware:60,60']
);

// PUT /v1/settings/{id} — تعديل إعداد
$router->addRoute('PUT', '/v1/settings/{id}',
    'SettingsController@update',
    ['V1\Middleware\AuthMiddleware', 'V1\Middleware\AdminMiddleware', 'V1\Middleware\ThrottleMiddleware:30,60']
);
