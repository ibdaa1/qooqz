<?php
/**
 * TORO — v1/routes/categories.php
 * مسارات التصنيفات
 *
 * $router هو instance من Shared\Core\Kernel
 * يُحقن من bootstrap عبر loadRoutes()
 */

declare(strict_types=1);

use V1\Modules\Categories\Controllers\CategoriesController;
use V1\Middleware\AuthMiddleware;
use V1\Middleware\AdminMiddleware;
use V1\Middleware\ThrottleMiddleware;

// ── قراءة التصنيفات (عام) ────────────────────────────────────

// GET /v1/categories — قائمة التصنيفات
$router->addRoute('GET', '/v1/categories',
    CategoriesController::class . '@index',
    [ThrottleMiddleware::class . ':120,60']
);

// GET /v1/categories/{id} — تصنيف بالـ ID
$router->addRoute('GET', '/v1/categories/{id}',
    CategoriesController::class . '@show',
    [ThrottleMiddleware::class . ':120,60']
);

// GET /v1/categories/slug/{slug} — تصنيف بالـ slug
$router->addRoute('GET', '/v1/categories/slug/{slug}',
    CategoriesController::class . '@showBySlug',
    [ThrottleMiddleware::class . ':120,60']
);

// GET /v1/categories/{id}/translations — كل ترجمات التصنيف
$router->addRoute('GET', '/v1/categories/{id}/translations',
    CategoriesController::class . '@translations',
    [ThrottleMiddleware::class . ':120,60']
);

// ── إدارة التصنيفات (أدمن فقط) ──────────────────────────────

// POST /v1/categories — إنشاء تصنيف
$router->addRoute('POST', '/v1/categories',
    CategoriesController::class . '@store',
    [AuthMiddleware::class, AdminMiddleware::class, ThrottleMiddleware::class . ':30,60']
);

// PUT /v1/categories/{id} — تعديل تصنيف
$router->addRoute('PUT', '/v1/categories/{id}',
    CategoriesController::class . '@update',
    [AuthMiddleware::class, AdminMiddleware::class, ThrottleMiddleware::class . ':30,60']
);

// DELETE /v1/categories/{id} — حذف تصنيف
$router->addRoute('DELETE', '/v1/categories/{id}',
    CategoriesController::class . '@destroy',
    [AuthMiddleware::class, AdminMiddleware::class, ThrottleMiddleware::class . ':20,60']
);
