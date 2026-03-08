<?php
/**
 * TORO — v1/routes/wishlists.php
 * Wishlist routes
 */

use Shared\Core\Kernel;
use V1\Middleware\AuthMiddleware;

$baseDir = __DIR__ . '/../modules/Wishlists';
require_once $baseDir . '/Contracts/WishlistsRepositoryInterface.php';
require_once $baseDir . '/Repositories/PdoWishlistsRepository.php';
require_once $baseDir . '/Services/WishlistsService.php';
require_once $baseDir . '/Controllers/WishlistsController.php';

$auth = [AuthMiddleware::class . '@handle'];

// ── Module-level paths ─────────────────────────────────────────────────────────

Kernel::get('/v1/users/{userId}/wishlist',
    'WishlistsController@index', $auth);

Kernel::post('/v1/users/{userId}/wishlist',
    'WishlistsController@add', $auth);

Kernel::post('/v1/users/{userId}/wishlist/toggle',
    'WishlistsController@toggle', $auth);

Kernel::delete('/v1/users/{userId}/wishlist/{productId}',
    'WishlistsController@remove', $auth);

Kernel::delete('/v1/users/{userId}/wishlist',
    'WishlistsController@clear', $auth);
