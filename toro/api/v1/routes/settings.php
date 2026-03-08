<?php

// 1. تحميل الملفات يدوياً
 $base = BASE_PATH . '/v1/modules/Settings/';
require_once $base . 'Contracts/SettingsRepositoryInterface.php';
require_once $base . 'Repositories/PdoSettingsRepository.php';
require_once $base . 'Validators/SettingsValidator.php';
require_once $base . 'Services/SettingsService.php';
require_once $base . 'Controllers/SettingsController.php';

// 2. تعريف الروتات

// اختبار
 $router->addRoute('GET', '/v1/settings/ping', function($params) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'message' => 'Pong!']);
});

// الرابط العام
 $router->addRoute('GET', '/v1/settings/public', function($params) {
    // استخدام الـ Namespace الكامل
    $controller = new \V1\modules\Settings\Controllers\SettingsController();
    $controller->getPublic($params);
});

// روابط الأدمن
 $router->addRoute('GET', '/v1/settings', function($params) {
    $controller = new \V1\modules\Settings\Controllers\SettingsController();
    $controller->index($params);
}, ['auth', 'admin']);

 $router->addRoute('PUT', '/v1/settings/{id}', function($params) {
    $controller = new \V1\modules\Settings\Controllers\SettingsController();
    $controller->update($params);
}, ['auth', 'admin']);