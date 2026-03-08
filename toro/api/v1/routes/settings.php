<?php
/**
 * TORO — v1/routes/settings.php
 * مسارات الإعدادات
 *
 * $router هو instance من Shared\Core\Kernel
 * يُحقن من bootstrap عبر loadRoutes()
 *
 * TODO: إنشاء SettingsController عند الحاجة
 */

declare(strict_types=1);

use V1\Middleware\AuthMiddleware;
use V1\Middleware\AdminMiddleware;
use V1\Middleware\ThrottleMiddleware;

// ── مثال: GET /v1/settings/public (TODO: تفعيل بعد إنشاء الـ Controller) ────
// $router->addRoute('GET', '/v1/settings/public',
//     SettingsController::class . '@getPublic',
//     [ThrottleMiddleware::class . ':60,60']
// );

// $router->addRoute('GET', '/v1/settings',
//     SettingsController::class . '@index',
//     [AuthMiddleware::class, AdminMiddleware::class]
// );

// $router->addRoute('PUT', '/v1/settings/{id}',
//     SettingsController::class . '@update',
//     [AuthMiddleware::class, AdminMiddleware::class]
// );