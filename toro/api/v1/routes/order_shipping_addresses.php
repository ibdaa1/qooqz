<?php
/**
 * TORO — v1/routes/order_shipping_addresses.php
 */
declare(strict_types=1);

$_path = __DIR__ . '/../modules/OrderShippingAddresses';
require_once $_path . '/Contracts/OrderShippingAddressesRepositoryInterface.php';
require_once $_path . '/Repositories/PdoOrderShippingAddressesRepository.php';
require_once $_path . '/Services/OrderShippingAddressesService.php';
require_once $_path . '/Controllers/OrderShippingAddressesController.php';
unset($_path);

$_authAdmin = ['V1\Middleware\AuthMiddleware', 'V1\Middleware\AdminMiddleware'];
$_authUser  = ['V1\Middleware\AuthMiddleware'];
$_throttle  = ['V1\Middleware\ThrottleMiddleware:60,60'];

// Users/admin can read & set shipping address on their orders
$router->addRoute('GET', '/v1/orders/{orderId}/shipping-address', 'OrderShippingAddressesController@show',   array_merge($_authUser,  $_throttle));
$router->addRoute('PUT', '/v1/orders/{orderId}/shipping-address', 'OrderShippingAddressesController@upsert', array_merge($_authUser,  $_throttle));

// Admin prefixed
$router->addRoute('GET', '/v1/admin/orders/{orderId}/shipping-address', 'OrderShippingAddressesController@show',   array_merge($_authAdmin, $_throttle));
$router->addRoute('PUT', '/v1/admin/orders/{orderId}/shipping-address', 'OrderShippingAddressesController@upsert', array_merge($_authAdmin, $_throttle));

unset($_authAdmin, $_authUser, $_throttle);
