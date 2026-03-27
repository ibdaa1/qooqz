<?php
/**
 * frontend/logout.php
 * Handles logout for public frontend users.
 */

// Use same session config as login.php / API auth
if (session_status() === PHP_SESSION_NONE) {
    $__sharedSess = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/api/shared/config/session.php';
    if (!file_exists($__sharedSess)) {
        $__sharedSess = dirname(__DIR__) . '/api/shared/config/session.php';
    }
    if (file_exists($__sharedSess)) {
        require_once $__sharedSess;           // defines destroySession() + calls session_start()
    } else {
        // Last-resort manual fallback
        $__sp = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/api/storage/sessions';
        session_name('APP_SESSID');
        if (is_dir($__sp)) ini_set('session.save_path', $__sp);
        session_start();
    }
    unset($__sharedSess, $__sp);
}

// Prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Destroy session using the helper defined in session.php (handles cookie deletion correctly)
// Falls back to manual destruction if the helper is unavailable (e.g. session.php not found)
if (function_exists('destroySession')) {
    destroySession();
} else {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        // Use PHP 7.3+ array form to properly handle SameSite attribute
        setcookie(session_name(), '', array_merge($params, ['expires' => time() - 42000]));
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

// Output a small HTML page that:
//  1. Clears localStorage (pubUser, pub_cart) via JS
//  2. Redirects to login page
//  3. Falls back to <meta http-equiv="refresh"> if JS is blocked
echo '<!doctype html>';
echo '<html><head>';
echo '<meta charset="utf-8">';
echo '<title>Logging out…</title>';
echo '<meta http-equiv="refresh" content="0;url=/frontend/login.php">';
echo '</head><body>';
echo '<script>';
echo 'try{localStorage.removeItem("pubUser");}catch(e){}';
echo 'try{localStorage.removeItem("pub_cart");}catch(e){}';
echo 'try{localStorage.removeItem("pubSessionUser");}catch(e){}';
echo 'window.location.replace("/frontend/login.php");';
echo '</script>';
echo '<p style="font-family:sans-serif;text-align:center;margin-top:40px;">Logging out…</p>';
echo '</body></html>';
exit;