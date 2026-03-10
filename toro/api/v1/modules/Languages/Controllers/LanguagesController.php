<?php
/**
 * TORO — v1/modules/Languages/Controllers/LanguagesController.php
 * يستقبل HTTP فقط
 */
declare(strict_types=1);

use Shared\Core\DatabaseConnection;
use Shared\Helpers\Response;

final class LanguagesController
{
    private LanguagesService $service;

    public function __construct()
    {
        $pdo = DatabaseConnection::getInstance();
        $this->service = new LanguagesService(
            new PdoLanguagesRepository($pdo),
        );
    }

    // ── GET /v1/languages ────────────────────────────────────
    public function index(array $params = []): void
    {
        $filters = [
            'is_active' => isset($_GET['is_active']) ? (bool)(int)$_GET['is_active'] : null,
            'limit'     => (int)($_GET['limit']  ?? 50),
            'offset'    => (int)($_GET['offset'] ?? 0),
        ];

        $result = $this->service->list($filters);
        Response::json(['success' => true, 'data' => $result], 200);
    }

    // ── GET /v1/languages/{id} ───────────────────────────────
    public function show(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $lang = $this->service->getById($id);
        Response::json(['success' => true, 'data' => $lang], 200);
    }

    // ── GET /v1/languages/code/{code} ────────────────────────
    public function showByCode(array $params = []): void
    {
        $code = $params['code'] ?? '';
        $lang = $this->service->getByCode($code);
        Response::json(['success' => true, 'data' => $lang], 200);
    }

    // ── GET /v1/languages/default ─────────────────────────────
    public function showDefault(array $params = []): void
    {
        $lang = $this->service->getDefault();
        Response::json(['success' => true, 'data' => $lang], 200);
    }

    // ── POST /v1/languages ───────────────────────────────────
    public function store(array $params = []): void
    {
        $data = $this->json();
        LanguagesValidator::create($data);
        $result = $this->service->create(
            CreateLanguageDTO::fromArray($data),
            $this->authUserId()
        );
        Response::json(['success' => true, 'data' => $result], 201);
    }

    // ── PUT /v1/languages/{id} ───────────────────────────────
    public function update(array $params = []): void
    {
        $id   = (int)($params['id'] ?? 0);
        $data = $this->json();
        LanguagesValidator::update($data);
        $result = $this->service->update(
            $id,
            UpdateLanguageDTO::fromArray($data),
            $this->authUserId()
        );
        Response::json(['success' => true, 'data' => $result], 200);
    }

    // ── DELETE /v1/languages/{id} ────────────────────────────
    public function destroy(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $this->service->delete($id, $this->authUserId());
        Response::json(['success' => true, 'message' => 'تم تعطيل اللغة'], 200);
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