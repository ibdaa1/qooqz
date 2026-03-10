<?php
/**
 * TORO — v1/routes/user_addresses.php
 */
declare(strict_types=1);

$_addrPath = __DIR__ . '/../modules/UserAddresses';
require_once $_addrPath . '/Contracts/UserAddressesRepositoryInterface.php';
require_once $_addrPath . '/Validators/UserAddressesValidator.php';
require_once $_addrPath . '/Repositories/PdoUserAddressesRepository.php';
require_once $_addrPath . '/Services/UserAddressesService.php';
require_once $_addrPath . '/Controllers/UserAddressesController.php';
unset($_addrPath);

$_authAdmin = ['V1\Middleware\AuthMiddleware', 'V1\Middleware\AdminMiddleware'];
$_authUser  = ['V1\Middleware\AuthMiddleware'];
$_throttle  = ['V1\Middleware\ThrottleMiddleware:60,60'];

// Admin: list addresses for any user
$router->addRoute('GET', '/v1/users/{user_id}/addresses', 'UserAddressesController@index', array_merge($_authAdmin, $_throttle));

// Authenticated user: manage own addresses
$router->addRoute('GET',    '/v1/addresses',          'UserAddressesController@index',      array_merge($_authUser, $_throttle));
$router->addRoute('GET',    '/v1/addresses/{id}',     'UserAddressesController@show',       array_merge($_authUser, $_throttle));
$router->addRoute('POST',   '/v1/addresses',          'UserAddressesController@store',      array_merge($_authUser, $_throttle));
$router->addRoute('PUT',    '/v1/addresses/{id}',     'UserAddressesController@update',     array_merge($_authUser, $_throttle));
$router->addRoute('DELETE', '/v1/addresses/{id}',     'UserAddressesController@destroy',    array_merge($_authUser, $_throttle));
$router->addRoute('PATCH',  '/v1/addresses/{id}/default', 'UserAddressesController@setDefault', array_merge($_authUser, $_throttle));

unset($_authAdmin, $_authUser, $_throttle);
