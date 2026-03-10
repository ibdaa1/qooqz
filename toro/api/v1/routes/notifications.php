<?php
/**
 * TORO — v1/routes/notifications.php
 */
declare(strict_types=1);

$_path = __DIR__ . '/../modules/Notifications';
require_once $_path . '/Contracts/NotificationsRepositoryInterface.php';
require_once $_path . '/Validators/NotificationsValidator.php';
require_once $_path . '/Repositories/PdoNotificationsRepository.php';
require_once $_path . '/Services/NotificationsService.php';
require_once $_path . '/Controllers/NotificationsController.php';
unset($_path);

$_authAdmin = ['V1\Middleware\AuthMiddleware', 'V1\Middleware\AdminMiddleware'];
$_throttle  = ['V1\Middleware\ThrottleMiddleware:60,60'];

// Notification Templates (admin only)
$router->addRoute('GET',    '/v1/notification-templates',                          'NotificationsController@indexTemplates',   array_merge($_authAdmin, $_throttle));
$router->addRoute('GET',    '/v1/notification-templates/{id}',                     'NotificationsController@showTemplate',    array_merge($_authAdmin, $_throttle));
$router->addRoute('POST',   '/v1/notification-templates',                          'NotificationsController@storeTemplate',   array_merge($_authAdmin, $_throttle));
$router->addRoute('PUT',    '/v1/notification-templates/{id}',                     'NotificationsController@updateTemplate',  array_merge($_authAdmin, $_throttle));
$router->addRoute('DELETE', '/v1/notification-templates/{id}',                     'NotificationsController@destroyTemplate', array_merge($_authAdmin, $_throttle));
$router->addRoute('PUT',    '/v1/notification-templates/{id}/translations',        'NotificationsController@upsertTranslation', array_merge($_authAdmin, $_throttle));

// Notifications Log (admin only)
$router->addRoute('GET',    '/v1/notifications-log',                               'NotificationsController@indexLog',        array_merge($_authAdmin, $_throttle));
$router->addRoute('GET',    '/v1/notifications-log/{id}',                          'NotificationsController@showLog',         array_merge($_authAdmin, $_throttle));
$router->addRoute('POST',   '/v1/notifications-log',                               'NotificationsController@storeLog',        array_merge($_authAdmin, $_throttle));
$router->addRoute('PATCH',  '/v1/notifications-log/{id}/status',                   'NotificationsController@updateLogStatus', array_merge($_authAdmin, $_throttle));

unset($_authAdmin, $_throttle);
