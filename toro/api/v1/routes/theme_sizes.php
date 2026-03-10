<?php
/**
 * TORO — v1/routes/theme_sizes.php
 */
declare(strict_types=1);

$_sizePath = __DIR__ . '/../modules/ThemeSizes';
require_once $_sizePath . '/Contracts/ThemeSizesRepositoryInterface.php';
require_once $_sizePath . '/Repositories/PdoThemeSizesRepository.php';
require_once $_sizePath . '/Services/ThemeSizesService.php';
require_once $_sizePath . '/Controllers/ThemeSizesController.php';
unset($_sizePath);

$_authAdmin = ['V1\Middleware\AuthMiddleware', 'V1\Middleware\AdminMiddleware'];
$_throttle  = ['V1\Middleware\ThrottleMiddleware:60,60'];

// Public read
$router->addRoute('GET', '/v1/theme-sizes',      'ThemeSizesController@index', $_throttle);
$router->addRoute('GET', '/v1/theme-sizes/{id}', 'ThemeSizesController@show',  $_throttle);

// Admin write
$router->addRoute('POST',   '/v1/theme-sizes',      'ThemeSizesController@store',   array_merge($_authAdmin, $_throttle));
$router->addRoute('PUT',    '/v1/theme-sizes/{id}', 'ThemeSizesController@update',  array_merge($_authAdmin, $_throttle));
$router->addRoute('DELETE', '/v1/theme-sizes/{id}', 'ThemeSizesController@destroy', array_merge($_authAdmin, $_throttle));

unset($_authAdmin, $_throttle);
