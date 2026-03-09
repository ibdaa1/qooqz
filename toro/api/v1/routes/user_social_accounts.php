<?php
/**
 * TORO — v1/routes/user_social_accounts.php
 */
declare(strict_types=1);

$_socialPath = __DIR__ . '/../modules/UserSocialAccounts';
require_once $_socialPath . '/Contracts/UserSocialAccountsRepositoryInterface.php';
require_once $_socialPath . '/Repositories/PdoUserSocialAccountsRepository.php';
require_once $_socialPath . '/Services/UserSocialAccountsService.php';
require_once $_socialPath . '/Controllers/UserSocialAccountsController.php';
unset($_socialPath);

$_authAdmin = ['V1\Middleware\AuthMiddleware', 'V1\Middleware\AdminMiddleware'];
$_authUser  = ['V1\Middleware\AuthMiddleware'];
$_throttle  = ['V1\Middleware\ThrottleMiddleware:60,60'];

// Admin: list social accounts for a user
$router->addRoute('GET', '/v1/users/{user_id}/social-accounts', 'UserSocialAccountsController@index', array_merge($_authAdmin, $_throttle));
$router->addRoute('GET', '/v1/social-accounts/{id}',            'UserSocialAccountsController@show',  array_merge($_authAdmin, $_throttle));

// Auth system: upsert / disconnect
$router->addRoute('POST',   '/v1/social-accounts',      'UserSocialAccountsController@upsert',  array_merge($_authAdmin, $_throttle));
$router->addRoute('DELETE', '/v1/social-accounts/{id}', 'UserSocialAccountsController@destroy', array_merge($_authUser,  $_throttle));

unset($_authAdmin, $_authUser, $_throttle);
