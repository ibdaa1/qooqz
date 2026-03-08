<?php
/**
 * TORO — v1/routes/auth.php
 * مسارات المصادقة
 *
 * $router هو instance من Shared\Core\Kernel
 * يُحقن من bootstrap عبر loadRoutes()
 */

declare(strict_types=1);

// ── تحميل ملفات Auth ────────────────────────────────────────
$_authPath = __DIR__ . '/../modules/Auth';
require_once $_authPath . '/Contracts/AuthRepositoryInterface.php';
require_once $_authPath . '/DTO/LoginDTO.php';
require_once $_authPath . '/DTO/RegisterDTO.php';
require_once $_authPath . '/DTO/OAuthDTO.php';
require_once $_authPath . '/Validators/AuthValidator.php';
require_once $_authPath . '/Repositories/PdoAuthRepository.php';
require_once $_authPath . '/Services/JwtService.php';
require_once $_authPath . '/Services/OAuthService.php';
require_once $_authPath . '/Services/AuthService.php';
require_once $_authPath . '/Controllers/AuthController.php';
unset($_authPath);

// ── تسجيل الدخول ─────────────────────────────────────────────
$router->addRoute('POST', '/v1/auth/login',
    'AuthController@login',
    ['V1\Middleware\ThrottleMiddleware:10,60', 'V1\Middleware\GuestMiddleware']
);

// ── تسجيل حساب جديد ──────────────────────────────────────────
$router->addRoute('POST', '/v1/auth/register',
    'AuthController@register',
    ['V1\Middleware\ThrottleMiddleware:5,60', 'V1\Middleware\GuestMiddleware']
);

// ── تسجيل الخروج ─────────────────────────────────────────────
$router->addRoute('POST', '/v1/auth/logout',
    'AuthController@logout',
    ['V1\Middleware\AuthMiddleware']
);

// ── تجديد JWT ─────────────────────────────────────────────────
$router->addRoute('POST', '/v1/auth/refresh',
    'AuthController@refresh',
    ['V1\Middleware\ThrottleMiddleware:30,60']
);

// ── المستخدم الحالي ───────────────────────────────────────────
$router->addRoute('GET', '/v1/auth/me',
    'AuthController@me',
    ['V1\Middleware\AuthMiddleware']
);

// ── تغيير كلمة المرور ─────────────────────────────────────────
$router->addRoute('POST', '/v1/auth/change-password',
    'AuthController@changePassword',
    ['V1\Middleware\AuthMiddleware', 'V1\Middleware\ThrottleMiddleware:5,60']
);

// ── نسيت كلمة المرور ─────────────────────────────────────────
$router->addRoute('POST', '/v1/auth/forgot-password',
    'AuthController@forgotPassword',
    ['V1\Middleware\ThrottleMiddleware:3,60', 'V1\Middleware\GuestMiddleware']
);

// ── إعادة تعيين كلمة المرور ──────────────────────────────────
$router->addRoute('POST', '/v1/auth/reset-password',
    'AuthController@resetPassword',
    ['V1\Middleware\ThrottleMiddleware:5,60']
);

// ── OAuth: Google ─────────────────────────────────────────────
$router->addRoute('POST', '/v1/auth/oauth/google',
    'AuthController@oauthGoogle',
    ['V1\Middleware\ThrottleMiddleware:20,60']
);

// ── OAuth: Facebook ───────────────────────────────────────────
$router->addRoute('POST', '/v1/auth/oauth/facebook',
    'AuthController@oauthFacebook',
    ['V1\Middleware\ThrottleMiddleware:20,60']
);

// ── تحقق البريد الإلكتروني ────────────────────────────────────
$router->addRoute('GET', '/v1/auth/verify-email/{token}',
    'AuthController@verifyEmail',
    []
);
