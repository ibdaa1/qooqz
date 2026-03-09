<?php
/**
 * TORO — v1/routes/users.php
 */
declare(strict_types=1);

$_usersPath = __DIR__ . '/../modules/Users';
require_once $_usersPath . '/Contracts/UsersRepositoryInterface.php';
require_once $_usersPath . '/Validators/UsersValidator.php';
require_once $_usersPath . '/Repositories/PdoUsersRepository.php';
require_once $_usersPath . '/Services/UsersService.php';
require_once $_usersPath . '/Controllers/UsersController.php';
unset($_usersPath);

$_authAdmin = ['V1\Middleware\AuthMiddleware', 'V1\Middleware\AdminMiddleware'];
$_authUser  = ['V1\Middleware\AuthMiddleware'];
$_throttle  = ['V1\Middleware\ThrottleMiddleware:60,60'];

// Admin: full user management
$router->addRoute('GET',    '/v1/users',               'UsersController@index',   array_merge($_authAdmin, $_throttle));
$router->addRoute('GET',    '/v1/users/{id}',          'UsersController@show',    array_merge($_authAdmin, $_throttle));
$router->addRoute('POST',   '/v1/users',               'UsersController@store',   array_merge($_authAdmin, $_throttle));
$router->addRoute('PUT',    '/v1/users/{id}',          'UsersController@update',  array_merge($_authAdmin, $_throttle));
$router->addRoute('DELETE', '/v1/users/{id}',          'UsersController@destroy', array_merge($_authAdmin, $_throttle));
$router->addRoute('POST',   '/v1/users/{id}/restore',  'UsersController@restore', array_merge($_authAdmin, $_throttle));

// Self-service: authenticated user updates own profile
$router->addRoute('GET', '/v1/users/me',     'UsersController@show',   array_merge($_authUser, $_throttle));
$router->addRoute('PUT', '/v1/users/me',     'UsersController@update', array_merge($_authUser, $_throttle));

unset($_authAdmin, $_authUser, $_throttle);
