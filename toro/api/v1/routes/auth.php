<?php
/**
 * TORO — v1/routes/auth.php
 * مسارات المصادقة
 *
 * $router هو instance من Shared\Core\Kernel
 * يُحقن من bootstrap عبر loadRoutes()
 */

declare(strict_types=1);

use V1\Modules\Auth\Controllers\AuthController;
use Shared\Helpers\CSRF;
use V1\Middleware\AuthMiddleware;
use V1\Middleware\GuestMiddleware;
use V1\Middleware\ThrottleMiddleware;

// ── تسجيل الدخول ─────────────────────────────────────────────
$router->addRoute('POST', '/v1/auth/login',
    AuthController::class . '@login',
    [ThrottleMiddleware::class . ':10,60', GuestMiddleware::class]
);

// ── تسجيل حساب جديد ──────────────────────────────────────────
$router->addRoute('POST', '/v1/auth/register',
    AuthController::class . '@register',
    [ThrottleMiddleware::class . ':5,60', GuestMiddleware::class]
);

// ── تسجيل الخروج ─────────────────────────────────────────────
$router->addRoute('POST', '/v1/auth/logout',
    AuthController::class . '@logout',
    [AuthMiddleware::class]
);

// ── تجديد JWT ─────────────────────────────────────────────────
$router->addRoute('POST', '/v1/auth/refresh',
    AuthController::class . '@refresh',
    [ThrottleMiddleware::class . ':30,60']
);

// ── المستخدم الحالي ───────────────────────────────────────────
$router->addRoute('GET', '/v1/auth/me',
    AuthController::class . '@me',
    [AuthMiddleware::class]
);

// ── تغيير كلمة المرور ─────────────────────────────────────────
$router->addRoute('POST', '/v1/auth/change-password',
    AuthController::class . '@changePassword',
    [AuthMiddleware::class, ThrottleMiddleware::class . ':5,60']
);

// ── نسيت كلمة المرور ─────────────────────────────────────────
$router->addRoute('POST', '/v1/auth/forgot-password',
    AuthController::class . '@forgotPassword',
    [ThrottleMiddleware::class . ':3,60', GuestMiddleware::class]
);

// ── إعادة تعيين كلمة المرور ──────────────────────────────────
$router->addRoute('POST', '/v1/auth/reset-password',
    AuthController::class . '@resetPassword',
    [ThrottleMiddleware::class . ':5,60']
);

// ── OAuth: Google ─────────────────────────────────────────────
$router->addRoute('POST', '/v1/auth/oauth/google',
    AuthController::class . '@oauthGoogle',
    [ThrottleMiddleware::class . ':20,60']
);

// ── OAuth: Facebook ───────────────────────────────────────────
$router->addRoute('POST', '/v1/auth/oauth/facebook',
    AuthController::class . '@oauthFacebook',
    [ThrottleMiddleware::class . ':20,60']
);

// ── تحقق البريد الإلكتروني ────────────────────────────────────
$router->addRoute('GET', '/v1/auth/verify-email/{token}',
    AuthController::class . '@verifyEmail',
    []
);
