<?php
/**
 * TORO — v1/modules/Images/Controllers/ImagesController.php
 * يدعم رفع الملفات (multipart) وتخزين الروابط (JSON)
 */
declare(strict_types=1);

use Shared\Core\DatabaseConnection;
use Shared\Helpers\Response;

final class ImagesController
{
    private ImagesService $service;

    public function __construct()
    {
        $pdo = DatabaseConnection::getInstance();
        $this->service = new ImagesService(new PdoImagesRepository($pdo));
    }

    // GET /v1/images
    public function index(array $params = []): void
    {
        $filters = [
            'owner_id'     => isset($_GET['owner_id'])      ? (int)$_GET['owner_id']      : null,
            'image_type_id'=> isset($_GET['image_type_id']) ? (int)$_GET['image_type_id'] : null,
            'visibility'   => $_GET['visibility']           ?? null,
            'user_id'      => isset($_GET['user_id'])       ? (int)$_GET['user_id']       : null,
            'limit'        => (int)($_GET['limit']  ?? 50),
            'offset'       => (int)($_GET['offset'] ?? 0),
        ];
        Response::json(['success' => true, 'data' => $this->service->list($filters)], 200);
    }

    // GET /v1/images/{id}
    public function show(array $params = []): void
    {
        $row = $this->service->getById((int)($params['id'] ?? 0));
        Response::json(['success' => true, 'data' => $row], 200);
    }

    // GET /v1/images/owner/{ownerId}
    public function byOwner(array $params = []): void
    {
        $ownerId     = (int)($params['owner_id'] ?? 0);
        $imageTypeId = isset($_GET['image_type_id']) ? (int)$_GET['image_type_id'] : null;
        $rows        = $this->service->getByOwner($ownerId, $imageTypeId);
        Response::json(['success' => true, 'data' => $rows], 200);
    }

    // POST /v1/images/upload  — multipart file upload
    public function upload(array $params = []): void
    {
        // Supports both 'image' and 'file' as field name for flexibility across different form integrations
        $file = $_FILES['image'] ?? $_FILES['file'] ?? null;
        if (!$file) {
            Response::json(['success' => false, 'message' => 'لم يتم إرسال ملف (حقل: image أو file)'], 422);
            return;
        }

        $meta   = $_POST;
        $result = $this->service->upload($file, $meta, $this->authUserId());
        Response::json(['success' => true, 'data' => $result], 201);
    }

    // POST /v1/images  — JSON body (URL-based)
    public function store(array $params = []): void
    {
        $data = $this->json();
        ImagesValidator::create($data);
        $result = $this->service->create(
            CreateImageDTO::fromArray($data, $this->authUserId()),
            $this->authUserId()
        );
        Response::json(['success' => true, 'data' => $result], 201);
    }

    // PUT /v1/images/{id}
    public function update(array $params = []): void
    {
        $id   = (int)($params['id'] ?? 0);
        $data = $this->json();
        ImagesValidator::update($data);
        $result = $this->service->update($id, UpdateImageDTO::fromArray($data), $this->authUserId());
        Response::json(['success' => true, 'data' => $result], 200);
    }

    // DELETE /v1/images/{id}
    public function destroy(array $params = []): void
    {
        $this->service->delete((int)($params['id'] ?? 0), $this->authUserId());
        Response::json(['success' => true, 'message' => 'تم حذف الصورة'], 200);
    }

    // PATCH /v1/images/{id}/set-main
    public function setMain(array $params = []): void
    {
        $result = $this->service->setMain((int)($params['id'] ?? 0), $this->authUserId());
        Response::json(['success' => true, 'data' => $result], 200);
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
