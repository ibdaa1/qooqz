<?php
declare(strict_types=1);
/**
 * frontend/includes/public_context.php
 *
 * Public context bootstrap for QOOQZ frontend pages.
 * - Loads theme colors/settings from API
 * - Handles language detection (ar/en/...)
 * - Provides helper functions for public pages
 * - No auth required (guest-friendly)
 */

if (!defined('FRONTEND_PUBLIC_CONTEXT')) {
    define('FRONTEND_PUBLIC_CONTEXT', true);
}

/* -------------------------------------------------------
 * 1. Base path & environment
 * ----------------------------------------------------- */
defined('FRONTEND_BASE') || define('FRONTEND_BASE', dirname(__DIR__));

$envFile = FRONTEND_BASE . '/config/app.php';
$appConfig = is_readable($envFile) ? (require $envFile) : [];

$apiConfigFile = FRONTEND_BASE . '/config/api.php';
$apiConfig = is_readable($apiConfigFile) ? (require $apiConfigFile) : [];

/* -------------------------------------------------------
 * 2. Session (safe)
 * ----------------------------------------------------- */
if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    session_start([
        'cookie_httponly' => true,
        'cookie_secure'   => $isSecure,
        'cookie_samesite' => 'Lax',
    ]);
}

/* -------------------------------------------------------
 * 3. Language & direction
 * ----------------------------------------------------- */
$_RTL_LANGS = ['ar', 'fa', 'ur', 'he'];

$lang = $_GET['lang']
     ?? $_SESSION['pub_lang']
     ?? ($appConfig['default_lang'] ?? 'ar');

// Sanitise: only allow [a-z]{2,5}
$lang = preg_match('/^[a-z]{2,5}$/', $lang) ? $lang : 'ar';
$_SESSION['pub_lang'] = $lang;

$dir = in_array($lang, $_RTL_LANGS, true) ? 'rtl' : 'ltr';

/* -------------------------------------------------------
 * 4. API base URL (used for server-side fetch)
 * ----------------------------------------------------- */
if (!function_exists('pub_api_url')) {
    function pub_api_url(string $path = ''): string {
        // Detect scheme + host for self-referencing API calls
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base   = rtrim($scheme . '://' . $host . '/api', '/');
        return $base . '/' . ltrim($path, '/');
    }
}

/* -------------------------------------------------------
 * 5. Lightweight HTTP fetch (curl/file_get_contents)
 * ----------------------------------------------------- */
if (!function_exists('pub_fetch')) {
    /**
     * Fetch JSON from the internal API.
     * Returns decoded array or [] on failure.
     */
    function pub_fetch(string $url, int $timeout = 4): array {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_HTTPHEADER     => ['Accept: application/json'],
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $body = curl_exec($ch);
            curl_close($ch);
        } else {
            $ctx  = stream_context_create(['http' => ['timeout' => $timeout]]);
            $body = @file_get_contents($url, false, $ctx);
        }
        if (!$body) return [];
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : [];
    }
}

/* -------------------------------------------------------
 * 6. Theme / color settings (from DB via API)
 * ----------------------------------------------------- */
if (!function_exists('pub_load_theme')) {
    function pub_load_theme(int $tenantId = 1): array {
        $defaults = [
            'primary'    => '#2d8cf0',
            'secondary'  => '#6c757d',
            'accent'     => '#f39c12',
            'background' => '#ffffff',
            'surface'    => '#f8f9fb',
            'text'       => '#222831',
        ];

        // Try to get from session cache (TTL: 5 min)
        $cacheKey = 'pub_theme_' . $tenantId;
        if (!empty($_SESSION[$cacheKey]) && !empty($_SESSION[$cacheKey . '_ts'])
            && (time() - $_SESSION[$cacheKey . '_ts']) < 300) {
            return $_SESSION[$cacheKey];
        }

        // Fetch from API
        $url  = pub_api_url('color_settings/active') . '?tenant_id=' . $tenantId;
        $resp = pub_fetch($url);

        if (!empty($resp['data']) && is_array($resp['data'])) {
            $colors = $defaults;
            foreach ($resp['data'] as $item) {
                $key = strtolower($item['key'] ?? '');
                $val = $item['value'] ?? '';
                if ($key && $val) {
                    $colors[$key] = $val;
                }
            }
            $_SESSION[$cacheKey]        = $colors;
            $_SESSION[$cacheKey . '_ts'] = time();
            return $colors;
        }

        return $defaults;
    }
}

/* -------------------------------------------------------
 * 7. XSS escape helper
 * ----------------------------------------------------- */
if (!function_exists('e')) {
    function e($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

/* -------------------------------------------------------
 * 8. Pagination helper
 * ----------------------------------------------------- */
if (!function_exists('pub_paginate')) {
    /**
     * Returns pagination info array.
     */
    function pub_paginate(int $total, int $page, int $perPage): array {
        $totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;
        return [
            'total'       => $total,
            'page'        => max(1, $page),
            'per_page'    => $perPage,
            'total_pages' => $totalPages,
            'has_prev'    => $page > 1,
            'has_next'    => $page < $totalPages,
        ];
    }
}

/* -------------------------------------------------------
 * 9. Compose the shared context globals
 * ----------------------------------------------------- */
$tenantId = (int)($_GET['tenant_id'] ?? $_SESSION['pub_tenant_id'] ?? 1);
$_SESSION['pub_tenant_id'] = $tenantId;

$theme = pub_load_theme($tenantId);

$GLOBALS['PUB_CONTEXT'] = [
    'lang'      => $lang,
    'dir'       => $dir,
    'tenant_id' => $tenantId,
    'theme'     => $theme,
    'app'       => $appConfig,
    'user'      => $_SESSION['current_user'] ?? null,
];
