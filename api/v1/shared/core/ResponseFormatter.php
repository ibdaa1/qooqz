<?php
declare(strict_types=1);

/**
 * api/v1/shared/core/ResponseFormatter.php - Compatibility stub
 *
 * Route files reference $baseDir . '/shared/core/ResponseFormatter.php'
 * where $baseDir = api/v1/. The real class is at api/shared/core/ResponseFormatter.php
 * and is loaded by the main bootstrap. This stub ensures no fatal error occurs
 * when the route file tries to require_once this path.
 */
if (!class_exists('ResponseFormatter')) {
    require_once defined('API_BASE_PATH')
        ? API_BASE_PATH . '/shared/core/ResponseFormatter.php'
        : dirname(dirname(dirname(__DIR__))) . '/shared/core/ResponseFormatter.php';
}
