<?php
/**
 * TORO — bootstrap.php
 * /public_html/toro/api/bootstrap.php
 *
 * Loaded by index.php FIRST.
 * Responsible for:
 *   1. PHP settings & error reporting
 *   2. Autoloader (PSR-4 style, no Composer required)
 *   3. .env loading
 *   4. Config / DB / Core / Helpers
 *   5. Global exception handler
 *   6. CORS headers
 */

declare(strict_types=1);

// ── 1. PHP Settings ──────────────────────────────────────────────────────────
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
date_default_timezone_set('UTC');   // overridden by settings after DB boot

// ── 2. PSR-4 Auto-loader ─────────────────────────────────────────────────────
/**
 * Namespace map:
 *   Shared\Config\     → shared/config/
 *   Shared\Core\       → shared/core/
 *   Shared\Helpers\    → shared/helpers/
 *   Shared\Application → shared/application/
 *   Shared\Domain\     → shared/domain/
 *   Shared\Infrastructure\ → shared/infrastructure/
 *   V1\                → v1/
 */
spl_autoload_register(function (string $class): void {
    $namespaceMap = [
        'Shared\\Config\\'          => BASE_PATH . '/shared/config/',
        'Shared\\Core\\'            => BASE_PATH . '/shared/core/',
        'Shared\\Helpers\\'         => BASE_PATH . '/shared/helpers/',
        'Shared\\Application\\'     => BASE_PATH . '/shared/application/',
        'Shared\\Domain\\'          => BASE_PATH . '/shared/domain/',
        'Shared\\Infrastructure\\'  => BASE_PATH . '/shared/infrastructure/',
        'V1\\'                      => BASE_PATH . '/v1/',
        'Admin\\'                   => BASE_PATH . '/admin/',
    ];

    foreach ($namespaceMap as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($class, $prefix, $len) !== 0) {
            continue;
        }
        $relative = substr($class, $len);
        $file     = $baseDir . str_replace('\\', '/', $relative) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// ── 3. Load .env ─────────────────────────────────────────────────────────────
require_once BASE_PATH . '/shared/helpers/env_helper.php';
loadEnv(BASE_PATH . '/shared/config/.env');

// ── 4. Constants ─────────────────────────────────────────────────────────────
require_once BASE_PATH . '/shared/config/constants.php';

// ── 5. Config ─────────────────────────────────────────────────────────────────
require_once BASE_PATH . '/shared/config/config.php';

// ── 6. Database connection (singleton via shared/core/DatabaseConnection) ────
//   Your existing DatabaseConnection.php is already in shared/core/ ✓
//   We just warm it up here so it fails fast on misconfiguration.
try {
    \Shared\Core\DatabaseConnection::getInstance();
} catch (\Throwable $e) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'code'    => 503,
        'message' => 'Database unavailable',
    ]);
    exit;
}

// ── 7. Global exception / error handler ──────────────────────────────────────
\Shared\Core\ExceptionHandler::register();

// ── 8. CORS (defined in shared/config/cors.php) ───────────────────────────────
require_once BASE_PATH . '/shared/config/cors.php';
applyCorsHeaders();

// ── 9. Detect request context and load appropriate bootstrap extension ────────
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestPath = rtrim(str_replace('/toro/api', '', $requestPath), '/') ?: '/';

if (str_starts_with($requestPath, '/admin')) {
    require_once BASE_PATH . '/bootstrap_admin_context.php';
} elseif (isset($_SERVER['HTTP_X_MOBILE_APP'])) {
    require_once BASE_PATH . '/bootstrap_mobile_context.php';
} else {
    require_once BASE_PATH . '/bootstrap_public_ui.php';
}
