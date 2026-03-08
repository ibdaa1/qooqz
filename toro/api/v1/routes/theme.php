<?php
/**
 * TORO — v1/routes/theme.php
 * مسارات الثيم
 *
 * $router هو instance من Shared\Core\Kernel
 * يُحقن من bootstrap عبر loadRoutes()
 */

declare(strict_types=1);

// ── تحميل ملفات Theme ────────────────────────────────────────
$_themePath = __DIR__ . '/../modules/Theme';
require_once $_themePath . '/Contracts/ThemeRepositoryInterface.php';
require_once $_themePath . '/Repositories/PdoThemeRepository.php';
require_once $_themePath . '/Services/ThemeService.php';
require_once $_themePath . '/Controllers/ThemeController.php';
unset($_themePath);

// ── الثيم العام (بدون مصادقة) ────────────────────────────────

// GET /v1/theme/css — CSS variables للثيم النشط
$router->addRoute('GET', '/v1/theme/css',
    'ThemeController@getCss',
    ['V1\Middleware\ThrottleMiddleware:120,60']
);

// ── إدارة الثيم (أدمن فقط) ───────────────────────────────────

// GET /v1/theme — كل ألوان الثيم
$router->addRoute('GET', '/v1/theme',
    'ThemeController@index',
    ['V1\Middleware\AuthMiddleware', 'V1\Middleware\AdminMiddleware', 'V1\Middleware\ThrottleMiddleware:60,60']
);

// PUT /v1/theme/{id} — تعديل لون
$router->addRoute('PUT', '/v1/theme/{id}',
    'ThemeController@update',
    ['V1\Middleware\AuthMiddleware', 'V1\Middleware\AdminMiddleware', 'V1\Middleware\ThrottleMiddleware:30,60']
);
