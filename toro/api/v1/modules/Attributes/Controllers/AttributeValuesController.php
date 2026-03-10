<?php
/**
 * TORO — v1/modules/Attributes/Controllers/AttributeValuesController.php
 * يستقبل HTTP فقط — لا منطق هنا أبداً
 */
declare(strict_types=1);

use Shared\Core\DatabaseConnection;
use Shared\Helpers\Response;

final class AttributeValuesController
{
    private AttributeValuesService $service;

    public function __construct()
    {
        $pdo = DatabaseConnection::getInstance();
        $this->service = new AttributeValuesService(
            new PdoAttributeValuesRepository($pdo),
            new PdoAttributesRepository($pdo),
        );
    }

    // ── GET /v1/attribute-values/{id} ─────────────────────────
    public function show(array $params = []): void
    {
        $id   = (int)($params['id'] ?? 0);
        $lang = $_GET['lang'] ?? null;
        $value = $this->service->getById($id, $lang);
        Response::json(['success' => true, 'data' => $value], 200);
    }

    // ── GET /v1/attribute-values/{id}/translations ────────────
    public function translations(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $translations = $this->service->getTranslations($id);
        Response::json(['success' => true, 'data' => $translations], 200);
    }

    // ── POST /v1/attribute-values ─────────────────────────────
    public function store(array $params = []): void
    {
        $data = $this->json();
        AttributeValuesValidator::create($data);
        $result = $this->service->create(
            CreateAttributeValueDTO::fromArray($data),
            $this->authUserId()
        );
        Response::json(['success' => true, 'data' => $result], 201);
    }

    // ── PUT /v1/attribute-values/{id} ─────────────────────────
    public function update(array $params = []): void
    {
        $id   = (int)($params['id'] ?? 0);
        $data = $this->json();
        AttributeValuesValidator::update($data);
        $result = $this->service->update(
            $id,
            UpdateAttributeValueDTO::fromArray($data),
            $this->authUserId()
        );
        Response::json(['success' => true, 'data' => $result], 200);
    }

    // ── DELETE /v1/attribute-values/{id} ──────────────────────
    public function destroy(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $this->service->delete($id, $this->authUserId());
        Response::json(['success' => true, 'message' => 'تم حذف قيمة السمة'], 200);
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
