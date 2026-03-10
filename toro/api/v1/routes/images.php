<?php
/**
 * TORO — v1/routes/images.php
 * مسارات الصور الموحدة (image_types + images)
 * يتم اكتشاف هذا الملف تلقائياً بواسطة Kernel::loadRoutes() عبر glob
 */
declare(strict_types=1);

$_modulePath = dirname(__DIR__) . '/modules/Images';

require_once $_modulePath . '/Contracts/ImageTypesRepositoryInterface.php';
require_once $_modulePath . '/Contracts/ImagesRepositoryInterface.php';
require_once $_modulePath . '/DTO/CreateImageTypeDTO.php';
require_once $_modulePath . '/DTO/UpdateImageTypeDTO.php';
require_once $_modulePath . '/DTO/CreateImageDTO.php';
require_once $_modulePath . '/DTO/UpdateImageDTO.php';
require_once $_modulePath . '/Validators/ImageTypesValidator.php';
require_once $_modulePath . '/Validators/ImagesValidator.php';
require_once $_modulePath . '/Repositories/PdoImageTypesRepository.php';
require_once $_modulePath . '/Repositories/PdoImagesRepository.php';
require_once $_modulePath . '/Services/ImageTypesService.php';
require_once $_modulePath . '/Services/ImagesService.php';
require_once $_modulePath . '/Controllers/ImageTypesController.php';
require_once $_modulePath . '/Controllers/ImagesController.php';

unset($_modulePath);

$_authAdmin = ['V1\Middleware\AuthMiddleware', 'V1\Middleware\AdminMiddleware'];
$_authUser  = ['V1\Middleware\AuthMiddleware'];
$_publicMw  = ['V1\Middleware\ThrottleMiddleware:120,60'];

// ════════════════════════════════════════════════════════════
// IMAGE TYPES
// ════════════════════════════════════════════════════════════

// GET — قائمة أنواع الصور (عام)
$router->addRoute('GET', '/v1/image-types',
    'ImageTypesController@index',
    $_publicMw
);

// GET — نوع صورة بالـ ID (عام)
$router->addRoute('GET', '/v1/image-types/{id}',
    'ImageTypesController@show',
    $_publicMw
);

// POST — إنشاء نوع صورة (أدمن)
$router->addRoute('POST', '/v1/image-types',
    'ImageTypesController@store',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60'])
);

// PUT — تعديل نوع صورة (أدمن)
$router->addRoute('PUT', '/v1/image-types/{id}',
    'ImageTypesController@update',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60'])
);

// DELETE — حذف نوع صورة (أدمن)
$router->addRoute('DELETE', '/v1/image-types/{id}',
    'ImageTypesController@destroy',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:20,60'])
);

// ════════════════════════════════════════════════════════════
// IMAGES
// ════════════════════════════════════════════════════════════

// GET — قائمة الصور (مصادق)
$router->addRoute('GET', '/v1/images',
    'ImagesController@index',
    $_authUser
);

// GET — صورة بالـ ID
$router->addRoute('GET', '/v1/images/{id}',
    'ImagesController@show',
    $_authUser
);

// GET — صور مالك معين
$router->addRoute('GET', '/v1/images/owner/{owner_id}',
    'ImagesController@byOwner',
    $_publicMw
);

// POST — رفع صورة (multipart)
$router->addRoute('POST', '/v1/images/upload',
    'ImagesController@upload',
    array_merge($_authUser, ['V1\Middleware\ThrottleMiddleware:30,60'])
);

// POST — إنشاء سجل صورة بالرابط (JSON)
$router->addRoute('POST', '/v1/images',
    'ImagesController@store',
    array_merge($_authUser, ['V1\Middleware\ThrottleMiddleware:30,60'])
);

// PUT — تعديل صورة
$router->addRoute('PUT', '/v1/images/{id}',
    'ImagesController@update',
    array_merge($_authUser, ['V1\Middleware\ThrottleMiddleware:30,60'])
);

// DELETE — حذف صورة
$router->addRoute('DELETE', '/v1/images/{id}',
    'ImagesController@destroy',
    array_merge($_authUser, ['V1\Middleware\ThrottleMiddleware:20,60'])
);

// PATCH — تعيين صورة رئيسية
$router->addRoute('PATCH', '/v1/images/{id}/set-main',
    'ImagesController@setMain',
    array_merge($_authUser, ['V1\Middleware\ThrottleMiddleware:30,60'])
);

unset($_authAdmin, $_authUser, $_publicMw);
