<?php
declare(strict_types=1);

/**
 * api/v1/shared/helpers/SeoAutoManager.php - Compatibility stub
 *
 * Route files reference $baseDir . '/shared/helpers/SeoAutoManager.php'
 * where $baseDir resolves to api/v1/. The real file is at api/shared/helpers/SeoAutoManager.php.
 */
if (!class_exists('SeoAutoManager')) {
    require_once defined('API_BASE_PATH')
        ? API_BASE_PATH . '/shared/helpers/SeoAutoManager.php'
        : dirname(dirname(dirname(__DIR__))) . '/shared/helpers/SeoAutoManager.php';
}
