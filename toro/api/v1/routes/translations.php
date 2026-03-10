<?php
/**
 * TORO — v1/routes/translations.php
 * مسارات الترجمات
 *
 * Prefixes:
 *   /v1/public/translations — بدون مصادقة
 *   /v1/admin/translations  — يتطلب Auth + Admin
 *
 * $router هو instance من Shared\Core\Kernel
 */

declare(strict_types=1);

// ── تحميل ملفات الترجمات ────────────────────────────────────
$_transPath = __DIR__ . '/../modules/Translations';
require_once $_transPath . '/Contracts/TranslationsRepositoryInterface.php';
require_once $_transPath . '/DTO/CreateTranslationKeyDTO.php';
require_once $_transPath . '/DTO/UpdateTranslationKeyDTO.php';
require_once $_transPath . '/DTO/UpsertTranslationValueDTO.php';
require_once $_transPath . '/Validators/TranslationsValidator.php';
require_once $_transPath . '/Repositories/PdoTranslationsRepository.php';
require_once $_transPath . '/Services/TranslationsService.php';
require_once $_transPath . '/Controllers/TranslationsController.php';
unset($_transPath);

$_publicMw  = ['V1\Middleware\ThrottleMiddleware:120,60'];
$_authAdmin = ['V1\Middleware\AuthMiddleware', 'V1\Middleware\AdminMiddleware'];

// ════════════════════════════════════════════════════════════
// PUBLIC ROUTES  →  /v1/public/translations
// ════════════════════════════════════════════════════════════

// GET — ترجمة واحدة (بالمفتاح واللغة)
foreach (['/v1/translations/{key}', '/v1/public/translations/{key}'] as $_path) {
    $router->addRoute('GET', $_path, 'TranslationsController@getByKey', $_publicMw);
}

// GET — ترجمات متعددة (بـ query string keys[])
foreach (['/v1/translations', '/v1/public/translations'] as $_path) {
    $router->addRoute('GET', $_path, 'TranslationsController@getBulk', $_publicMw);
}

// GET — جميع المفاتيح (مع فلترة باللغة)
foreach (['/v1/translations/keys', '/v1/public/translations/keys'] as $_path) {
    $router->addRoute('GET', $_path, 'TranslationsController@listKeys', $_publicMw);
}

// GET — جميع قيم لغة محددة (كاملة)
foreach (['/v1/translations/lang/{code}', '/v1/public/translations/lang/{code}'] as $_path) {
    $router->addRoute('GET', $_path, 'TranslationsController@getLanguagePack', $_publicMw);
}

// ════════════════════════════════════════════════════════════
// ADMIN ROUTES  →  /v1/admin/translations
// ════════════════════════════════════════════════════════════

// POST — إنشاء مفتاح ترجمة جديد
foreach (['/v1/translations/keys', '/v1/admin/translations/keys'] as $_path) {
    $router->addRoute('POST', $_path, 'TranslationsController@storeKey',
        array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60']));
}

// PUT — تحديث مفتاح ترجمة
foreach (['/v1/translations/keys/{id}', '/v1/admin/translations/keys/{id}'] as $_path) {
    $router->addRoute('PUT', $_path, 'TranslationsController@updateKey',
        array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60']));
}

// DELETE — حذف مفتاح ترجمة
foreach (['/v1/translations/keys/{id}', '/v1/admin/translations/keys/{id}'] as $_path) {
    $router->addRoute('DELETE', $_path, 'TranslationsController@destroyKey',
        array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:20,60']));
}

// POST — إنشاء/تحديث قيمة ترجمة (upsert)
foreach (['/v1/translations/values', '/v1/admin/translations/values'] as $_path) {
    $router->addRoute('POST', $_path, 'TranslationsController@upsertValue',
        array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:50,60']));
}

// DELETE — حذف قيمة ترجمة (لغة محددة لمفتاح محدد)
foreach (['/v1/translations/values/{key_id}/{lang_id}', '/v1/admin/translations/values/{key_id}/{lang_id}'] as $_path) {
    $router->addRoute('DELETE', $_path, 'TranslationsController@destroyValue',
        array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:20,60']));
}

// POST — استيراد ملف ترجمات (JSON)
foreach (['/v1/translations/import', '/v1/admin/translations/import'] as $_path) {
    $router->addRoute('POST', $_path, 'TranslationsController@import',
        array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:10,120']));
}

// GET — تصدير ملف ترجمات (JSON)
foreach (['/v1/translations/export/{code}', '/v1/admin/translations/export/{code}'] as $_path) {
    $router->addRoute('GET', $_path, 'TranslationsController@export',
        array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60']));
}

unset($_publicMw, $_authAdmin, $_path);