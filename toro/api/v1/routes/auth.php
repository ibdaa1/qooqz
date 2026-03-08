<?php
/**
 * TORO — v1/routes/auth.php
 * مسارات المصادقة
 *
 * Prefixes:
 *   /v1/public/auth  — تسجيل دخول / تسجيل جديد (بدون مصادقة)
 *   /v1/admin/auth   — عمليات المصادقة للأدمن (نفس الـ endpoints)
 *
 * $router هو instance من Shared\Core\Kernel
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

// ════════════════════════════════════════════════════════════
// تسجيل الدخول — POST /v1/auth/login | /v1/public/auth/login
// ════════════════════════════════════════════════════════════
foreach (['/v1/auth/login', '/v1/public/auth/login'] as $_path) {
    $router->addRoute('POST', $_path, 'AuthController@login',
        ['V1\Middleware\ThrottleMiddleware:10,60', 'V1\Middleware\GuestMiddleware']);
}

// ════════════════════════════════════════════════════════════
// تسجيل حساب جديد — POST /v1/auth/register | /v1/public/auth/register
// ════════════════════════════════════════════════════════════
foreach (['/v1/auth/register', '/v1/public/auth/register'] as $_path) {
    $router->addRoute('POST', $_path, 'AuthController@register',
        ['V1\Middleware\ThrottleMiddleware:5,60', 'V1\Middleware\GuestMiddleware']);
}

// ════════════════════════════════════════════════════════════
// تسجيل الخروج — POST /v1/auth/logout | /v1/admin/auth/logout
// ════════════════════════════════════════════════════════════
foreach (['/v1/auth/logout', '/v1/admin/auth/logout'] as $_path) {
    $router->addRoute('POST', $_path, 'AuthController@logout',
        ['V1\Middleware\AuthMiddleware']);
}

// ════════════════════════════════════════════════════════════
// تجديد JWT — POST /v1/auth/refresh | /v1/public/auth/refresh
// ════════════════════════════════════════════════════════════
foreach (['/v1/auth/refresh', '/v1/public/auth/refresh'] as $_path) {
    $router->addRoute('POST', $_path, 'AuthController@refresh',
        ['V1\Middleware\ThrottleMiddleware:30,60']);
}

// ════════════════════════════════════════════════════════════
// المستخدم الحالي — GET /v1/auth/me | /v1/admin/auth/me
// ════════════════════════════════════════════════════════════
foreach (['/v1/auth/me', '/v1/admin/auth/me'] as $_path) {
    $router->addRoute('GET', $_path, 'AuthController@me',
        ['V1\Middleware\AuthMiddleware']);
}

// ════════════════════════════════════════════════════════════
// تغيير كلمة المرور — POST /v1/auth/change-password | /v1/admin/auth/change-password
// ════════════════════════════════════════════════════════════
foreach (['/v1/auth/change-password', '/v1/admin/auth/change-password'] as $_path) {
    $router->addRoute('POST', $_path, 'AuthController@changePassword',
        ['V1\Middleware\AuthMiddleware', 'V1\Middleware\ThrottleMiddleware:5,60']);
}

// ════════════════════════════════════════════════════════════
// نسيت كلمة المرور — POST /v1/auth/forgot-password | /v1/public/auth/forgot-password
// ════════════════════════════════════════════════════════════
foreach (['/v1/auth/forgot-password', '/v1/public/auth/forgot-password'] as $_path) {
    $router->addRoute('POST', $_path, 'AuthController@forgotPassword',
        ['V1\Middleware\ThrottleMiddleware:3,60', 'V1\Middleware\GuestMiddleware']);
}

// ════════════════════════════════════════════════════════════
// إعادة تعيين كلمة المرور — POST /v1/auth/reset-password | /v1/public/auth/reset-password
// ════════════════════════════════════════════════════════════
foreach (['/v1/auth/reset-password', '/v1/public/auth/reset-password'] as $_path) {
    $router->addRoute('POST', $_path, 'AuthController@resetPassword',
        ['V1\Middleware\ThrottleMiddleware:5,60']);
}

// ════════════════════════════════════════════════════════════
// OAuth: Google — POST /v1/auth/oauth/google | /v1/public/auth/oauth/google
// ════════════════════════════════════════════════════════════
foreach (['/v1/auth/oauth/google', '/v1/public/auth/oauth/google'] as $_path) {
    $router->addRoute('POST', $_path, 'AuthController@oauthGoogle',
        ['V1\Middleware\ThrottleMiddleware:20,60']);
}

// ════════════════════════════════════════════════════════════
// OAuth: Facebook — POST /v1/auth/oauth/facebook | /v1/public/auth/oauth/facebook
// ════════════════════════════════════════════════════════════
foreach (['/v1/auth/oauth/facebook', '/v1/public/auth/oauth/facebook'] as $_path) {
    $router->addRoute('POST', $_path, 'AuthController@oauthFacebook',
        ['V1\Middleware\ThrottleMiddleware:20,60']);
}

// ════════════════════════════════════════════════════════════
// تحقق البريد الإلكتروني — GET /v1/auth/verify-email/{token}
// ════════════════════════════════════════════════════════════
foreach (['/v1/auth/verify-email/{token}', '/v1/public/auth/verify-email/{token}'] as $_path) {
    $router->addRoute('GET', $_path, 'AuthController@verifyEmail',
        ['V1\Middleware\ThrottleMiddleware:10,60']);
}

unset($_path);
