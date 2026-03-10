<?php
declare(strict_types=1);

/**
 * api/v1/bootstrap.php - Compatibility stub
 *
 * Route files in api/v1/routes/ compute:
 *   $baseDir = dirname(__DIR__)  =>  api/v1/
 * and then require $baseDir . '/bootstrap.php'.
 *
 * The real bootstrap lives one level up at api/bootstrap.php.
 * When accessed via the normal entry point (api/index.php → Kernel → route file),
 * the real bootstrap is already loaded, so this is a no-op.
 * If a route file is executed in any other context, we load the real bootstrap here.
 */
if (!defined('API_BASE_PATH')) {
    require_once dirname(__DIR__) . '/bootstrap.php';
}
