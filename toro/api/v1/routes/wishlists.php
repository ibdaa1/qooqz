<?php
/**
 * TORO — v1/routes/wishlists.php
 * مسارات المفضلة
 *
 * $router هو instance من Shared\Core\Kernel
 */
declare(strict_types=1);

$_wishPath = __DIR__ . '/../modules/Wishlists';
require_once $_wishPath . '/Contracts/WishlistsRepositoryInterface.php';
require_once $_wishPath . '/Repositories/PdoWishlistsRepository.php';
require_once $_wishPath . '/Services/WishlistsService.php';
require_once $_wishPath . '/Controllers/WishlistsController.php';
unset($_wishPath);

$_authMw = ['V1\Middleware\AuthMiddleware', 'V1\Middleware\ThrottleMiddleware:60,60'];

// GET /v1/users/{userId}/wishlist — قائمة المفضلة
$router->addRoute('GET', '/v1/users/{userId}/wishlist',
    'WishlistsController@index', $_authMw);

// POST /v1/users/{userId}/wishlist — إضافة منتج
$router->addRoute('POST', '/v1/users/{userId}/wishlist',
    'WishlistsController@add', $_authMw);

// POST /v1/users/{userId}/wishlist/toggle — تبديل منتج (إضافة/إزالة)
$router->addRoute('POST', '/v1/users/{userId}/wishlist/toggle',
    'WishlistsController@toggle', $_authMw);

// DELETE /v1/users/{userId}/wishlist/{productId} — إزالة منتج
$router->addRoute('DELETE', '/v1/users/{userId}/wishlist/{productId}',
    'WishlistsController@remove', $_authMw);

// DELETE /v1/users/{userId}/wishlist — مسح كل المفضلة
$router->addRoute('DELETE', '/v1/users/{userId}/wishlist',
    'WishlistsController@clear', $_authMw);

unset($_authMw);
