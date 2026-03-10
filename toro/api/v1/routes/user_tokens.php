<?php
/**
 * TORO — v1/routes/user_tokens.php
 */
declare(strict_types=1);

$_tokPath = __DIR__ . '/../modules/UserTokens';
require_once $_tokPath . '/Contracts/UserTokensRepositoryInterface.php';
require_once $_tokPath . '/Repositories/PdoUserTokensRepository.php';
require_once $_tokPath . '/Services/UserTokensService.php';
require_once $_tokPath . '/Controllers/UserTokensController.php';
unset($_tokPath);

$_authAdmin = ['V1\Middleware\AuthMiddleware', 'V1\Middleware\AdminMiddleware'];
$_authUser  = ['V1\Middleware\AuthMiddleware'];
$_throttle  = ['V1\Middleware\ThrottleMiddleware:60,60'];

// Admin: list active tokens for a user
$router->addRoute('GET',    '/v1/users/{user_id}/tokens', 'UserTokensController@index',      array_merge($_authAdmin, $_throttle));
$router->addRoute('DELETE', '/v1/users/{user_id}/tokens', 'UserTokensController@revokeAll',  array_merge($_authAdmin, $_throttle));

// Internal / admin: issue, verify, consume, purge
$router->addRoute('POST',   '/v1/tokens/issue',   'UserTokensController@issue',        array_merge($_authAdmin, $_throttle));
$router->addRoute('POST',   '/v1/tokens/verify',  'UserTokensController@verify',       $_throttle);
$router->addRoute('POST',   '/v1/tokens/consume', 'UserTokensController@consume',      $_throttle);
$router->addRoute('DELETE', '/v1/tokens/expired', 'UserTokensController@purgeExpired', array_merge($_authAdmin, $_throttle));

unset($_authAdmin, $_authUser, $_throttle);
