<?php
/**
 * TORO — v1/routes/rate_limits.php
 */
declare(strict_types=1);

$_path = __DIR__ . '/../modules/RateLimits';
require_once $_path . '/Contracts/RateLimitsRepositoryInterface.php';
require_once $_path . '/Repositories/PdoRateLimitsRepository.php';
require_once $_path . '/Services/RateLimitsService.php';
require_once $_path . '/Controllers/RateLimitsController.php';
unset($_path);

$_authAdmin = ['V1\Middleware\AuthMiddleware', 'V1\Middleware\AdminMiddleware'];
$_throttle  = ['V1\Middleware\ThrottleMiddleware:60,60'];

// Rate Limits (admin only)
$router->addRoute('GET',    '/v1/rate-limits/{key}',         'RateLimitsController@show',     array_merge($_authAdmin, $_throttle));
$router->addRoute('GET',    '/v1/rate-limits/{key}/blocked', 'RateLimitsController@isBlocked',array_merge($_authAdmin, $_throttle));
$router->addRoute('POST',   '/v1/rate-limits/increment',     'RateLimitsController@increment',array_merge($_authAdmin, $_throttle));
$router->addRoute('POST',   '/v1/rate-limits/block',         'RateLimitsController@block',    array_merge($_authAdmin, $_throttle));
$router->addRoute('DELETE', '/v1/rate-limits/{key}',         'RateLimitsController@reset',    array_merge($_authAdmin, $_throttle));
$router->addRoute('DELETE', '/v1/rate-limits/cleanup',       'RateLimitsController@cleanup',  array_merge($_authAdmin, $_throttle));

unset($_authAdmin, $_throttle);
