<?php
/**
 * TORO — v1/modules/Settings/Controllers/SettingsController.php
 * يستقبل HTTP فقط — لا منطق هنا أبداً
 */
declare(strict_types=1);

use Shared\Core\DatabaseConnection;
use Shared\Helpers\Response;

class SettingsController
{
    private SettingsService $service;

    public function __construct()
    {
        $pdo = DatabaseConnection::getInstance();
        $this->service = new SettingsService(new PdoSettingsRepository($pdo));
    }

    // ── GET /v1/settings/public ───────────────────────────────
    public function getPublic(array $params = []): void
    {
        $data = $this->service->getPublicSettings();
        Response::json(['status' => 'success', 'data' => $data]);
    }

    // ── GET /v1/settings (admin) ──────────────────────────────
    public function index(array $params = []): void
    {
        $filters = [
            'group'     => $_GET['group']     ?? null,
            'is_public' => isset($_GET['is_public']) ? (bool)(int)$_GET['is_public'] : null,
            'search'    => $_GET['search']    ?? null,
            'limit'     => (int)($_GET['limit']  ?? 100),
            'offset'    => (int)($_GET['offset'] ?? 0),
        ];

        $result = $this->service->list($filters);
        Response::json(['success' => true, 'data' => $result]);
    }

    // ── GET /v1/settings/{id} (admin) ─────────────────────────
    public function show(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $setting = $this->service->getById($id);
        Response::json(['success' => true, 'data' => $setting]);
    }

    // ── GET /v1/settings/group/{group} (admin) ────────────────
    public function byGroup(array $params = []): void
    {
        $group = $params['group'] ?? '';
        $items = $this->service->getByGroup($group);
        Response::json(['success' => true, 'data' => $items]);
    }

    // ── POST /v1/settings (admin) ─────────────────────────────
    public function store(array $params = []): void
    {
        $data = $this->json();
        SettingsValidator::create($data);
        $result = $this->service->create($data, $this->authUserId());
        Response::json(['success' => true, 'data' => $result], 201);
    }

    // ── PUT /v1/settings/{id} (admin) ─────────────────────────
    public function update(array $params = []): void
    {
        $id   = (int)($params['id'] ?? 0);
        $data = $this->json();

        // fetch current type for validation
        $existing = $this->service->getById($id);
        SettingsValidator::update($data, $existing['type'] ?? 'string');

        $result = $this->service->update($id, (string)($data['value'] ?? ''), $this->authUserId());
        Response::json(['success' => true, 'data' => $result]);
    }

    // ── DELETE /v1/settings/{id} (admin) ──────────────────────
    public function destroy(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $this->service->delete($id, $this->authUserId());
        Response::json(['success' => true, 'message' => 'تم حذف الإعداد']);
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
