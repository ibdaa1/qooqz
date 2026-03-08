<?php
/**
 * TORO — v1/routes/menus.php
 * Menus + Menu-Items routes
 */

use Shared\Core\Kernel;
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

$admin = [AuthMiddleware::class . '@handle', AdminMiddleware::class . '@handle'];

// ── Module-level paths ─────────────────────────────────────────────────────────

// Menus CRUD
Kernel::get('/v1/menus',             'MenusController@index',        []);
Kernel::get('/v1/menus/{id}',        'MenusController@show',         []);
Kernel::get('/v1/menus/by-slug/{slug}', 'MenusController@showBySlug', []);
Kernel::post('/v1/menus',            'MenusController@store',        $admin);
Kernel::patch('/v1/menus/{id}',      'MenusController@update',       $admin);
Kernel::delete('/v1/menus/{id}',     'MenusController@destroy',      $admin);

// Menu Items
Kernel::get('/v1/menus/{menuId}/items',    'MenusController@items',      []);
Kernel::put('/v1/menus/{menuId}/items/reorder', 'MenusController@reorderItems', $admin);
Kernel::get('/v1/menu-items/{id}',         'MenusController@showItem',   []);
Kernel::post('/v1/menu-items',             'MenusController@storeItem',  $admin);
Kernel::patch('/v1/menu-items/{id}',       'MenusController@updateItem', $admin);
Kernel::delete('/v1/menu-items/{id}',      'MenusController@destroyItem', $admin);
