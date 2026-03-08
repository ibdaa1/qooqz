<?php
/**
 * TORO — v1/routes/attributes.php
 * مسارات السمات وقيمها — /v1/attributes/* و /v1/attribute-values/*
 *
 * هذا الملف يسجّل المسارات الأساسية /v1/attributes/* و /v1/attribute-values/*
 * المسارات ذات البادئة /v1/admin/* موجودة في routes/admin.php
 * المسارات ذات البادئة /v1/public/* موجودة في routes/public.php
 *
 * $router هو instance من Shared\Core\Kernel
 */

declare(strict_types=1);

// ── تحميل ملفات السمات ───────────────────────────────────────
$_attrPath = __DIR__ . '/../modules/Attributes';
require_once $_attrPath . '/Contracts/AttributesRepositoryInterface.php';
require_once $_attrPath . '/Contracts/AttributeValuesRepositoryInterface.php';
require_once $_attrPath . '/DTO/CreateAttributeDTO.php';
require_once $_attrPath . '/DTO/UpdateAttributeDTO.php';
require_once $_attrPath . '/DTO/CreateAttributeValueDTO.php';
require_once $_attrPath . '/DTO/UpdateAttributeValueDTO.php';
require_once $_attrPath . '/Validators/AttributesValidator.php';
require_once $_attrPath . '/Validators/AttributeValuesValidator.php';
require_once $_attrPath . '/Repositories/PdoAttributesRepository.php';
require_once $_attrPath . '/Repositories/PdoAttributeValuesRepository.php';
require_once $_attrPath . '/Services/AttributesService.php';
require_once $_attrPath . '/Services/AttributeValuesService.php';
require_once $_attrPath . '/Controllers/AttributesController.php';
require_once $_attrPath . '/Controllers/AttributeValuesController.php';
unset($_attrPath);

// ════════════════════════════════════════════════════════════
// ATTRIBUTES  →  /v1/attributes/*
// ════════════════════════════════════════════════════════════

// GET /v1/attributes — قائمة السمات (عام)
$router->addRoute('GET', '/v1/attributes',
    'AttributesController@index',
    ['V1\Middleware\ThrottleMiddleware:120,60']
);

// GET /v1/attributes/{id} — سمة بالـ ID (عام)
$router->addRoute('GET', '/v1/attributes/{id}',
    'AttributesController@show',
    ['V1\Middleware\ThrottleMiddleware:120,60']
);

// GET /v1/attributes/slug/{slug} — سمة بالـ slug (عام)
$router->addRoute('GET', '/v1/attributes/slug/{slug}',
    'AttributesController@showBySlug',
    ['V1\Middleware\ThrottleMiddleware:120,60']
);

// GET /v1/attributes/{id}/translations — ترجمات السمة (عام)
$router->addRoute('GET', '/v1/attributes/{id}/translations',
    'AttributesController@translations',
    ['V1\Middleware\ThrottleMiddleware:120,60']
);

// GET /v1/attributes/{id}/values — قيم السمة (عام)
$router->addRoute('GET', '/v1/attributes/{id}/values',
    'AttributesController@values',
    ['V1\Middleware\ThrottleMiddleware:120,60']
);

$_authAdmin = ['V1\Middleware\AuthMiddleware', 'V1\Middleware\AdminMiddleware'];

// POST /v1/attributes — إنشاء سمة
$router->addRoute('POST', '/v1/attributes',
    'AttributesController@store',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60'])
);

// PUT /v1/attributes/{id} — تعديل سمة
$router->addRoute('PUT', '/v1/attributes/{id}',
    'AttributesController@update',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60'])
);

// DELETE /v1/attributes/{id} — حذف سمة
$router->addRoute('DELETE', '/v1/attributes/{id}',
    'AttributesController@destroy',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:20,60'])
);

// ════════════════════════════════════════════════════════════
// ATTRIBUTE VALUES  →  /v1/attribute-values/*
// ════════════════════════════════════════════════════════════

// GET /v1/attribute-values/{id} — قيمة بالـ ID (عام)
$router->addRoute('GET', '/v1/attribute-values/{id}',
    'AttributeValuesController@show',
    ['V1\Middleware\ThrottleMiddleware:120,60']
);

// GET /v1/attribute-values/{id}/translations — ترجمات قيمة (عام)
$router->addRoute('GET', '/v1/attribute-values/{id}/translations',
    'AttributeValuesController@translations',
    ['V1\Middleware\ThrottleMiddleware:120,60']
);

// POST /v1/attribute-values — إنشاء قيمة
$router->addRoute('POST', '/v1/attribute-values',
    'AttributeValuesController@store',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60'])
);

// PUT /v1/attribute-values/{id} — تعديل قيمة
$router->addRoute('PUT', '/v1/attribute-values/{id}',
    'AttributeValuesController@update',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60'])
);

// DELETE /v1/attribute-values/{id} — حذف قيمة
$router->addRoute('DELETE', '/v1/attribute-values/{id}',
    'AttributeValuesController@destroy',
    array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:20,60'])
);

unset($_authAdmin);
