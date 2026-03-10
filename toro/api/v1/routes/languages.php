<?php
/**
 * TORO — v1/routes/languages.php
 * مسارات اللغات
 *
 * Prefixes:
 *   /v1/public/languages — بدون مصادقة
 *   /v1/admin/languages  — يتطلب Auth + Admin
 *
 * $router هو instance من Shared\Core\Kernel
 */

declare(strict_types=1);

// ── تحميل ملفات اللغات ────────────────────────────────────
$_langPath = __DIR__ . '/../modules/Languages';
require_once $_langPath . '/Contracts/LanguagesRepositoryInterface.php';
require_once $_langPath . '/DTO/CreateLanguageDTO.php';
require_once $_langPath . '/DTO/UpdateLanguageDTO.php';
require_once $_langPath . '/Validators/LanguagesValidator.php';
require_once $_langPath . '/Repositories/PdoLanguagesRepository.php';
require_once $_langPath . '/Services/LanguagesService.php';
require_once $_langPath . '/Controllers/LanguagesController.php';
unset($_langPath);

$_publicMw  = ['V1\Middleware\ThrottleMiddleware:120,60'];
$_authAdmin = ['V1\Middleware\AuthMiddleware', 'V1\Middleware\AdminMiddleware'];

// ════════════════════════════════════════════════════════════
// PUBLIC ROUTES  →  /v1/public/languages
// ════════════════════════════════════════════════════════════

// GET — قائمة اللغات (النشطة فقط ما لم يُحدد خلافه)
foreach (['/v1/languages', '/v1/public/languages'] as $_path) {
    $router->addRoute('GET', $_path, 'LanguagesController@index', $_publicMw);
}

// GET — لغة بالـ ID
foreach (['/v1/languages/{id}', '/v1/public/languages/{id}'] as $_path) {
    $router->addRoute('GET', $_path, 'LanguagesController@show', $_publicMw);
}

// GET — لغة بالكود (مثل ar, en)
foreach (['/v1/languages/code/{code}', '/v1/public/languages/code/{code}'] as $_path) {
    $router->addRoute('GET', $_path, 'LanguagesController@showByCode', $_publicMw);
}

// GET — اللغة الافتراضية
foreach (['/v1/languages/default', '/v1/public/languages/default'] as $_path) {
    $router->addRoute('GET', $_path, 'LanguagesController@showDefault', $_publicMw);
}

// ════════════════════════════════════════════════════════════
// ADMIN ROUTES  →  /v1/admin/languages
// ════════════════════════════════════════════════════════════

// POST — إنشاء لغة
foreach (['/v1/languages', '/v1/admin/languages'] as $_path) {
    $router->addRoute('POST', $_path, 'LanguagesController@store',
        array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60']));
}

// PUT — تعديل لغة
foreach (['/v1/languages/{id}', '/v1/admin/languages/{id}'] as $_path) {
    $router->addRoute('PUT', $_path, 'LanguagesController@update',
        array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:30,60']));
}

// DELETE — حذف لغة (soft delete: تعطيلها)
foreach (['/v1/languages/{id}', '/v1/admin/languages/{id}'] as $_path) {
    $router->addRoute('DELETE', $_path, 'LanguagesController@destroy',
        array_merge($_authAdmin, ['V1\Middleware\ThrottleMiddleware:20,60']));
}

unset($_publicMw, $_authAdmin, $_path);