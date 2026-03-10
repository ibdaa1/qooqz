<?php
/**
 * TORO — v1/routes/audit_logs.php
 */
declare(strict_types=1);

$_path = __DIR__ . '/../modules/AuditLogs';
require_once $_path . '/Contracts/AuditLogsRepositoryInterface.php';
require_once $_path . '/Repositories/PdoAuditLogsRepository.php';
require_once $_path . '/Services/AuditLogsService.php';
require_once $_path . '/Controllers/AuditLogsController.php';
unset($_path);

$_authAdmin = ['V1\Middleware\AuthMiddleware', 'V1\Middleware\AdminMiddleware'];
$_throttle  = ['V1\Middleware\ThrottleMiddleware:60,60'];

// Audit Logs (read-only for admin; internal writes only)
$router->addRoute('GET',  '/v1/audit-logs',       'AuditLogsController@index', array_merge($_authAdmin, $_throttle));
$router->addRoute('GET',  '/v1/audit-logs/{id}',  'AuditLogsController@show',  array_merge($_authAdmin, $_throttle));
$router->addRoute('POST', '/v1/audit-logs',        'AuditLogsController@store', array_merge($_authAdmin, $_throttle));

unset($_authAdmin, $_throttle);
