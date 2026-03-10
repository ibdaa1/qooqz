<?php
/**
 * TORO — v1/routes/menus.php
 * Menus + Menu-Items routes
 */

use V1\Middleware\AuthMiddleware;
use V1\Middleware\AdminMiddleware;

$baseDir = __DIR__ . '/../modules/Menus';
require_once $baseDir . '/Contracts/MenusRepositoryInterface.php';
require_once $baseDir . '/Contracts/MenuItemsRepositoryInterface.php';
require_once $baseDir . '/DTO/CreateMenuDTO.php';
require_once $baseDir . '/DTO/CreateMenuItemDTO.php';
require_once $baseDir . '/Validators/MenusValidator.php';
require_once $baseDir . '/Repositories/PdoMenusRepository.php';
require_once $baseDir . '/Repositories/PdoMenuItemsRepository.php';
require_once $baseDir . '/Services/MenusService.php';
require_once $baseDir . '/Controllers/MenusController.php';

// تعريف middleware للمشرف
$admin = [AuthMiddleware::class, AdminMiddleware::class];

// ── Module-level paths ─────────────────────────────────────────────────────────

// Menus CRUD
$router->addRoute('GET', '/v1/menus', 'MenusController@index', []);
$router->addRoute('GET', '/v1/menus/{id}', 'MenusController@show', []);
$router->addRoute('GET', '/v1/menus/by-slug/{slug}', 'MenusController@showBySlug', []);
$router->addRoute('POST', '/v1/menus', 'MenusController@store', $admin);
$router->addRoute('PATCH', '/v1/menus/{id}', 'MenusController@update', $admin);
$router->addRoute('DELETE', '/v1/menus/{id}', 'MenusController@destroy', $admin);

// Menu Items
$router->addRoute('GET', '/v1/menus/{menuId}/items', 'MenusController@items', []);
$router->addRoute('PUT', '/v1/menus/{menuId}/items/reorder', 'MenusController@reorderItems', $admin);
$router->addRoute('GET', '/v1/menu-items/{id}', 'MenusController@showItem', []);
$router->addRoute('POST', '/v1/menu-items', 'MenusController@storeItem', $admin);
$router->addRoute('PATCH', '/v1/menu-items/{id}', 'MenusController@updateItem', $admin);
$router->addRoute('DELETE', '/v1/menu-items/{id}', 'MenusController@destroyItem', $admin);