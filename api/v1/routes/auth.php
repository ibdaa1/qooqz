<?php
declare(strict_types=1);
/**
 * routes/auth.php (improved)
 *
 * - Robust session handling: ensure session started, consistent cookie params
 * - session_regenerate_id(true) on successful login
 * - Sets Cache-Control: no-store on responses to avoid caching auth responses
 * - Supports JSON and form payloads
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    // Defensive session cookie params — adjust domain as needed
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $cookieParams = [
        'lifetime' => 0,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'] ?? '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ];
    // Always use APP_SESSID to match admin/login.php and public_context.php.
    // PHP default session_name() is 'PHPSESSID' (never empty), so the old
    // `=== ''` guard never fired → sessions were created under PHPSESSID
    // while the frontend read APP_SESSID → user always appeared logged out.
    if (session_name() !== 'APP_SESSID') session_name('APP_SESSID');
    // some PHP versions accept array param
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params($cookieParams);
    } else {
        session_set_cookie_params($cookieParams['lifetime'], $cookieParams['path'], $cookieParams['domain'], $cookieParams['secure'], $cookieParams['httponly']);
    }
    @session_start();
}

// helper to emit no-cache header for auth
function _no_cache(): void {
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }
}

// Ensure DB
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    _no_cache();
    ResponseFormatter::serverError('Database unavailable');
    exit;
}

// Read dispatcher segments / action
$segments = $_GET['segments'] ?? [];
$firstSeg = strtolower($segments[0] ?? '');
$action = $firstSeg ?: (isset($_GET['__action']) ? strtolower($_GET['__action']) : '');

// read payload (JSON preferred)
function _read_payload(): array {
    $raw = @file_get_contents('php://input');
    if ($raw) {
        $d = @json_decode($raw, true);
        if (is_array($d)) return $d;
    }
    return $_POST ?: [];
}

// current user helper
function _current_user(): ?array {
    $u = $GLOBALS['ADMIN_USER'] ?? null;
    if (!$u && !empty($_SESSION['user'])) $u = $_SESSION['user'];
    return is_array($u) ? $u : null;
}

// RBAC loader (best-effort)
function _load_user_rbac(PDO $pdo, int $userId, ?int $roleId = null): array {
    $perms = []; $roles = [];
    try {
        // user_roles
        $st = $pdo->query("SHOW TABLES LIKE 'user_roles'");
        if ($st && $st->rowCount()) {
            $q = $pdo->prepare("SELECT r.key_name FROM roles r JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?");
            $q->execute([$userId]);
            $r = $q->fetchAll(PDO::FETCH_COLUMN, 0);
            if ($r) $roles = array_merge($roles, $r);
        } elseif ($roleId) {
            $q = $pdo->prepare("SELECT key_name FROM roles WHERE id = ? LIMIT 1");
            $q->execute([$roleId]);
            $r = $q->fetch(PDO::FETCH_COLUMN);
            if ($r) $roles[] = $r;
        }
        // user_permissions
        $st2 = $pdo->query("SHOW TABLES LIKE 'user_permissions'");
        if ($st2 && $st2->rowCount()) {
            $q2 = $pdo->prepare("SELECT p.key_name FROM permissions p JOIN user_permissions up ON up.permission_id = p.id WHERE up.user_id = ?");
            $q2->execute([$userId]);
            $up = $q2->fetchAll(PDO::FETCH_COLUMN, 0);
            if ($up) $perms = array_merge($perms, $up);
        }
        // role_permissions
        if ($roleId) {
            $q3 = $pdo->prepare("SELECT p.key_name FROM permissions p JOIN role_permissions rp ON rp.permission_id = p.id WHERE rp.role_id = ?");
            $q3->execute([$roleId]);
            $rp = $q3->fetchAll(PDO::FETCH_COLUMN, 0);
            if ($rp) $perms = array_merge($perms, $rp);
        } elseif (!empty($roles)) {
            $in = implode(',', array_fill(0, count($roles), '?'));
            $q4 = $pdo->prepare("SELECT DISTINCT p.key_name FROM permissions p JOIN role_permissions rp ON rp.permission_id = p.id JOIN roles r ON r.id = rp.role_id WHERE r.key_name IN ($in)");
            $q4->execute($roles);
            $rp2 = $q4->fetchAll(PDO::FETCH_COLUMN, 0);
            if ($rp2) $perms = array_merge($perms, $rp2);
        }
    } catch (Throwable $e) {
        if (class_exists('Logger')) Logger::error('RBAC load error: ' . $e->getMessage());
    }
    return ['permissions' => array_values(array_unique($perms)), 'roles' => array_values(array_unique($roles))];
}

// ---------------- GET actions ----------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    _no_cache();

    if ($action === 'logout') {
        // clear session
        unset($_SESSION['user'], $_SESSION['user_id'], $_SESSION['permissions'], $_SESSION['roles']);
        $GLOBALS['ADMIN_USER'] = null;
        // optionally destroy session cookie
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_regenerate_id(true);
        ResponseFormatter::success(['ok' => true, 'message' => 'Logged out']);
        exit;
    }

    if ($action === 'me') {
        $u = _current_user();
        if (!$u) ResponseFormatter::notFound('Not authenticated');
        else ResponseFormatter::success(['ok' => true, 'user' => $u]);
        exit;
    }

    if ($action === 'csrf') {
        if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
        ResponseFormatter::success(['ok' => true, 'csrf' => $_SESSION['csrf_token']]);
        exit;
    }

    if ($action === 'check') {
        $u = _current_user();
        ResponseFormatter::success(['ok' => true, 'authenticated' => (bool)$u, 'user' => $u]);
        exit;
    }

    // ---------------- GOOGLE OAUTH2 CALLBACK ----------------
    if ($action === 'google_callback') {
        $code  = trim((string)($_GET['code']  ?? ''));
        $error = trim((string)($_GET['error'] ?? ''));

        $googleClientId     = getenv('GOOGLE_CLIENT_ID')     ?: (defined('GOOGLE_CLIENT_ID')     ? GOOGLE_CLIENT_ID     : '');
        $googleClientSecret = getenv('GOOGLE_CLIENT_SECRET') ?: (defined('GOOGLE_CLIENT_SECRET') ? GOOGLE_CLIENT_SECRET : '');
        $appUrl             = getenv('APP_URL')               ?: (defined('APP_URL')               ? APP_URL               : '');
        if ($appUrl === '') {
            $secure  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                       || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
            $appUrl  = ($secure ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        }
        $loginUrl = $appUrl . '/frontend/login.php';

        // Use GOOGLE_REDIRECT_URI from env if set (same value used in login.php),
        // otherwise construct it to keep auth-URL and token-exchange in sync.
        $redirectUri = getenv('GOOGLE_REDIRECT_URI') ?: (defined('GOOGLE_REDIRECT_URI') ? GOOGLE_REDIRECT_URI : '');
        if ($redirectUri === '') {
            $redirectUri = $appUrl . '/api/auth?__action=google_callback';
        }

        if ($error !== '' || $code === '') {
            header('Location: ' . $loginUrl . '?google_error=' . urlencode($error ?: 'access_denied'));
            exit;
        }

        // Detect placeholder / unconfigured credentials
        $secretOk = ($googleClientSecret !== ''
                     && stripos($googleClientSecret, 'PUT_YOUR') === false
                     && stripos($googleClientSecret, 'YOUR_')    === false
                     && strlen($googleClientSecret) > 10);
        if ($googleClientId === '' || !$secretOk) {
            header('Location: ' . $loginUrl . '?google_error=server_config');
            exit;
        }

        // Exchange authorization code for access token
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'code'          => $code,
                'client_id'     => $googleClientId,
                'client_secret' => $googleClientSecret,
                'redirect_uri'  => $redirectUri,
                'grant_type'    => 'authorization_code',
            ]),
        ]);
        $tokenRaw = curl_exec($ch);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr || !$tokenRaw) {
            if (class_exists('Logger')) Logger::error('Google token exchange cURL error: ' . $curlErr);
            header('Location: ' . $loginUrl . '?google_error=token_exchange_failed');
            exit;
        }

        $tokenData = json_decode($tokenRaw, true);
        if (empty($tokenData['access_token'])) {
            // Log the actual Google error to help with diagnosis
            $googleErrCode = $tokenData['error']             ?? 'unknown';
            $googleErrDesc = $tokenData['error_description'] ?? '';
            if (class_exists('Logger')) {
                Logger::error(sprintf(
                    'Google token exchange failed: %s — %s (redirect_uri=%s)',
                    $googleErrCode, $googleErrDesc, $redirectUri
                ));
            }
            // Map known Google error codes to friendlier app-level codes
            $appError = match($googleErrCode) {
                'redirect_uri_mismatch' => 'server_config',
                'invalid_client'        => 'server_config',
                default                 => 'no_access_token',
            };
            header('Location: ' . $loginUrl . '?google_error=' . urlencode($appError));
            exit;
        }

        // Retrieve user profile from Google
        $ch2 = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
        curl_setopt_array($ch2, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $tokenData['access_token']],
        ]);
        $userInfoRaw = curl_exec($ch2);
        curl_close($ch2);

        $userInfo = json_decode($userInfoRaw, true);
        if (empty($userInfo['id']) || empty($userInfo['email'])) {
            header('Location: ' . $loginUrl . '?google_error=no_user_info');
            exit;
        }

        $googleSub   = (string)$userInfo['id'];
        $googleEmail = filter_var($userInfo['email'], FILTER_VALIDATE_EMAIL) ? $userInfo['email'] : '';
        $googleName  = trim((string)($userInfo['name'] ?? ''));

        if ($googleEmail === '') {
            header('Location: ' . $loginUrl . '?google_error=invalid_email');
            exit;
        }

        try {
            // Check if this Google account is already linked
            $authStmt = $pdo->prepare(
                'SELECT user_id FROM user_auth_providers WHERE provider = ? AND provider_user_id = ? LIMIT 1'
            );
            $authStmt->execute(['google', $googleSub]);
            $authRow = $authStmt->fetch(PDO::FETCH_ASSOC);

            if ($authRow) {
                $userId = (int)$authRow['user_id'];
            } else {
                // Check if a user with this email already exists
                $emailStmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                $emailStmt->execute([$googleEmail]);
                $existingUser = $emailStmt->fetch(PDO::FETCH_ASSOC);

                if ($existingUser) {
                    $userId = (int)$existingUser['id'];
                } else {
                    // Create a new user from the Google profile
                    $baseUsername = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $googleName) ?: 'user');
                    if (strlen($baseUsername) < 3) $baseUsername = 'user';
                    $username = substr($baseUsername, 0, 45);
                    $counter  = 1;
                    while (true) {
                        $chkStmt = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
                        $chkStmt->execute([$username]);
                        if (!$chkStmt->fetch()) break;
                        $username = substr($baseUsername, 0, 40) . $counter++;
                    }
                    $insStmt = $pdo->prepare(
                        'INSERT INTO users (username, email, is_active, preferred_language, created_at)
                         VALUES (?, ?, 1, ?, NOW())'
                    );
                    $insStmt->execute([$username, $googleEmail, 'en']);
                    $userId = (int)$pdo->lastInsertId();
                }

                // Link this Google account to the user
                $extra   = json_encode([
                    'email_verified' => (bool)($userInfo['verified_email'] ?? false),
                    'name'           => $googleName,
                    'picture'        => $userInfo['picture'] ?? null,
                ]);
                $insAuth = $pdo->prepare(
                    'INSERT INTO user_auth_providers (user_id, provider, provider_user_id, provider_extra)
                     VALUES (?, ?, ?, ?)'
                );
                $insAuth->execute([$userId, 'google', $googleSub, $extra]);
            }

            // Load full user record
            $uLoad = $pdo->prepare(
                'SELECT u.id, u.username, u.email, u.phone, u.preferred_language, u.is_active,
                        tu.role_id, tu.tenant_id
                 FROM users u
                 LEFT JOIN tenant_users tu ON tu.user_id = u.id AND tu.is_active = 1
                 WHERE u.id = ?
                 ORDER BY tu.joined_at DESC
                 LIMIT 1'
            );
            $uLoad->execute([$userId]);
            $uRow = $uLoad->fetch(PDO::FETCH_ASSOC);

            if (!$uRow) {
                header('Location: ' . $loginUrl . '?google_error=user_load_failed');
                exit;
            }

            $rbac = _load_user_rbac($pdo, $userId, isset($uRow['role_id']) ? (int)$uRow['role_id'] : null);

            session_regenerate_id(true);

            $user = [
                'id'                 => (int)$uRow['id'],
                'name'               => $uRow['username'],
                'username'           => $uRow['username'],
                'email'              => $uRow['email'],
                'phone'              => $uRow['phone'] ?? null,
                'role_id'            => isset($uRow['role_id']) ? (int)$uRow['role_id'] : null,
                'tenant_id'          => isset($uRow['tenant_id']) ? (int)$uRow['tenant_id'] : 1,
                'preferred_language' => $uRow['preferred_language'] ?? 'en',
                'is_active'          => (bool)$uRow['is_active'],
                'permissions'        => $rbac['permissions'] ?? [],
                'roles'              => $rbac['roles'] ?? [],
                'permissions_count'  => count($rbac['permissions'] ?? []),
                'roles_count'        => count($rbac['roles'] ?? []),
            ];

            $_SESSION['user_id']     = $user['id'];
            $_SESSION['user']        = $user;
            $_SESSION['permissions'] = $user['permissions'];
            $_SESSION['roles']       = $user['roles'];
            unset($_SESSION['pending_user_id']);
            $GLOBALS['ADMIN_USER']   = $user;

            // Redirect to frontend after successful Google login
            header('Location: ' . $appUrl . '/frontend/public/index.php');
            exit;

        } catch (Throwable $e) {
            if (class_exists('Logger')) Logger::error('Google callback error: ' . $e->getMessage());
            header('Location: ' . $loginUrl . '?google_error=server_error');
            exit;
        }
    }

    // default: accept check for GET /api/auth
    ResponseFormatter::error('Invalid GET action. Use: me, csrf, check or logout', 400);
    exit;
}

// ---------------- POST: login / register ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    _no_cache();

    $payload = _read_payload();
    // Detect action from payload first, then from URL segment
    $postAction = strtolower(trim((string)($payload['action'] ?? '')));
    $routeAction = $action;
    $effectiveAction = ($routeAction !== '' && $routeAction !== 'login' && $routeAction !== 'register')
        ? $routeAction
        : ($postAction ?: ($routeAction ?: 'login'));

    if (!in_array($effectiveAction, ['login', 'register', 'verify_otp', 'resend_verification', 'google_login'], true)) {
        ResponseFormatter::notFound('Auth POST route not found');
        exit;
    }

    // ---------------- REGISTER ----------------
    if ($effectiveAction === 'register') {
        // CSRF check
        $csrfHeader    = trim((string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        $csrfPayload   = trim((string)($payload['csrf_token'] ?? ''));
        $csrfSubmitted = $csrfHeader !== '' ? $csrfHeader : $csrfPayload;
        $csrfSession   = (string)($_SESSION['csrf_token'] ?? '');
        if ($csrfSession === '' || $csrfSubmitted === '' || !hash_equals($csrfSession, $csrfSubmitted)) {
            ResponseFormatter::error('Invalid request. Please reload the page.', 403);
            exit;
        }

        $regUsername = trim((string)($payload['username'] ?? ''));
        $regEmail    = trim((string)($payload['email'] ?? ''));
        $regPassword = (string)($payload['password'] ?? '');
        $regPhone    = trim((string)($payload['phone'] ?? ''));
        $regLang     = preg_replace('/[^a-z\-]/', '', strtolower((string)($payload['preferred_language'] ?? 'en')));

        $errors = [];
        if ($regUsername === '') $errors['username'] = 'Username is required';
        elseif (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $regUsername)) $errors['username'] = 'Username must be 3-50 alphanumeric characters or underscores';
        if ($regEmail === '') $errors['email'] = 'Email is required';
        elseif (!filter_var($regEmail, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Invalid email address';
        if (strlen($regPassword) < 6) $errors['password'] = 'Password must be at least 6 characters';

        if ($errors) {
            ResponseFormatter::error('Validation failed', 422, $errors);
            exit;
        }

        try {
            // Rate-limit: max 5 registrations per IP per hour (exact match is intentional
            // — stricter for abuse prevention; prefix relaxation is only for verification)
            $regIp = (string)($_SERVER['REMOTE_ADDR'] ?? '');
            if ($regIp !== '') {
                $ipRate = $pdo->prepare(
                    'SELECT COUNT(DISTINCT user_id) FROM user_phone_verifications
                      WHERE ip = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)'
                );
                $ipRate->execute([$regIp]);
                if ((int)$ipRate->fetchColumn() >= 5) {
                    ResponseFormatter::error('Too many registration attempts from this network. Please try again later.', 429);
                    exit;
                }
            }

            // Check duplicates
            $chk = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
            $chk->execute([$regUsername, $regEmail]);
            if ($chk->fetch()) {
                ResponseFormatter::error('Username or email already exists', 409);
                exit;
            }

            $hash = password_hash($regPassword, PASSWORD_DEFAULT);
            $ins  = $pdo->prepare(
                'INSERT INTO users (username, email, password_hash, phone, preferred_language, is_active, created_at)
                 VALUES (?, ?, ?, ?, ?, 0, NOW())'
            );
            $ins->execute([$regUsername, $regEmail, $hash, $regPhone ?: null, $regLang ?: 'en']);
            $newId = (int)$pdo->lastInsertId();

            // ---- Device-bound verification link (token never shown to user) ----
            // Raw token: 32 random bytes as hex (64 chars). Only the hash is stored.
            $rawToken    = bin2hex(random_bytes(32));
            $tokenHash   = hash('sha256', $rawToken);

            // Device token: stored in an httpOnly cookie so we can verify the same
            // browser/device opens the activation link.
            $rawDevice   = bin2hex(random_bytes(16));
            $deviceHash  = hash('sha256', $rawDevice);

            $expiresAt   = date('Y-m-d H:i:s', time() + 86400); // 24 hours (for manual link sharing)
            $userAgent   = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512);
            $clientIp    = $regIp;

            // Store verification record (no OTP is persisted in plain text anywhere)
            $insV = $pdo->prepare(
                'INSERT INTO user_phone_verifications
                    (user_id, token_hash, device_hash, session_id, user_agent, ip, expires_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $insV->execute([$newId, $tokenHash, $deviceHash, session_id(), $userAgent, $clientIp, $expiresAt]);
            $verificationRowId = (int)$pdo->lastInsertId();

            // Set device cookie (httpOnly, SameSite=Lax, expires with verification window)
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            if (!headers_sent()) {
                if (PHP_VERSION_ID >= 70300) {
                    setcookie('qz_dvt', $rawDevice,
                        ['expires' => time() + 86400, 'path' => '/', 'httponly' => true,
                         'samesite' => 'Lax', 'secure' => $secure]);
                } else {
                    setcookie('qz_dvt', $rawDevice, time() + 86400, '/', '', $secure, true);
                }
            }

            // Build activation link and send via SMS (link contains raw token, never the code itself)
            $appUrl  = defined('APP_URL') ? APP_URL
                     : (($secure ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
            $activationLink = $appUrl . '/frontend/verify_phone.php?t=' . urlencode($rawToken);

            // Store pending user_id and activation link in session (no OTP in session anymore)
            session_regenerate_id(true);
            // Update the verification record to use the post-regeneration session ID
            if ($verificationRowId > 0) {
                $pdo->prepare('UPDATE user_phone_verifications SET session_id = ? WHERE id = ?')
                    ->execute([session_id(), $verificationRowId]);
            }
            $_SESSION['pending_user_id'] = $newId;
            unset($_SESSION['user_id'], $_SESSION['user'], $_SESSION['pending_otp'], $_SESSION['pending_verify_link']);

            $user = [
                'id'                 => $newId,
                'name'               => $regUsername,
                'username'           => $regUsername,
                'email'              => $regEmail,
                'phone'              => $regPhone ?: null,
                'role_id'            => null,
                'preferred_language' => $regLang ?: 'en',
                'is_active'          => false,
                'permissions'        => [],
                'roles'              => [],
                'permissions_count'  => 0,
                'roles_count'        => 0,
            ];

            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            }
            echo json_encode([
                'ok'              => true,
                'message'         => ($regLang === 'ar')
                    ? 'تم إنشاء الحساب. يمكنك إرسال رابط التفعيل يدوياً.'
                    : 'Account created. You can share the activation link manually.',
                'activation_link' => $activationLink,
                'user'            => $user,
            ]);

            // Flush response to client immediately so the user doesn't wait for SMS
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            } else {
                while (ob_get_level() > 0) { ob_end_flush(); }
                flush();
            }

            // Send SMS after the response has been delivered to the browser
            if ($regPhone) {
                try {
                    if (file_exists(__DIR__ . '/../../../shared/helpers/sms.php')) {
                        require_once __DIR__ . '/../../../shared/helpers/sms.php';
                    }
                    if (class_exists('SMS')) {
                        SMS::setPDO($pdo);
                        SMS::sendVerificationLink($regPhone, $activationLink, $regLang ?: 'ar');
                    }
                } catch (Throwable $smsErr) {
                    if (class_exists('Logger')) Logger::error('SMS send error: ' . $smsErr->getMessage());
                }
            }
            exit;
        } catch (Throwable $e) {
            if (class_exists('Logger')) Logger::error('Register error: ' . $e->getMessage());
            ResponseFormatter::serverError(app_env('debug') ? $e->getMessage() : 'Registration failed');
        }
        exit;
    }

    // ---------------- RESEND VERIFICATION SMS ----------------
    if ($effectiveAction === 'resend_verification') {
        // CSRF check
        $csrfHeader    = trim((string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        $csrfPayload   = trim((string)($payload['csrf_token'] ?? ''));
        $csrfSubmitted = $csrfHeader !== '' ? $csrfHeader : $csrfPayload;
        $csrfSession   = (string)($_SESSION['csrf_token'] ?? '');
        if ($csrfSession === '' || $csrfSubmitted === '' || !hash_equals($csrfSession, $csrfSubmitted)) {
            ResponseFormatter::error('Invalid request. Please reload the page.', 403);
            exit;
        }

        $pendingId = $_SESSION['pending_user_id'] ?? null;
        if (!$pendingId) {
            ResponseFormatter::error('No pending registration found. Please register first.', 400);
            exit;
        }

        try {
            // Fetch user phone
            $uRow = $pdo->prepare('SELECT phone, preferred_language FROM users WHERE id = ? AND is_active = 0 LIMIT 1');
            $uRow->execute([(int)$pendingId]);
            $uData = $uRow->fetch(PDO::FETCH_ASSOC);

            if (!$uData || empty($uData['phone'])) {
                ResponseFormatter::error('User not found or already activated.', 400);
                exit;
            }

            // Rate-limit: max 1 resend per 60 seconds
            $recent = $pdo->prepare(
                'SELECT COUNT(*) FROM user_phone_verifications
                  WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 60 SECOND)'
            );
            $recent->execute([(int)$pendingId]);
            if ((int)$recent->fetchColumn() > 0) {
                ResponseFormatter::error('Please wait 60 seconds before requesting another SMS.', 429);
                exit;
            }

            $rawToken  = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $rawToken);
            $rawDevice = bin2hex(random_bytes(16));
            $deviceHash = hash('sha256', $rawDevice);
            $expiresAt = date('Y-m-d H:i:s', time() + 86400); // 24 hours (for manual link sharing)
            $userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512);
            $clientIp  = (string)($_SERVER['REMOTE_ADDR'] ?? '');

            $insV = $pdo->prepare(
                'INSERT INTO user_phone_verifications
                    (user_id, token_hash, device_hash, session_id, user_agent, ip, expires_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $insV->execute([(int)$pendingId, $tokenHash, $deviceHash, session_id(), $userAgent, $clientIp, $expiresAt]);

            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            if (!headers_sent()) {
                if (PHP_VERSION_ID >= 70300) {
                    setcookie('qz_dvt', $rawDevice,
                        ['expires' => time() + 86400, 'path' => '/', 'httponly' => true,
                         'samesite' => 'Lax', 'secure' => $secure]);
                } else {
                    setcookie('qz_dvt', $rawDevice, time() + 86400, '/', '', $secure, true);
                }
            }

            $appUrl = defined('APP_URL') ? APP_URL
                    : (($secure ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
            $activationLink = $appUrl . '/frontend/verify_phone.php?t=' . urlencode($rawToken);

            $resendLang= preg_replace('/[^a-z\-]/', '', strtolower($uData['preferred_language'] ?: 'ar'));

            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            unset($_SESSION['pending_verify_link']);
            echo json_encode(['ok' => true, 'message' => 'Verification SMS sent.', 'activation_link' => $activationLink, 'phone' => $uData['phone'] ?? '']);

            // Flush response before sending SMS so the client doesn't wait
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            } else {
                while (ob_get_level() > 0) { ob_end_flush(); }
                flush();
            }

            if (file_exists(__DIR__ . '/../../../shared/helpers/sms.php')) {
                require_once __DIR__ . '/../../../shared/helpers/sms.php';
            }
            if (class_exists('SMS')) {
                SMS::setPDO($pdo);
                SMS::sendVerificationLink($uData['phone'], $activationLink, $resendLang);
            }
        } catch (Throwable $e) {
            if (class_exists('Logger')) Logger::error('Resend verification error: ' . $e->getMessage());
            ResponseFormatter::serverError('Failed to resend verification SMS.');
        }
        exit;
    }

    // ---------------- VERIFY OTP ----------------
    if ($effectiveAction === 'verify_otp') {
        $submittedOtp = trim((string)($payload['otp'] ?? ''));

        if ($submittedOtp === '' || !preg_match('/^\d{6}$/', $submittedOtp)) {
            ResponseFormatter::error('OTP must be a 6-digit number', 422);
            exit;
        }

        $sessionOtp     = $_SESSION['pending_otp']          ?? null;
        $sessionUserId  = $_SESSION['pending_user_id']       ?? null;
        $otpExpires     = $_SESSION['pending_otp_expires']   ?? 0;
        $attempts       = (int)($_SESSION['pending_otp_attempts'] ?? 0);

        if (!$sessionOtp || !$sessionUserId) {
            ResponseFormatter::error('No pending verification found. Please register again.', 400);
            exit;
        }

        // Check expiry (15 minutes)
        if (time() > $otpExpires) {
            unset($_SESSION['pending_otp'], $_SESSION['pending_user_id'],
                  $_SESSION['pending_otp_expires'], $_SESSION['pending_otp_attempts']);
            ResponseFormatter::error('OTP has expired. Please register again.', 400);
            exit;
        }

        // Brute-force protection: max 5 attempts
        if ($attempts >= 5) {
            unset($_SESSION['pending_otp'], $_SESSION['pending_user_id'],
                  $_SESSION['pending_otp_expires'], $_SESSION['pending_otp_attempts']);
            ResponseFormatter::error('Too many incorrect attempts. Please register again.', 429);
            exit;
        }

        if ($submittedOtp !== $sessionOtp) {
            $_SESSION['pending_otp_attempts'] = $attempts + 1;
            $remaining = 5 - ($attempts + 1);
            ResponseFormatter::error('Invalid OTP. ' . $remaining . ' attempt(s) remaining.', 401);
            exit;
        }

        try {
            // Activate the user account
            $upd = $pdo->prepare('UPDATE users SET is_active = 1, updated_at = NOW() WHERE id = ? AND is_active = 0');
            $upd->execute([$sessionUserId]);

            if ($upd->rowCount() === 0) {
                // User might already be active or not found
                ResponseFormatter::error('Account could not be activated. It may already be active.', 409);
                exit;
            }

            // Fetch the now-active user
            $rowStmt = $pdo->prepare('SELECT id, username, email, phone, preferred_language, role_id, is_active FROM users WHERE id = ?');
            $rowStmt->execute([$sessionUserId]);
            $userData = $rowStmt->fetch(PDO::FETCH_ASSOC);

            // Clear pending OTP from session and log the user in
            unset($_SESSION['pending_otp'], $_SESSION['pending_user_id'],
                  $_SESSION['pending_otp_expires'], $_SESSION['pending_otp_attempts']);
            session_regenerate_id(true);

            $user = [
                'id'                 => (int)$userData['id'],
                'name'               => $userData['username'],
                'username'           => $userData['username'],
                'email'              => $userData['email'],
                'phone'              => $userData['phone'],
                'role_id'            => $userData['role_id'],
                'preferred_language' => $userData['preferred_language'],
                'is_active'          => true,
                'permissions'        => [],
                'roles'              => [],
                'permissions_count'  => 0,
                'roles_count'        => 0,
            ];
            $_SESSION['user_id']       = $user['id'];
            $_SESSION['user']          = $user;
            $GLOBALS['ADMIN_USER']     = $user;

            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            }
            echo json_encode(['ok' => true, 'message' => 'Account verified and activated', 'user' => $user]);
        } catch (Throwable $e) {
            if (class_exists('Logger')) Logger::error('Verify OTP error: ' . $e->getMessage());
            ResponseFormatter::serverError(app_env('debug') ? $e->getMessage() : 'Verification failed');
        }
        exit;
    }

    // ---------------- LOGIN ----------------
    if ($effectiveAction !== 'google_login') {
    $username = trim((string)($payload['username'] ?? $payload['email'] ?? ''));
    $password = (string)($payload['password'] ?? '');

    if ($username === '' || $password === '') {
        ResponseFormatter::error('Missing credentials', 400);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT u.*, tu.role_id, tu.tenant_id 
            FROM users u 
            LEFT JOIN tenant_users tu ON u.id = tu.user_id 
            WHERE u.username = ? OR u.email = ? 
            LIMIT 1
        ");
        $stmt->execute([$username, $username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            ResponseFormatter::error('Invalid credentials', 401);
            exit;
        }

        $hash = $row['password_hash'] ?? $row['password'] ?? $row['pass'] ?? null;
        $verified = false;
        if ($hash !== null) {
            if (function_exists('password_verify')) $verified = @password_verify($password, $hash);
        } else {
            ResponseFormatter::serverError('Password not found for user');
            exit;
        }

        if (!$verified) {
            ResponseFormatter::error('Invalid credentials', 401);
            exit;
        }

        if (isset($row['is_active']) && !$row['is_active']) {
            ResponseFormatter::error('Account disabled', 403);
            exit;
        }

        // regenerate session id to prevent fixation and ensure Set-Cookie
        session_regenerate_id(true);

        // set session + global user
        $user = [
            'id'                 => isset($row['id']) ? (int)$row['id'] : null,
            'name'               => $row['name'] ?? $row['full_name'] ?? $row['username'] ?? null,
            'username'           => $row['username'] ?? $row['email'] ?? null,
            'email'              => $row['email'] ?? null,
            'role_id'            => isset($row['role_id']) ? (int)$row['role_id'] : null,
            'preferred_language' => $row['preferred_language'] ?? null,
            'is_active'          => !empty($row['is_active']),
        ];

        $rbac = _load_user_rbac($pdo, (int)$user['id'], $user['role_id'] ?? null);
        $user['permissions'] = $rbac['permissions'] ?? [];
        $user['roles'] = $rbac['roles'] ?? [];
        $user['permissions_count'] = count($user['permissions']);
        $user['roles_count'] = count($user['roles']);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user'] = $user;
        $_SESSION['permissions'] = $user['permissions'];
        $_SESSION['roles'] = $user['roles'];
        $GLOBALS['ADMIN_USER'] = $user;

        ResponseFormatter::success(['ok' => true, 'message' => 'Authenticated', 'user' => $user]);
        exit;

    } catch (Throwable $e) {
        if (class_exists('Logger')) Logger::error('Auth error: ' . $e->getMessage());
        ResponseFormatter::serverError(app_env('debug') ? $e->getMessage() : 'Authentication failed');
        exit;
    }
    } // end if ($effectiveAction !== 'google_login')
    // ---------------- GOOGLE LOGIN ----------------
    if ($effectiveAction === 'google_login') {
        $idToken = trim((string)($payload['id_token'] ?? ''));
        if ($idToken === '') {
            ResponseFormatter::error('Missing Google ID token', 400);
            exit;
        }

        // Verify the ID token with Google's tokeninfo endpoint
        $ch = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $tokenInfoRaw = curl_exec($ch);
        $curlErr      = curl_error($ch);
        curl_close($ch);

        if ($curlErr || !$tokenInfoRaw) {
            ResponseFormatter::serverError('Could not reach Google authentication servers. Please try again.');
            exit;
        }

        $tokenInfo = json_decode($tokenInfoRaw, true);
        if (empty($tokenInfo['sub']) || empty($tokenInfo['email'])) {
            ResponseFormatter::error('Invalid Google token', 401);
            exit;
        }

        // Verify the token was issued for our app
        $googleClientId = getenv('GOOGLE_CLIENT_ID') ?: (defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '');
        $tokenAud = $tokenInfo['aud'] ?? '';
        if ($googleClientId !== '' && $tokenAud !== $googleClientId) {
            ResponseFormatter::error('Token audience mismatch', 401);
            exit;
        }

        $googleSub   = (string)$tokenInfo['sub'];
        $googleEmail = filter_var($tokenInfo['email'], FILTER_VALIDATE_EMAIL) ? $tokenInfo['email'] : '';
        $googleName  = trim((string)($tokenInfo['name'] ?? ''));

        if ($googleEmail === '') {
            ResponseFormatter::error('Google account email is missing or invalid', 422);
            exit;
        }

        try {
            // Check if this Google account is already linked
            $authStmt = $pdo->prepare(
                'SELECT user_id FROM user_auth_providers WHERE provider = ? AND provider_user_id = ? LIMIT 1'
            );
            $authStmt->execute(['google', $googleSub]);
            $authRow = $authStmt->fetch(PDO::FETCH_ASSOC);

            if ($authRow) {
                $userId = (int)$authRow['user_id'];
            } else {
                // Check if a user with this email already exists
                $emailStmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                $emailStmt->execute([$googleEmail]);
                $existingUser = $emailStmt->fetch(PDO::FETCH_ASSOC);

                if ($existingUser) {
                    $userId = (int)$existingUser['id'];
                } else {
                    // Create a new user from the Google profile
                    $baseUsername = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $googleName) ?: 'user');
                    if (strlen($baseUsername) < 3) $baseUsername = 'user';
                    $username = substr($baseUsername, 0, 45);
                    $counter  = 1;
                    while (true) {
                        $chkStmt = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
                        $chkStmt->execute([$username]);
                        if (!$chkStmt->fetch()) break;
                        $username = substr($baseUsername, 0, 40) . $counter++;
                    }

                    $insStmt = $pdo->prepare(
                        'INSERT INTO users (username, email, is_active, preferred_language, created_at)
                         VALUES (?, ?, 1, ?, NOW())'
                    );
                    $insStmt->execute([$username, $googleEmail, 'en']);
                    $userId = (int)$pdo->lastInsertId();
                }

                // Link this Google account to the user
                $extra   = json_encode([
                    'email_verified' => (bool)($tokenInfo['email_verified'] ?? false),
                    'name'           => $googleName,
                    'picture'        => $tokenInfo['picture'] ?? null,
                ]);
                $insAuth = $pdo->prepare(
                    'INSERT INTO user_auth_providers (user_id, provider, provider_user_id, provider_extra)
                     VALUES (?, ?, ?, ?)'
                );
                $insAuth->execute([$userId, 'google', $googleSub, $extra]);
            }

            // Load full user record
            $uLoad = $pdo->prepare(
                'SELECT u.id, u.username, u.email, u.phone, u.preferred_language, u.is_active,
                        tu.role_id, tu.tenant_id
                 FROM users u
                 LEFT JOIN tenant_users tu ON tu.user_id = u.id AND tu.is_active = 1
                 WHERE u.id = ?
                 ORDER BY tu.joined_at DESC
                 LIMIT 1'
            );
            $uLoad->execute([$userId]);
            $uRow = $uLoad->fetch(PDO::FETCH_ASSOC);

            if (!$uRow) {
                ResponseFormatter::serverError('User not found after Google authentication');
                exit;
            }

            $rbac = _load_user_rbac($pdo, $userId, isset($uRow['role_id']) ? (int)$uRow['role_id'] : null);

            session_regenerate_id(true);

            $user = [
                'id'                 => (int)$uRow['id'],
                'name'               => $uRow['username'],
                'username'           => $uRow['username'],
                'email'              => $uRow['email'],
                'phone'              => $uRow['phone'] ?? null,
                'role_id'            => isset($uRow['role_id']) ? (int)$uRow['role_id'] : null,
                'tenant_id'          => isset($uRow['tenant_id']) ? (int)$uRow['tenant_id'] : 1,
                'preferred_language' => $uRow['preferred_language'] ?? 'en',
                'is_active'          => (bool)$uRow['is_active'],
                'permissions'        => $rbac['permissions'] ?? [],
                'roles'              => $rbac['roles'] ?? [],
                'permissions_count'  => count($rbac['permissions'] ?? []),
                'roles_count'        => count($rbac['roles'] ?? []),
            ];

            $_SESSION['user_id']     = $user['id'];
            $_SESSION['user']        = $user;
            $_SESSION['permissions'] = $user['permissions'];
            $_SESSION['roles']       = $user['roles'];
            unset($_SESSION['pending_user_id']);
            $GLOBALS['ADMIN_USER']   = $user;

            ResponseFormatter::success(['ok' => true, 'message' => 'Authenticated', 'user' => $user]);
            exit;

        } catch (Throwable $e) {
            if (class_exists('Logger')) Logger::error('Google login error: ' . $e->getMessage());
            ResponseFormatter::serverError('Google sign-in failed. Please try again.');
            exit;
        }
    }
}

// fallback
ResponseFormatter::notFound('Auth route not supported');
exit;