<?php
/**
 * TORO — v1/routes/carts.php
 */
declare(strict_types=1);

$_cartPath = __DIR__ . '/../modules/Carts';
require_once $_cartPath . '/Contracts/CartsRepositoryInterface.php';
require_once $_cartPath . '/Repositories/PdoCartsRepository.php';
require_once $_cartPath . '/Services/CartsService.php';
require_once $_cartPath . '/Controllers/CartsController.php';
unset($_cartPath);

$_throttle = ['V1\Middleware\ThrottleMiddleware:60,60'];
$_authUser = ['V1\Middleware\AuthMiddleware'];

// Get or create cart (optional auth)
$router->addRoute('GET', '/v1/carts', 'CartsController@index', $_throttle);

// Cart by ID
$router->addRoute('GET',    '/v1/carts/{id}',              'CartsController@show',        $_throttle);
$router->addRoute('DELETE', '/v1/carts/{id}',              'CartsController@destroy',     array_merge($_authUser, $_throttle));

// Items
$router->addRoute('POST',   '/v1/carts/{id}/items',            'CartsController@addItem',    $_throttle);
$router->addRoute('PUT',    '/v1/carts/{id}/items/{item_id}',  'CartsController@updateItem', $_throttle);
$router->addRoute('DELETE', '/v1/carts/{id}/items/{item_id}',  'CartsController@removeItem', $_throttle);
$router->addRoute('DELETE', '/v1/carts/{id}/items',            'CartsController@clearItems', array_merge($_authUser, $_throttle));

// Coupon
$router->addRoute('PATCH',  '/v1/carts/{id}/coupon', 'CartsController@applyCoupon', $_throttle);

unset($_throttle, $_authUser);
