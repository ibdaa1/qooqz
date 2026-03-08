<?php
/**
 * TORO — v1/routes/pages.php
 */
declare(strict_types=1);

$_pagePath = __DIR__ . '/../modules/Pages';
require_once $_pagePath . '/Contracts/PagesRepositoryInterface.php';
require_once $_pagePath . '/DTO/CreatePageDTO.php';
require_once $_pagePath . '/DTO/UpdatePageDTO.php';
require_once $_pagePath . '/Validators/PagesValidator.php';
require_once $_pagePath . '/Repositories/PdoPagesRepository.php';
require_once $_pagePath . '/Services/PagesService.php';
require_once $_pagePath . '/Controllers/PagesController.php';
unset($_pagePath);

$_authAdmin = ['V1\Middleware\AuthMiddleware', 'V1\Middleware\AdminMiddleware'];

// Public
$router->addRoute('GET', '/v1/pages',                  'PagesController@index',        ['V1\Middleware\ThrottleMiddleware:120,60']);
$router->addRoute('GET', '/v1/pages/{id}',             'PagesController@show',         ['V1\Middleware\ThrottleMiddleware:120,60']);
$router->addRoute('GET', '/v1/pages/slug/{slug}',      'PagesController@showBySlug',   ['V1\Middleware\ThrottleMiddleware:120,60']);
$router->addRoute('GET', '/v1/pages/{id}/translations','PagesController@translations', ['V1\Middleware\ThrottleMiddleware:120,60']);

// Admin
$router->addRoute('POST',   '/v1/pages',       'PagesController@store',   array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60']));
$router->addRoute('PUT',    '/v1/pages/{id}',  'PagesController@update',  array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60']));
$router->addRoute('DELETE', '/v1/pages/{id}',  'PagesController@destroy', array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:20,60']));

unset($_authAdmin);
