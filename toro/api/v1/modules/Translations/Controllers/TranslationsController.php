<?php
/**
 * TORO — v1/modules/Translations/Controllers/TranslationsController.php
 * يستقبل HTTP فقط
 */
declare(strict_types=1);

use Shared\Core\DatabaseConnection;
use Shared\Helpers\Response;

final class TranslationsController
{
    private TranslationsService $service;

    public function __construct()
    {
        $pdo = DatabaseConnection::getInstance();
        $this->service = new TranslationsService(
            new PdoTranslationsRepository($pdo),
        );
    }

    // ── GET /v1/translations/keys ────────────────────────────
    public function listKeys(array $params = []): void
    {
        $filters = [
            'context' => $_GET['context'] ?? null,
            'search'  => $_GET['search']  ?? null,
            'limit'   => (int)($_GET['limit']  ?? 50),
            'offset'  => (int)($_GET['offset'] ?? 0),
        ];

        $result = $this->service->listKeys($filters);
        Response::json(['success' => true, 'data' => $result], 200);
    }

    // ── GET /v1/translations/{key} ───────────────────────────
    public function getByKey(array $params = []): void
    {
        $key  = $params['key'] ?? '';
        $lang = $_GET['lang']  ?? null;

        if (!$lang) {
            Response::json(['success' => false, 'message' => 'lang parameter is required'], 400);
            return;
        }

        $result = $this->service->getTranslationsByKeys([$key], $lang);
        $value  = $result[$key] ?? null;

        Response::json([
            'success' => true,
            'data'    => ['key' => $key, 'value' => $value]
        ], 200);
    }

    // ── GET /v1/translations (bulk) ──────────────────────────
    public function getBulk(array $params = []): void
    {
        $keys = $_GET['keys'] ?? [];
        if (is_string($keys)) {
            $keys = explode(',', $keys);
        }
        $lang = $_GET['lang'] ?? null;

        if (!$lang) {
            Response::json(['success' => false, 'message' => 'lang parameter is required'], 400);
            return;
        }

        $result = $this->service->getTranslationsByKeys($keys, $lang);
        Response::json(['success' => true, 'data' => $result], 200);
    }

    // ── GET /v1/translations/lang/{code} ─────────────────────
    public function getLanguagePack(array $params = []): void
    {
        $code = $params['code'] ?? '';
        $result = $this->service->getLanguagePack($code);
        Response::json(['success' => true, 'data' => $result], 200);
    }

    // ── POST /v1/translations/keys ───────────────────────────
    public function storeKey(array $params = []): void
    {
        $data = $this->json();
        TranslationsValidator::createKey($data);
        $result = $this->service->createKey(
            CreateTranslationKeyDTO::fromArray($data),
            $this->authUserId()
        );
        Response::json(['success' => true, 'data' => $result], 201);
    }

    // ── PUT /v1/translations/keys/{id} ───────────────────────
    public function updateKey(array $params = []): void
    {
        $id   = (int)($params['id'] ?? 0);
        $data = $this->json();
        TranslationsValidator::updateKey($data);
        $result = $this->service->updateKey(
            $id,
            UpdateTranslationKeyDTO::fromArray($data),
            $this->authUserId()
        );
        Response::json(['success' => true, 'data' => $result], 200);
    }

    // ── DELETE /v1/translations/keys/{id} ────────────────────
    public function destroyKey(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $this->service->deleteKey($id, $this->authUserId());
        Response::json(['success' => true, 'message' => 'تم حذف المفتاح'], 200);
    }

    // ── POST /v1/translations/values ─────────────────────────
    public function upsertValue(array $params = []): void
    {
        $data = $this->json();
        TranslationsValidator::upsertValue($data);
        $result = $this->service->upsertValue(
            UpsertTranslationValueDTO::fromArray($data),
            $this->authUserId()
        );
        Response::json(['success' => true, 'data' => $result], 200);
    }

    // ── DELETE /v1/translations/values/{key_id}/{lang_id} ────
    public function destroyValue(array $params = []): void
    {
        $keyId = (int)($params['key_id'] ?? 0);
        $langId = (int)($params['lang_id'] ?? 0);
        $this->service->deleteValue($keyId, $langId, $this->authUserId());
        Response::json(['success' => true, 'message' => 'تم حذف القيمة'], 200);
    }

    // ── POST /v1/translations/import ─────────────────────────
    public function import(array $params = []): void
    {
        $data = $this->json();
        TranslationsValidator::validateImport($data);
        $stats = $this->service->import(
            $data['language_code'],
            $data['translations'],
            $this->authUserId()
        );
        Response::json(['success' => true, 'data' => $stats], 200);
    }

    // ── GET /v1/translations/export/{code} ───────────────────
    public function export(array $params = []): void
    {
        $code = $params['code'] ?? '';
        $translations = $this->service->export($code);
        Response::json($translations, 200, JSON_UNESCAPED_UNICODE);
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