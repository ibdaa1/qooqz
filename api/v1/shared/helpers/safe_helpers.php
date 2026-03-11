<?php
declare(strict_types=1);

/**
 * api/v1/shared/helpers/safe_helpers.php - Compatibility stub
 *
 * Route files reference $baseDir . '/shared/helpers/safe_helpers.php'
 * where $baseDir resolves to api/v1/. The real file is at api/shared/helpers/safe_helpers.php.
 *
 * We use a dedicated constant as a load-guard so that all functions defined
 * in the real file are always loaded together on the first inclusion, regardless
 * of which individual function might already exist from another source.
 */
if (!defined('_SAFE_HELPERS_LOADED')) {
    require_once defined('API_BASE_PATH')
        ? API_BASE_PATH . '/shared/helpers/safe_helpers.php'
        : dirname(dirname(dirname(__DIR__))) . '/shared/helpers/safe_helpers.php';
}
