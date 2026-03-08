<?php
/**
 * TORO — v1/routes/product_reviews.php
 * Product reviews routes
 */

use Shared\Core\Kernel;
use V1\Middleware\AuthMiddleware;
use V1\Middleware\AdminMiddleware;

$baseDir = __DIR__ . '/../modules/ProductReviews';
require_once $baseDir . '/Contracts/ProductReviewsRepositoryInterface.php';
require_once $baseDir . '/DTO/CreateReviewDTO.php';
require_once $baseDir . '/Validators/ProductReviewsValidator.php';
require_once $baseDir . '/Repositories/PdoProductReviewsRepository.php';
require_once $baseDir . '/Services/ProductReviewsService.php';
require_once $baseDir . '/Controllers/ProductReviewsController.php';

$auth  = [AuthMiddleware::class . '@handle'];
$admin = [AuthMiddleware::class . '@handle', AdminMiddleware::class . '@handle'];

// ── Module-level paths ─────────────────────────────────────────────────────────

Kernel::get('/v1/products/{productId}/reviews',
    'ProductReviewsController@indexByProduct', []);          // public listing

Kernel::get('/v1/reviews/{id}',
    'ProductReviewsController@show', []);

Kernel::post('/v1/reviews',
    'ProductReviewsController@store', $auth);

Kernel::patch('/v1/reviews/{id}',
    'ProductReviewsController@update', $auth);

Kernel::patch('/v1/reviews/{id}/approve',
    'ProductReviewsController@approve', $admin);

Kernel::delete('/v1/reviews/{id}',
    'ProductReviewsController@destroy', $admin);
