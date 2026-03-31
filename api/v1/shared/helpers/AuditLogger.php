<?php
declare(strict_types=1);

/**
 * api/v1/shared/helpers/AuditLogger.php - Compatibility stub
 *
 * Route files reference $baseDir . '/shared/helpers/AuditLogger.php'
 * where $baseDir resolves to api/v1/. The real file is at api/shared/helpers/AuditLogger.php.
 */
if (!class_exists('AuditLogger')) {
    require_once defined('API_BASE_PATH')
        ? API_BASE_PATH . '/shared/helpers/AuditLogger.php'
        : dirname(dirname(dirname(__DIR__))) . '/shared/helpers/AuditLogger.php';
}
