<?php
use App\Core\Router;
use App\Modules\Settings\Controllers\SettingsController;

 $router = new Router();

// Public Route
 $router->get('/settings/public', [SettingsController::class, 'getPublic']);

// Admin Routes (Requires AuthMiddleware + AdminMiddleware)
 $router->group(['middleware' => ['auth', 'admin']], function($router) {
    $router->get('/settings', [SettingsController::class, 'index']);
    $router->put('/settings/{id}', [SettingsController::class, 'update']);
});

return $router;