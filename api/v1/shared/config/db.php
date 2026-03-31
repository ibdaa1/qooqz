<?php
declare(strict_types=1);

/**
 * api/v1/shared/config/db.php - Compatibility stub
 *
 * Route files reference $baseDir . '/shared/config/db.php'
 * where $baseDir resolves to api/v1/. The real file is at api/shared/config/db.php
 * and is already executed by the main bootstrap (which sets up $GLOBALS['ADMIN_DB']).
 * This stub ensures no fatal error occurs; it re-runs db.php only if the connection
 * has not been established yet.
 */
if (!isset($GLOBALS['ADMIN_DB']) || !($GLOBALS['ADMIN_DB'] instanceof PDO)) {
    $realDbConfig = defined('API_BASE_PATH')
        ? API_BASE_PATH . '/shared/config/db.php'
        : dirname(dirname(dirname(__DIR__))) . '/shared/config/db.php';
    if (is_file($realDbConfig)) {
        require_once $realDbConfig;
    }
}
