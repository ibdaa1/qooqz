<?php
/**
 * TORO — v1/routes/orders.php
 */
declare(strict_types=1);

$_orderPath = __DIR__ . '/../modules/Orders';
require_once $_orderPath . '/Contracts/OrdersRepositoryInterface.php';
require_once $_orderPath . '/DTO/CreateOrderDTO.php';
require_once $_orderPath . '/Validators/OrdersValidator.php';
require_once $_orderPath . '/Repositories/PdoOrdersRepository.php';
require_once $_orderPath . '/Services/OrdersService.php';
require_once $_orderPath . '/Controllers/OrdersController.php';
unset($_orderPath);

$_authAdmin = ['V1\Middleware\AuthMiddleware', 'V1\Middleware\AdminMiddleware'];
$_authUser  = ['V1\Middleware\AuthMiddleware'];
$_throttle  = ['V1\Middleware\ThrottleMiddleware:60,60'];

// Authenticated users: list own orders, view, create
$router->addRoute('GET',  '/v1/orders',                    'OrdersController@index',         array_merge($_authUser,  $_throttle));
$router->addRoute('GET',  '/v1/orders/{id}',               'OrdersController@show',          array_merge($_authUser,  $_throttle));
$router->addRoute('GET',  '/v1/orders/number/{number}',    'OrdersController@showByNumber',  array_merge($_authUser,  $_throttle));
$router->addRoute('GET',  '/v1/orders/{id}/history',       'OrdersController@statusHistory', array_merge($_authUser,  $_throttle));
$router->addRoute('POST', '/v1/orders',                    'OrdersController@store',         array_merge($_authUser,  ['V1\Middleware\ThrottleMiddleware:10,60']));

// Admin: update status
$router->addRoute('PATCH', '/v1/orders/{id}/status', 'OrdersController@updateStatus', array_merge($_authAdmin, $_throttle));

unset($_authAdmin, $_authUser, $_throttle);
