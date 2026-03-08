<?php
/**
 * TORO — v1/routes/product_variants.php
 * Product variants routes
 */

use Shared\Core\Kernel;
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

$auth  = [AuthMiddleware::class . '@handle'];
$admin = [AuthMiddleware::class . '@handle', AdminMiddleware::class . '@handle'];

// ── Module-level paths ─────────────────────────────────────────────────────────

Kernel::get('/v1/products/{productId}/variants',
    'ProductVariantsController@indexByProduct', $auth);

Kernel::get('/v1/variants/{id}',
    'ProductVariantsController@show', $auth);

Kernel::post('/v1/variants',
    'ProductVariantsController@store', $admin);

Kernel::patch('/v1/variants/{id}',
    'ProductVariantsController@update', $admin);

Kernel::delete('/v1/variants/{id}',
    'ProductVariantsController@destroy', $admin);
