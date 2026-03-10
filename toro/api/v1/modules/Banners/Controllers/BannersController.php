<?php
/**
 * TORO — v1/modules/Banners/Controllers/BannersController.php
 */
declare(strict_types=1);

use Shared\Core\DatabaseConnection;
use Shared\Helpers\Response;

final class BannersController
{
    private BannersService $service;

    public function __construct()
    {
        $pdo = DatabaseConnection::getInstance();
        $this->service = new BannersService(new PdoBannersRepository($pdo));
    }

    public function index(array $params = []): void
    {
        $filters = [
            'lang'      => $_GET['lang']      ?? null,
            'position'  => $_GET['position']  ?? null,
            'is_active' => isset($_GET['is_active']) ? (bool)(int)$_GET['is_active'] : null,
            'limit'     => (int)($_GET['limit']  ?? 50),
            'offset'    => (int)($_GET['offset'] ?? 0),
        ];
        Response::json(['success' => true, 'data' => $this->service->list($filters)], 200);
    }

    public function show(array $params = []): void
    {
        $id   = (int)($params['id'] ?? 0);
        $lang = $_GET['lang'] ?? null;
        Response::json(['success' => true, 'data' => $this->service->getById($id, $lang)], 200);
    }

    public function translations(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        Response::json(['success' => true, 'data' => $this->service->getTranslations($id)], 200);
    }

    public function store(array $params = []): void
    {
        $data = $this->json();
        BannersValidator::create($data);
        $result = $this->service->create(CreateBannerDTO::fromArray($data), $this->authUserId());
        Response::json(['success' => true, 'data' => $result], 201);
    }

    public function update(array $params = []): void
    {
        $id   = (int)($params['id'] ?? 0);
        $data = $this->json();
        BannersValidator::update($data);
        $result = $this->service->update($id, UpdateBannerDTO::fromArray($data), $this->authUserId());
        Response::json(['success' => true, 'data' => $result], 200);
    }

    public function destroy(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $this->service->delete($id, $this->authUserId());
        Response::json(['success' => true, 'message' => 'تم حذف البانر'], 200);
    }

    private function json(): array
    {
        $raw = file_get_contents('php://input');
        return json_decode($raw ?: '{}', true) ?? [];
    }

    private function authUserId(): int
    {
        $id = $_SERVER['REQUEST_USER_ID'] ?? null;
        if (!$id) { Response::json(['success' => false, 'message' => 'غير مصرح'], 401); exit; }
        return (int)$id;
    }
}
