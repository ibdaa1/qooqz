<?php
/**
 * TORO — bootstrap_mobile_context.php
 * /public_html/toro/api/bootstrap_mobile_context.php
 *
 * Mobile app (React Native / PWA) uses JWT instead of sessions.
 * Header: X-Mobile-App: 1
 */

declare(strict_types=1);

define('REQUEST_CONTEXT', 'mobile');

// Mobile clients must not receive Set-Cookie
// All auth is header-based (Authorization: Bearer <JWT>)

// Detect language from Accept-Language or custom header
$langCode = $_SERVER['HTTP_X_APP_LANG']
    ?? substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'ar', 0, 2);

define('REQUEST_LANG', $langCode);