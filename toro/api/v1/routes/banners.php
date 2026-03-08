<?php
/**
 * TORO — v1/routes/banners.php
 */
declare(strict_types=1);

$_bannerPath = __DIR__ . '/../modules/Banners';
require_once $_bannerPath . '/Contracts/BannersRepositoryInterface.php';
require_once $_bannerPath . '/DTO/CreateBannerDTO.php';
require_once $_bannerPath . '/DTO/UpdateBannerDTO.php';
require_once $_bannerPath . '/Validators/BannersValidator.php';
require_once $_bannerPath . '/Repositories/PdoBannersRepository.php';
require_once $_bannerPath . '/Services/BannersService.php';
require_once $_bannerPath . '/Controllers/BannersController.php';
unset($_bannerPath);

$_authAdmin = ['V1\Middleware\AuthMiddleware', 'V1\Middleware\AdminMiddleware'];

// Public
$router->addRoute('GET', '/v1/banners',                 'BannersController@index',        ['V1\Middleware\ThrottleMiddleware:120,60']);
$router->addRoute('GET', '/v1/banners/{id}',            'BannersController@show',         ['V1\Middleware\ThrottleMiddleware:120,60']);
$router->addRoute('GET', '/v1/banners/{id}/translations','BannersController@translations', ['V1\Middleware\ThrottleMiddleware:120,60']);

// Admin
$router->addRoute('POST',   '/v1/banners',       'BannersController@store',   array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60']));
$router->addRoute('PUT',    '/v1/banners/{id}',  'BannersController@update',  array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60']));
$router->addRoute('DELETE', '/v1/banners/{id}',  'BannersController@destroy', array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:20,60']));

unset($_authAdmin);
