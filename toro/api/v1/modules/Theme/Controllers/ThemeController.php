<?php
/**
 * TORO — v1/modules/Theme/Controllers/ThemeController.php
 * يستقبل HTTP فقط — لا منطق هنا أبداً
 */
declare(strict_types=1);

use Shared\Core\DatabaseConnection;
use Shared\Helpers\Response;

class ThemeController
{
    private ThemeService $service;

    public function __construct()
    {
        $pdo = DatabaseConnection::getInstance();
        $this->service = new ThemeService(new PdoThemeRepository($pdo));
    }

    // ── GET /v1/theme/css (public) ────────────────────────────
    public function getCss(array $params = []): void
    {
        $css = $this->service->getActiveCssVariables();
        if (!headers_sent()) {
            http_response_code(200);
            header('Content-Type: text/css; charset=utf-8');
            header('Cache-Control: public, max-age=300');
        }
        echo $css;
        exit;
    }

    // ── GET /v1/theme (admin) ─────────────────────────────────
    public function index(array $params = []): void
    {
        $filters = [
            'is_active' => isset($_GET['is_active']) ? (bool)(int)$_GET['is_active'] : null,
            'search'    => $_GET['search']    ?? null,
            'limit'     => (int)($_GET['limit']  ?? 100),
            'offset'    => (int)($_GET['offset'] ?? 0),
        ];

        $result = $this->service->list($filters);
        Response::json(['success' => true, 'data' => $result]);
    }

    // ── GET /v1/theme/{id} (admin) ────────────────────────────
    public function show(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $color = $this->service->getById($id);
        Response::json(['success' => true, 'data' => $color]);
    }

    // ── POST /v1/theme (admin) ────────────────────────────────
    public function store(array $params = []): void
    {
        $data = $this->json();
        ThemeValidator::create($data);
        $result = $this->service->create(CreateThemeDTO::fromArray($data), $this->authUserId());
        Response::json(['success' => true, 'data' => $result], 201);
    }

    // ── PUT /v1/theme/{id} (admin) ────────────────────────────
    public function update(array $params = []): void
    {
        $id   = (int)($params['id'] ?? 0);
        $data = $this->json();
        ThemeValidator::update($data);
        $result = $this->service->update($id, UpdateThemeDTO::fromArray($data), $this->authUserId());
        Response::json(['success' => true, 'data' => $result]);
    }

    // ── DELETE /v1/theme/{id} (admin) ─────────────────────────
    public function destroy(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $this->service->delete($id, $this->authUserId());
        Response::json(['success' => true, 'message' => 'تم حذف لون الثيم']);
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
