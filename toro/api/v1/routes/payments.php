<?php
/**
 * TORO — v1/routes/payments.php
 */
declare(strict_types=1);

$_path = __DIR__ . '/../modules/Payments';
require_once $_path . '/Contracts/PaymentsRepositoryInterface.php';
require_once $_path . '/DTO/CreatePaymentDTO.php';
require_once $_path . '/Validators/PaymentsValidator.php';
require_once $_path . '/Repositories/PdoPaymentsRepository.php';
require_once $_path . '/Services/PaymentsService.php';
require_once $_path . '/Controllers/PaymentsController.php';
unset($_path);

$_authAdmin = ['V1\Middleware\AuthMiddleware', 'V1\Middleware\AdminMiddleware'];
$_authUser  = ['V1\Middleware\AuthMiddleware'];
$_throttle  = ['V1\Middleware\ThrottleMiddleware:60,60'];

// Payments (admin only to list/create/update)
$router->addRoute('GET',   '/v1/payments',                      'PaymentsController@index',        array_merge($_authAdmin, $_throttle));
$router->addRoute('GET',   '/v1/payments/{id}',                 'PaymentsController@show',         array_merge($_authAdmin, $_throttle));
$router->addRoute('GET',   '/v1/orders/{orderId}/payments',     'PaymentsController@byOrder',      array_merge($_authUser,  $_throttle));
$router->addRoute('POST',  '/v1/payments',                      'PaymentsController@store',        array_merge($_authAdmin, $_throttle));
$router->addRoute('PATCH', '/v1/payments/{id}/status',          'PaymentsController@updateStatus', array_merge($_authAdmin, $_throttle));

// Refunds
$router->addRoute('GET',   '/v1/payments/{paymentId}/refunds',  'PaymentsController@refunds',      array_merge($_authAdmin, $_throttle));
$router->addRoute('POST',  '/v1/payments/{paymentId}/refunds',  'PaymentsController@createRefund', array_merge($_authAdmin, $_throttle));
$router->addRoute('PATCH', '/v1/refunds/{id}/process',          'PaymentsController@processRefund',array_merge($_authAdmin, $_throttle));

unset($_authAdmin, $_authUser, $_throttle);
