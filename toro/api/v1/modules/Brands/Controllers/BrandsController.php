<?php
/**
 * TORO — v1/modules/Brands/Controllers/BrandsController.php
 * يستقبل HTTP فقط — لا منطق هنا أبداً
 */
declare(strict_types=1);

use Shared\Core\DatabaseConnection;
use Shared\Helpers\Response;

final class BrandsController
{
    private BrandsService $service;

    public function __construct()
    {
        $pdo = DatabaseConnection::getInstance();
        $this->service = new BrandsService(
            new PdoBrandsRepository($pdo),
        );
    }

    // ── GET /v1/brands ────────────────────────────────────────
    public function index(array $params = []): void
    {
        $filters = [
            'lang'      => $_GET['lang']      ?? null,
            'is_active' => isset($_GET['is_active']) ? (bool)(int)$_GET['is_active'] : null,
            'limit'     => (int)($_GET['limit']  ?? 50),
            'offset'    => (int)($_GET['offset'] ?? 0),
        ];

        $result = $this->service->list($filters);
        Response::json(['success' => true, 'data' => $result], 200);
    }

    // ── GET /v1/brands/{id} ───────────────────────────────────
    public function show(array $params = []): void
    {
        $id   = (int)($params['id'] ?? 0);
        $lang = $_GET['lang'] ?? null;
        $brand = $this->service->getById($id, $lang);
        Response::json(['success' => true, 'data' => $brand], 200);
    }

    // ── GET /v1/brands/slug/{slug} ────────────────────────────
    public function showBySlug(array $params = []): void
    {
        $slug = $params['slug'] ?? '';
        $lang = $_GET['lang']  ?? null;
        $brand = $this->service->getBySlug($slug, $lang);
        Response::json(['success' => true, 'data' => $brand], 200);
    }

    // ── GET /v1/brands/{id}/translations ──────────────────────
    public function translations(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $translations = $this->service->getTranslations($id);
        Response::json(['success' => true, 'data' => $translations], 200);
    }

    // ── POST /v1/brands ───────────────────────────────────────
    public function store(array $params = []): void
    {
        $data = $this->json();
        BrandsValidator::create($data);
        $result = $this->service->create(
            CreateBrandDTO::fromArray($data),
            $this->authUserId()
        );
        Response::json(['success' => true, 'data' => $result], 201);
    }

    // ── PUT /v1/brands/{id} ───────────────────────────────────
    public function update(array $params = []): void
    {
        $id   = (int)($params['id'] ?? 0);
        $data = $this->json();
        BrandsValidator::update($data);
        $result = $this->service->update(
            $id,
            UpdateBrandDTO::fromArray($data),
            $this->authUserId()
        );
        Response::json(['success' => true, 'data' => $result], 200);
    }

    // ── DELETE /v1/brands/{id} ────────────────────────────────
    public function destroy(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $this->service->delete($id, $this->authUserId());
        Response::json(['success' => true, 'message' => 'تم حذف الماركة'], 200);
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
