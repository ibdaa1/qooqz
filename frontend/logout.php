<?php
declare(strict_types=1);
/**
 * frontend/logout.php
 * Handles logout for public frontend users.
 */

// Use same session config as login.php / API auth
if (session_status() === PHP_SESSION_NONE) {
    $__sharedSess = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/api/shared/config/session.php';
    if (file_exists($__sharedSess)) {
        require_once $__sharedSess;
    } else {
        $__sp = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/api/storage/sessions';
        session_name('APP_SESSID');
        if (is_dir($__sp)) ini_set('session.save_path', $__sp);
        session_start();
    }
    unset($__sharedSess, $__sp);
}

// Allow both GET (link click) and POST (form submit)
// For GET requests we skip CSRF check (acceptable for a public frontend logout link)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = $_POST['csrf_token'] ?? '';
    $session_token = $_SESSION['csrf_token'] ?? '';
    if ($session_token !== '' && !hash_equals($session_token, $posted_token)) {
        // Token mismatch — still proceed with logout (fail-safe)
    }
}

// Clear session data
$_SESSION = [];

// Delete session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

session_destroy();

// Prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Clear client-side auth data (localStorage.pubUser) then redirect
echo '<!doctype html><html><head><title>Logging out...</title></head><body>';
echo '<script>';
echo 'try{localStorage.removeItem("pubUser");}catch(e){}';
echo 'window.location.replace("/frontend/login.php");';
echo '</script>';
echo '</body></html>';
exit;