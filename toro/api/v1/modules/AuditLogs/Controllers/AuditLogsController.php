<?php
/**
 * TORO — v1/modules/AuditLogs/Controllers/AuditLogsController.php
 */
declare(strict_types=1);

use Shared\Core\DatabaseConnection;
use Shared\Helpers\Response;

final class AuditLogsController
{
    private AuditLogsService $service;

    public function __construct()
    {
        $pdo = DatabaseConnection::getInstance();
        $this->service = new AuditLogsService(new PdoAuditLogsRepository($pdo));
    }

    private function json(): array
    {
        return (array)json_decode(file_get_contents('php://input'), true);
    }

    // GET /v1/audit-logs
    public function index(array $params = []): void
    {
        $filters = [
            'user_id' => $_GET['user_id'] ?? null,
            'entity'  => $_GET['entity']  ?? null,
            'action'  => $_GET['action']  ?? null,
            'limit'   => (int)($_GET['limit']  ?? 50),
            'offset'  => (int)($_GET['offset'] ?? 0),
        ];
        Response::json(['success' => true, 'data' => $this->service->list($filters)], 200);
    }

    // GET /v1/audit-logs/{id}
    public function show(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        Response::json(['success' => true, 'data' => $this->service->getById($id)], 200);
    }

    // POST /v1/audit-logs
    public function store(array $params = []): void
    {
        $data = $this->json();
        Response::json(['success' => true, 'data' => $this->service->create($data)], 201);
    }
}
