<?php

// تحميل يدوي للملفات
 $base = BASE_PATH . '/v1/modules/Theme/';
require_once $base . 'Contracts/ThemeRepositoryInterface.php';
require_once $base . 'Repositories/PdoThemeRepository.php';
require_once $base . 'Services/ThemeService.php';
require_once $base . 'Controllers/ThemeController.php';

// --- Public Routes ---

// إرجاع CSS مباشرة (يمكن استخدامه في <link>)
 $router->addRoute('GET', '/v1/theme/css', function($params) {
    $controller = new \V1\modules\Theme\Controllers\ThemeController();
    $controller->getCss($params);
});

// --- Admin Routes ---

 $router->addRoute('GET', '/v1/theme', function($params) {
    $controller = new \V1\modules\Theme\Controllers\ThemeController();
    $controller->index($params);
}, ['auth', 'admin']);

 $router->addRoute('PUT', '/v1/theme/{id}', function($params) {
    $controller = new \V1\modules\Theme\Controllers\ThemeController();
    $controller->update($params);
}, ['auth', 'admin']);