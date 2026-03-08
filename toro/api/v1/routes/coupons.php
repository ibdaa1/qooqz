<?php
/**
 * TORO — v1/routes/coupons.php
 */
declare(strict_types=1);

$_couponPath = __DIR__ . '/../modules/Coupons';
require_once $_couponPath . '/Contracts/CouponsRepositoryInterface.php';
require_once $_couponPath . '/DTO/CreateCouponDTO.php';
require_once $_couponPath . '/DTO/UpdateCouponDTO.php';
require_once $_couponPath . '/Validators/CouponsValidator.php';
require_once $_couponPath . '/Repositories/PdoCouponsRepository.php';
require_once $_couponPath . '/Services/CouponsService.php';
require_once $_couponPath . '/Controllers/CouponsController.php';
unset($_couponPath);

$_authAdmin = ['V1\Middleware\AuthMiddleware', 'V1\Middleware\AdminMiddleware'];
$_authUser  = ['V1\Middleware\AuthMiddleware'];

// Validate coupon (authenticated users)
$router->addRoute('GET', '/v1/coupons/validate', 'CouponsController@validate', array_merge($_authUser, ['V1\Middleware\ThrottleMiddleware:30,60']));

// Admin
$router->addRoute('GET',    '/v1/coupons',                  'CouponsController@index',        array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:60,60']));
$router->addRoute('GET',    '/v1/coupons/{id}',             'CouponsController@show',         array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:60,60']));
$router->addRoute('GET',    '/v1/coupons/{id}/translations','CouponsController@translations', array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:60,60']));
$router->addRoute('POST',   '/v1/coupons',                  'CouponsController@store',        array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60']));
$router->addRoute('PUT',    '/v1/coupons/{id}',             'CouponsController@update',       array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60']));
$router->addRoute('DELETE', '/v1/coupons/{id}',             'CouponsController@destroy',      array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:20,60']));

unset($_authAdmin, $_authUser);
