<?php
/**
 * TORO — v1/modules/Images/Controllers/ImageTypesController.php
 */
declare(strict_types=1);

use Shared\Core\DatabaseConnection;
use Shared\Helpers\Response;

final class ImageTypesController
{
    private ImageTypesService $service;

    public function __construct()
    {
        $pdo = DatabaseConnection::getInstance();
        $this->service = new ImageTypesService(new PdoImageTypesRepository($pdo));
    }

    // GET /v1/image-types
    public function index(array $params = []): void
    {
        $filters = [
            'is_thumbnail' => isset($_GET['is_thumbnail']) ? (bool)(int)$_GET['is_thumbnail'] : null,
            'limit'        => (int)($_GET['limit']  ?? 100),
            'offset'       => (int)($_GET['offset'] ?? 0),
        ];
        Response::json(['success' => true, 'data' => $this->service->list($filters)], 200);
    }

    // GET /v1/image-types/{id}
    public function show(array $params = []): void
    {
        $row = $this->service->getById((int)($params['id'] ?? 0));
        Response::json(['success' => true, 'data' => $row], 200);
    }

    // POST /v1/image-types
    public function store(array $params = []): void
    {
        $data = $this->json();
        ImageTypesValidator::create($data);
        $result = $this->service->create(CreateImageTypeDTO::fromArray($data), $this->authUserId());
        Response::json(['success' => true, 'data' => $result], 201);
    }

    // PUT /v1/image-types/{id}
    public function update(array $params = []): void
    {
        $id   = (int)($params['id'] ?? 0);
        $data = $this->json();
        ImageTypesValidator::update($data);
        $result = $this->service->update($id, UpdateImageTypeDTO::fromArray($data), $this->authUserId());
        Response::json(['success' => true, 'data' => $result], 200);
    }

    // DELETE /v1/image-types/{id}
    public function destroy(array $params = []): void
    {
        $this->service->delete((int)($params['id'] ?? 0), $this->authUserId());
        Response::json(['success' => true, 'message' => 'تم حذف نوع الصورة'], 200);
    }

    // ── Helpers ───────────────────────────────────────────────
    private function json(): array
    {
        return json_decode(file_get_contents('php://input') ?: '{}', true) ?? [];
    }

    private function authUserId(): int
    {
        $id = $_SERVER['REQUEST_USER_ID'] ?? null;
        if (!$id) { Response::json(['success' => false, 'message' => 'غير مصرح'], 401); exit; }
        return (int)$id;
    }
}
