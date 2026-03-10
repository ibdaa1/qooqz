<?php
/**
 * TORO — v1/modules/Roles/Controllers/RolesController.php
 * يستقبل HTTP فقط — لا منطق هنا أبداً
 */
declare(strict_types=1);

use Shared\Core\DatabaseConnection;
use Shared\Helpers\Response;

final class RolesController
{
    private RolesService $service;

    public function __construct()
    {
        $pdo = DatabaseConnection::getInstance();
        $this->service = new RolesService(
            new PdoRolesRepository($pdo),
        );
    }

    // ── GET /v1/roles ─────────────────────────────────────────
    public function index(array $params = []): void
    {
        $filters = [
            'search' => $_GET['search'] ?? null,
            'limit'  => (int)($_GET['limit']  ?? 50),
            'offset' => (int)($_GET['offset'] ?? 0),
        ];

        $result = $this->service->list($filters);
        Response::json(['success' => true, 'data' => $result], 200);
    }

    // ── GET /v1/roles/{id} ────────────────────────────────────
    public function show(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $role = $this->service->getById($id);
        Response::json(['success' => true, 'data' => $role], 200);
    }

    // ── GET /v1/roles/slug/{slug} ─────────────────────────────
    public function showBySlug(array $params = []): void
    {
        $slug = $params['slug'] ?? '';
        $role = $this->service->getBySlug($slug);
        Response::json(['success' => true, 'data' => $role], 200);
    }

    // ── POST /v1/roles ────────────────────────────────────────
    public function store(array $params = []): void
    {
        $data = $this->json();
        RolesValidator::create($data);
        $result = $this->service->create(
            CreateRoleDTO::fromArray($data),
            $this->authUserId()
        );
        Response::json(['success' => true, 'data' => $result], 201);
    }

    // ── PUT /v1/roles/{id} ────────────────────────────────────
    public function update(array $params = []): void
    {
        $id   = (int)($params['id'] ?? 0);
        $data = $this->json();
        RolesValidator::update($data);
        $result = $this->service->update(
            $id,
            UpdateRoleDTO::fromArray($data),
            $this->authUserId()
        );
        Response::json(['success' => true, 'data' => $result], 200);
    }

    // ── DELETE /v1/roles/{id} ─────────────────────────────────
    public function destroy(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $this->service->delete($id, $this->authUserId());
        Response::json(['success' => true, 'message' => 'تم حذف الدور'], 200);
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