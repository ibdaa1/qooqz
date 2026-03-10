<?php
/**
 * TORO — v1/routes/products.php
 * مسارات المنتجات (products + product_translations)
 * يتم اكتشاف هذا الملف تلقائياً بواسطة Kernel::loadRoutes() عبر glob
 */
declare(strict_types=1);

$_modulePath = dirname(__DIR__) . '/modules/Products';

require_once $_modulePath . '/Contracts/ProductsRepositoryInterface.php';
require_once $_modulePath . '/DTO/CreateProductDTO.php';
require_once $_modulePath . '/DTO/UpdateProductDTO.php';
require_once $_modulePath . '/Validators/ProductsValidator.php';
require_once $_modulePath . '/Repositories/PdoProductsRepository.php';
require_once $_modulePath . '/Services/ProductsService.php';
require_once $_modulePath . '/Controllers/ProductsController.php';

unset($_modulePath);

$_authAdmin = ['V1\Middleware\AuthMiddleware', 'V1\Middleware\AdminMiddleware'];
$_publicMw  = ['V1\Middleware\ThrottleMiddleware:120,60'];

// ════════════════════════════════════════════════════════════
// PUBLIC — قراءة المنتجات بدون مصادقة
// ════════════════════════════════════════════════════════════

// GET — قائمة المنتجات
$router->addRoute('GET', '/v1/products',
    'ProductsController@index',
    $_publicMw
);

// GET — منتج بالـ ID
$router->addRoute('GET', '/v1/products/{id}',
    'ProductsController@show',
    $_publicMw
);

// GET — منتج بالـ SKU
$router->addRoute('GET', '/v1/products/sku/{sku}',
    'ProductsController@showBySku',
    $_publicMw
);

// GET — ترجمات المنتج
$router->addRoute('GET', '/v1/products/{id}/translations',
    'ProductsController@translations',
    $_publicMw
);

// GET — صور المنتج
$router->addRoute('GET', '/v1/products/{id}/images',
    'ProductsController@images',
    $_publicMw
);

// ════════════════════════════════════════════════════════════
// ADMIN — كتابة المنتجات (تتطلب مصادقة)
// ════════════════════════════════════════════════════════════

// POST — إنشاء منتج
$router->addRoute('POST', '/v1/products',
    'ProductsController@store',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60'])
);

// PUT — تعديل منتج
$router->addRoute('PUT', '/v1/products/{id}',
    'ProductsController@update',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60'])
);

// DELETE — حذف منتج (soft delete)
$router->addRoute('DELETE', '/v1/products/{id}',
    'ProductsController@destroy',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:20,60'])
);

unset($_authAdmin, $_publicMw);
