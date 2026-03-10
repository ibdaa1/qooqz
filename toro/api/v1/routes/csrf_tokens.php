<?php
/**
 * TORO — v1/routes/csrf_tokens.php
 */
declare(strict_types=1);

$_path = __DIR__ . '/../modules/CsrfTokens';
require_once $_path . '/Contracts/CsrfTokensRepositoryInterface.php';
require_once $_path . '/Repositories/PdoCsrfTokensRepository.php';
require_once $_path . '/Services/CsrfTokensService.php';
require_once $_path . '/Controllers/CsrfTokensController.php';
unset($_path);

$_authAdmin = ['V1\Middleware\AuthMiddleware', 'V1\Middleware\AdminMiddleware'];
$_throttle  = ['V1\Middleware\ThrottleMiddleware:60,60'];

// CSRF token endpoints (public — not authenticated, used before forms)
$router->addRoute('POST',   '/v1/csrf/generate', 'CsrfTokensController@generate', $_throttle);
$router->addRoute('POST',   '/v1/csrf/verify',   'CsrfTokensController@verify',   $_throttle);
$router->addRoute('DELETE', '/v1/csrf/cleanup',  'CsrfTokensController@cleanup',  array_merge($_authAdmin, $_throttle));

unset($_authAdmin, $_throttle);
