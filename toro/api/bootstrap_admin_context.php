<?php
/**
 * TORO — bootstrap_admin_context.php
 * /public_html/toro/api/bootstrap_admin_context.php
 *
 * Extra setup required for admin panel routes:
 *   - Enforce HTTPS
 *   - Stricter rate limiting
 *   - Admin-only RBAC check (applied per-route in middleware, not globally here)
 *   - Load AdminUiThemeLoader for dynamic CSS
 */

declare(strict_types=1);

// Force HTTPS in production
if (
    ($_ENV['APP_ENV'] ?? 'production') === 'production'
    && empty($_SERVER['HTTPS'])
    && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') !== 'https'
) {
    $redirect = 'https://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/');
    header('Location: ' . $redirect, true, 301);
    exit;
}

// Mark this request as admin context — used by Kernel to select middleware stack
define('REQUEST_CONTEXT', 'admin');

// Admin-specific session hardening
if (session_status() === PHP_SESSION_NONE) {
    session_name('TORO_ADMIN_SID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/toro/api/admin',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}