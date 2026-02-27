<?php
declare(strict_types=1);

/**
 * frontend/login.php
 * Public frontend login/register page.
 * Uses APP_SESSID to match the API auth session.
 */

// Use same session name as API auth (api/routes/auth.php)
session_name('APP_SESSID');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set('display_errors', '0');
error_reporting(E_ALL);

// If already logged in, redirect to homepage
if (!empty($_SESSION['user'])) {
    header('Location: /frontend/public/index.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Admin Login</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
<div class="login-wrapper">
    <div class="login-box">
        <div class="tabs">
            <button id="tab-login" class="tab active" onclick="showForm('login')">Login</button>
            <button id="tab-register" class="tab" onclick="showForm('register')">Register</button>
        </div>

        <form id="loginForm" class="form" action="javascript:void(0);" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <h2>Login</h2>

            <div class="form-group">
                <label for="username">Username / Email / Phone</label>
                <input id="username" name="username" type="text" required />
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" required />
            </div>

            <input type="hidden" name="action" value="login">
            <div class="form-actions">
                <button type="submit" class="btn">Login</button>
            </div>
        </form>

        <form id="registerForm" class="form hidden" action="javascript:void(0);" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <h2>Register</h2>

            <div class="form-group">
                <label for="reg_username">Username</label>
                <input id="reg_username" name="username" type="text" required />
            </div>

            <div class="form-group">
                <label for="reg_email">Email</label>
                <input id="reg_email" name="email" type="email" required />
            </div>

            <div class="form-group">
                <label for="reg_password">Password</label>
                <input id="reg_password" name="password" type="password" required />
            </div>

            <input type="hidden" name="action" value="register">
            <div class="form-actions">
                <button type="submit" class="btn">Register</button>
            </div>
        </form>

        <div id="result" class="result" role="status" aria-live="polite"></div>
    </div>
</div>

<script src="assets/js/login.js"></script>
</body>
</html>