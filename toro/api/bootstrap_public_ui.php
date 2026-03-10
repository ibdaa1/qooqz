<?php
declare(strict_types=1);
/**
 * Bootstrap Public UI — API context
 *
 * This file is loaded by bootstrap.php for non-admin, non-mobile requests.
 * For the TORO REST API all heavy lifting (DB, autoloader, exception handler,
 * CORS) is already done in bootstrap.php.  This file only adds lightweight
 * public-UI helpers that are safe to skip when unavailable.
 */

// ── Optional: language detection ──────────────────────────────────────────────
if (!defined('APP_LANG')) {
    $lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '', 0, 2) ?: 'en';
    if (!in_array($lang, ['en', 'ar'], true)) {
        $lang = 'en';
    }
    define('APP_LANG', $lang);
}

// ── Optional: load legacy config files if they exist ─────────────────────────
$legacyConfigs = [
    defined('API_BASE_PATH') ? API_BASE_PATH . '/config/constants.php' : null,
    defined('API_BASE_PATH') ? API_BASE_PATH . '/config/config.php'    : null,
    defined('API_BASE_PATH') ? API_BASE_PATH . '/config/db.php'        : null,
];
foreach ($legacyConfigs as $file) {
    if ($file && is_readable($file)) {
        require_once $file;
    }
}

// ── Optional: legacy jsonResponse helper ──────────────────────────────────────
if (!function_exists('jsonResponse')) {
    function jsonResponse(array $data, int $code = 200): void
    {
        http_response_code($code);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
