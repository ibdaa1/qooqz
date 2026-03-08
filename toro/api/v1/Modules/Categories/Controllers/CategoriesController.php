<?php
/**
 * TORO — v1/modules/Categories/Controllers/CategoriesController.php
 * يستقبل HTTP فقط — لا منطق هنا أبداً
 */
declare(strict_types=1);
namespace V1\Modules\Categories\Controllers;

use V1\Modules\Categories\Services\CategoriesService;
use V1\Modules\Categories\Repositories\PdoCategoriesRepository;
use V1\Modules\Categories\Validators\CategoriesValidator;
use V1\Modules\Categories\DTO\{CreateCategoryDTO, UpdateCategoryDTO};
use Shared\Core\DatabaseConnection;
use Shared\Helpers\Response;

final class CategoriesController
{
    private CategoriesService $service;

    public function __construct()
    {
        $pdo = DatabaseConnection::getInstance();
        $this->service = new CategoriesService(
            new PdoCategoriesRepository($pdo),
        );
    }

    // ── GET /v1/categories ────────────────────────────────────
    public function index(array $params = []): void
    {
        $filters = [
            'lang'      => $_GET['lang']      ?? null,
            'parent_id' => isset($_GET['parent_id']) ? (int)$_GET['parent_id'] : null,
            'is_active' => isset($_GET['is_active']) ? (bool)(int)$_GET['is_active'] : null,
            'limit'     => (int)($_GET['limit']  ?? 50),
            'offset'    => (int)($_GET['offset'] ?? 0),
        ];

        $result = $this->service->list($filters);
        Response::json(['success' => true, 'data' => $result], 200);
    }

    // ── GET /v1/categories/{id} ───────────────────────────────
    public function show(array $params = []): void
    {
        $id   = (int)($params['id'] ?? 0);
        $lang = $_GET['lang'] ?? null;
        $category = $this->service->getById($id, $lang);
        Response::json(['success' => true, 'data' => $category], 200);
    }

    // ── GET /v1/categories/slug/{slug} ────────────────────────
    public function showBySlug(array $params = []): void
    {
        $slug = $params['slug'] ?? '';
        $lang = $_GET['lang']  ?? null;
        $category = $this->service->getBySlug($slug, $lang);
        Response::json(['success' => true, 'data' => $category], 200);
    }

    // ── GET /v1/categories/{id}/translations ──────────────────
    public function translations(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $translations = $this->service->getTranslations($id);
        Response::json(['success' => true, 'data' => $translations], 200);
    }

    // ── POST /v1/categories ───────────────────────────────────
    public function store(array $params = []): void
    {
        $data = $this->json();
        CategoriesValidator::create($data);
        $result = $this->service->create(
            CreateCategoryDTO::fromArray($data),
            $this->authUserId()
        );
        Response::json(['success' => true, 'data' => $result], 201);
    }

    // ── PUT /v1/categories/{id} ───────────────────────────────
    public function update(array $params = []): void
    {
        $id   = (int)($params['id'] ?? 0);
        $data = $this->json();
        CategoriesValidator::update($data);
        $result = $this->service->update(
            $id,
            UpdateCategoryDTO::fromArray($data),
            $this->authUserId()
        );
        Response::json(['success' => true, 'data' => $result], 200);
    }

    // ── DELETE /v1/categories/{id} ────────────────────────────
    public function destroy(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $this->service->delete($id, $this->authUserId());
        Response::json(['success' => true, 'message' => 'تم حذف التصنيف'], 200);
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
