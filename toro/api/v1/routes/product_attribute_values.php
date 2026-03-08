<?php
/**
 * TORO — v1/routes/product_attribute_values.php
 * Product ↔ attribute-values pivot routes
 */

use Shared\Core\Kernel;
use V1\Middleware\AuthMiddleware;
use V1\Middleware\AdminMiddleware;

$baseDir = __DIR__ . '/../modules/ProductAttributeValues';
require_once $baseDir . '/Contracts/ProductAttributeValuesRepositoryInterface.php';
require_once $baseDir . '/Repositories/PdoProductAttributeValuesRepository.php';
require_once $baseDir . '/Services/ProductAttributeValuesService.php';
require_once $baseDir . '/Controllers/ProductAttributeValuesController.php';

$auth  = [AuthMiddleware::class . '@handle'];
$admin = [AuthMiddleware::class . '@handle', AdminMiddleware::class . '@handle'];

// ── Module-level paths (backward compat) ──────────────────────────────────────

Kernel::get('/v1/products/{productId}/attribute-values',
    'ProductAttributeValuesController@index', $auth);

Kernel::post('/v1/products/{productId}/attribute-values',
    'ProductAttributeValuesController@attach', $admin);

Kernel::delete('/v1/products/{productId}/attribute-values/{valueId}',
    'ProductAttributeValuesController@detach', $admin);

Kernel::put('/v1/products/{productId}/attribute-values/sync',
    'ProductAttributeValuesController@sync', $admin);
