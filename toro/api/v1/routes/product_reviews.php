<?php
/**
 * TORO — v1/routes/product_reviews.php
 * Product reviews routes
 */

use V1\Middleware\AuthMiddleware;
use V1\Middleware\AdminMiddleware;

$baseDir = __DIR__ . '/../modules/ProductReviews';
require_once $baseDir . '/Contracts/ProductReviewsRepositoryInterface.php';
require_once $baseDir . '/DTO/CreateReviewDTO.php';
require_once $baseDir . '/Validators/ProductReviewsValidator.php';
require_once $baseDir . '/Repositories/PdoProductReviewsRepository.php';
require_once $baseDir . '/Services/ProductReviewsService.php';
require_once $baseDir . '/Controllers/ProductReviewsController.php';

$router = $router ?? $this;

$auth  = [AuthMiddleware::class];
$admin = [AuthMiddleware::class, AdminMiddleware::class];

// ── Module-level paths ─────────────────────────────────────────────────────────

$router->addRoute('GET', '/v1/products/{productId}/reviews',
    'ProductReviewsController@indexByProduct', []);          // public listing

$router->addRoute('GET', '/v1/reviews/{id}',
    'ProductReviewsController@show', []);

$router->addRoute('POST', '/v1/reviews',
    'ProductReviewsController@store', $auth);

$router->addRoute('PATCH', '/v1/reviews/{id}',
    'ProductReviewsController@update', $auth);

$router->addRoute('PATCH', '/v1/reviews/{id}/approve',
    'ProductReviewsController@approve', $admin);

$router->addRoute('DELETE', '/v1/reviews/{id}',
    'ProductReviewsController@destroy', $admin);