<?php
/**
 * TORO — v1/modules/Coupons/Controllers/CouponsController.php
 */
declare(strict_types=1);

use Shared\Core\DatabaseConnection;
use Shared\Helpers\Response;

final class CouponsController
{
    private CouponsService $service;

    public function __construct()
    {
        $pdo = DatabaseConnection::getInstance();
        $this->service = new CouponsService(new PdoCouponsRepository($pdo));
    }

    public function index(array $params = []): void
    {
        $filters = [
            'lang'      => $_GET['lang']      ?? null,
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

    public function validate(array $params = []): void
    {
        $code        = $_GET['code']         ?? ($this->json()['code']         ?? '');
        $orderAmount = (float)($_GET['order_amount'] ?? ($this->json()['order_amount'] ?? 0));
        $result      = $this->service->validate($code, $orderAmount);
        Response::json(['success' => true, 'data' => $result], 200);
    }

    public function translations(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        Response::json(['success' => true, 'data' => $this->service->getTranslations($id)], 200);
    }

    public function store(array $params = []): void
    {
        $data = $this->json();
        CouponsValidator::create($data);
        $result = $this->service->create(CreateCouponDTO::fromArray($data), $this->authUserId());
        Response::json(['success' => true, 'data' => $result], 201);
    }

    public function update(array $params = []): void
    {
        $id   = (int)($params['id'] ?? 0);
        $data = $this->json();
        CouponsValidator::update($data);
        $result = $this->service->update($id, UpdateCouponDTO::fromArray($data), $this->authUserId());
        Response::json(['success' => true, 'data' => $result], 200);
    }

    public function destroy(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $this->service->delete($id, $this->authUserId());
        Response::json(['success' => true, 'message' => 'تم حذف الكوبون'], 200);
    }

    private function json(): array
    {
        static $body = null;
        if ($body === null) {
            $raw  = file_get_contents('php://input');
            $body = json_decode($raw ?: '{}', true) ?? [];
        }
        return $body;
    }

    private function authUserId(): int
    {
        $id = $_SERVER['REQUEST_USER_ID'] ?? null;
        if (!$id) { Response::json(['success' => false, 'message' => 'غير مصرح'], 401); exit; }
        return (int)$id;
    }
}
