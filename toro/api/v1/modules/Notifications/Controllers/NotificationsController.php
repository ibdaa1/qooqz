<?php
/**
 * TORO — v1/modules/Notifications/Controllers/NotificationsController.php
 */
declare(strict_types=1);

use Shared\Core\DatabaseConnection;
use Shared\Helpers\Response;

final class NotificationsController
{
    private NotificationsService $service;

    public function __construct()
    {
        $pdo = DatabaseConnection::getInstance();
        $this->service = new NotificationsService(new PdoNotificationsRepository($pdo));
    }

    private function json(): array
    {
        return (array)json_decode(file_get_contents('php://input'), true);
    }

    // ── Templates ──────────────────────────────────────────────

    // GET /v1/notification-templates
    public function indexTemplates(array $params = []): void
    {
        $filters = [
            'channel'   => $_GET['channel']   ?? null,
            'is_active' => $_GET['is_active'] ?? null,
            'limit'     => (int)($_GET['limit']  ?? 50),
            'offset'    => (int)($_GET['offset'] ?? 0),
        ];
        Response::json(['success' => true, 'data' => $this->service->listTemplates($filters)], 200);
    }

    // GET /v1/notification-templates/{id}
    public function showTemplate(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        Response::json(['success' => true, 'data' => $this->service->getTemplateById($id)], 200);
    }

    // POST /v1/notification-templates
    public function storeTemplate(array $params = []): void
    {
        $data = $this->json();
        Response::json(['success' => true, 'data' => $this->service->createTemplate($data)], 201);
    }

    // PUT /v1/notification-templates/{id}
    public function updateTemplate(array $params = []): void
    {
        $id   = (int)($params['id'] ?? 0);
        $data = $this->json();
        Response::json(['success' => true, 'data' => $this->service->updateTemplate($id, $data)], 200);
    }

    // DELETE /v1/notification-templates/{id}
    public function destroyTemplate(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $this->service->deleteTemplate($id);
        Response::json(['success' => true, 'message' => 'تم الحذف بنجاح'], 200);
    }

    // PUT /v1/notification-templates/{id}/translations
    public function upsertTranslation(array $params = []): void
    {
        $templateId = (int)($params['id'] ?? 0);
        $data       = $this->json();
        Response::json(['success' => true, 'data' => $this->service->upsertTranslation($templateId, $data)], 200);
    }

    // ── Notifications Log ──────────────────────────────────────

    // GET /v1/notifications-log
    public function indexLog(array $params = []): void
    {
        $filters = [
            'user_id' => $_GET['user_id'] ?? null,
            'channel' => $_GET['channel'] ?? null,
            'status'  => $_GET['status']  ?? null,
            'limit'   => (int)($_GET['limit']  ?? 50),
            'offset'  => (int)($_GET['offset'] ?? 0),
        ];
        Response::json(['success' => true, 'data' => $this->service->listLogs($filters)], 200);
    }

    // GET /v1/notifications-log/{id}
    public function showLog(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        Response::json(['success' => true, 'data' => $this->service->getLogById($id)], 200);
    }

    // POST /v1/notifications-log
    public function storeLog(array $params = []): void
    {
        $data = $this->json();
        Response::json(['success' => true, 'data' => $this->service->createLog($data)], 201);
    }

    // PATCH /v1/notifications-log/{id}/status
    public function updateLogStatus(array $params = []): void
    {
        $id     = (int)($params['id'] ?? 0);
        $data   = $this->json();
        $status = $data['status'] ?? '';
        Response::json(['success' => true, 'data' => $this->service->updateLogStatus($id, $status)], 200);
    }
}
