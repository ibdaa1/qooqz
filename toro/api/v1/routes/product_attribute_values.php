<?php
/**
 * TORO — v1/routes/product_attribute_values.php
 * Product ↔ attribute-values pivot routes
 */

use V1\Middleware\AuthMiddleware;
use V1\Middleware\AdminMiddleware;

$baseDir = __DIR__ . '/../modules/ProductAttributeValues';
require_once $baseDir . '/Contracts/ProductAttributeValuesRepositoryInterface.php';
require_once $baseDir . '/Repositories/PdoProductAttributeValuesRepository.php';
require_once $baseDir . '/Services/ProductAttributeValuesService.php';
require_once $baseDir . '/Controllers/ProductAttributeValuesController.php';

$auth  = [AuthMiddleware::class];
$admin = [AuthMiddleware::class, AdminMiddleware::class];

// ── Module-level paths (backward compat) ──────────────────────────────────────

$router->addRoute('GET', '/v1/products/{productId}/attribute-values',
    'ProductAttributeValuesController@index', $auth);

$router->addRoute('POST', '/v1/products/{productId}/attribute-values',
    'ProductAttributeValuesController@attach', $admin);

$router->addRoute('DELETE', '/v1/products/{productId}/attribute-values/{valueId}',
    'ProductAttributeValuesController@detach', $admin);

$router->addRoute('PUT', '/v1/products/{productId}/attribute-values/sync',
    'ProductAttributeValuesController@sync', $admin);