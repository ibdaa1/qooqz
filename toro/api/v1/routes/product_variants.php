<?php
/**
 * TORO — v1/routes/product_variants.php
 * Product variants routes
 */

use V1\Middleware\AuthMiddleware;
use V1\Middleware\AdminMiddleware;

$baseDir = __DIR__ . '/../modules/ProductVariants';
require_once $baseDir . '/Contracts/ProductVariantsRepositoryInterface.php';
require_once $baseDir . '/DTO/CreateVariantDTO.php';
require_once $baseDir . '/DTO/UpdateVariantDTO.php';
require_once $baseDir . '/Validators/ProductVariantsValidator.php';
require_once $baseDir . '/Repositories/PdoProductVariantsRepository.php';
require_once $baseDir . '/Services/ProductVariantsService.php';
require_once $baseDir . '/Controllers/ProductVariantsController.php';

// ضمان وجود المتغير $router
$router = $router ?? $this;

// تعريف middleware (بدون @handle لأن addRoute يتوقع اسم الكلاس فقط)
$auth  = [AuthMiddleware::class];
$admin = [AuthMiddleware::class, AdminMiddleware::class];

// ── Module-level paths ─────────────────────────────────────────────────────────

$router->addRoute('GET', '/v1/products/{productId}/variants',
    'ProductVariantsController@indexByProduct', $auth);

$router->addRoute('GET', '/v1/variants/{id}',
    'ProductVariantsController@show', $auth);

$router->addRoute('POST', '/v1/variants',
    'ProductVariantsController@store', $admin);

$router->addRoute('PATCH', '/v1/variants/{id}',
    'ProductVariantsController@update', $admin);

$router->addRoute('DELETE', '/v1/variants/{id}',
    'ProductVariantsController@destroy', $admin);