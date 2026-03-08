<?php
/**
 * TORO Perfume Store — Main Entry Point
 * /public_html/toro/api/index.php
 *
 * All HTTP requests are routed through here via .htaccess
 */

declare(strict_types=1);

// ── Absolute base path ───────────────────────────────────────────────────────
define('BASE_PATH', dirname(__FILE__));
define('API_START', microtime(true));

// ── Bootstrap ────────────────────────────────────────────────────────────────
require_once BASE_PATH . '/bootstrap.php';

// ── Run ──────────────────────────────────────────────────────────────────────
$kernel = new \Shared\Core\Kernel();
$kernel->handle();