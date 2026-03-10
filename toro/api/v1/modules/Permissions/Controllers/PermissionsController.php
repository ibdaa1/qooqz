<?php
/**
 * TORO — v1/modules/Permissions/Controllers/PermissionsController.php
 * يستقبل HTTP فقط — لا منطق هنا أبداً
 */
declare(strict_types=1);

use Shared\Core\DatabaseConnection;
use Shared\Helpers\Response;

final class PermissionsController
{
    private PermissionsService $service;

    public function __construct()
    {
        $pdo = DatabaseConnection::getInstance();
        $this->service = new PermissionsService(
            new PdoPermissionsRepository($pdo),
        );
    }

    // ── GET /v1/permissions ───────────────────────────────────
    public function index(array $params = []): void
    {
        $filters = [
            'group'  => $_GET['group'] ?? null,
            'search' => $_GET['search'] ?? null,
            'limit'  => (int)($_GET['limit']  ?? 100),
            'offset' => (int)($_GET['offset'] ?? 0),
        ];

        $result = $this->service->list($filters);
        Response::json(['success' => true, 'data' => $result], 200);
    }

    // ── GET /v1/permissions/{id} ──────────────────────────────
    public function show(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $perm = $this->service->getById($id);
        Response::json(['success' => true, 'data' => $perm], 200);
    }

    // ── GET /v1/permissions/slug/{slug} ───────────────────────
    public function showBySlug(array $params = []): void
    {
        $slug = $params['slug'] ?? '';
        $perm = $this->service->getBySlug($slug);
        Response::json(['success' => true, 'data' => $perm], 200);
    }

    // ── GET /v1/permissions/grouped ───────────────────────────
    public function grouped(array $params = []): void
    {
        $grouped = $this->service->getGrouped();
        Response::json(['success' => true, 'data' => $grouped], 200);
    }

    // ── POST /v1/permissions ──────────────────────────────────
    public function store(array $params = []): void
    {
        $data = $this->json();
        PermissionsValidator::create($data);
        $result = $this->service->create(
            CreatePermissionDTO::fromArray($data),
            $this->authUserId()
        );
        Response::json(['success' => true, 'data' => $result], 201);
    }

    // ── PUT /v1/permissions/{id} ──────────────────────────────
    public function update(array $params = []): void
    {
        $id   = (int)($params['id'] ?? 0);
        $data = $this->json();
        PermissionsValidator::update($data);
        $result = $this->service->update(
            $id,
            UpdatePermissionDTO::fromArray($data),
            $this->authUserId()
        );
        Response::json(['success' => true, 'data' => $result], 200);
    }

    // ── DELETE /v1/permissions/{id} ───────────────────────────
    public function destroy(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $this->service->delete($id, $this->authUserId());
        Response::json(['success' => true, 'message' => 'تم حذف الصلاحية'], 200);
    }

    // ── Helpers ───────────────────────────────────────────────
    private function json(): array
    {
        $raw = file_get_contents('php://input');
        return json_decode($raw ?: '{}', true) ?? [];
    }

    private function authUserId(): int
    {
        $id = $_SERVER['REQUEST_USER_ID'] ?? null;
        if (!$id) {
            Response::json(['success' => false, 'message' => 'غير مصرح'], 401);
            exit;
        }
        return (int)$id;
    }
}