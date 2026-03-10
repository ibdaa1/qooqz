<?php
/**
 * TORO — v1/modules/Orders/Controllers/OrdersController.php
 */
declare(strict_types=1);

use Shared\Core\DatabaseConnection;
use Shared\Helpers\Response;

final class OrdersController
{
    private OrdersService $service;

    public function __construct()
    {
        $pdo = DatabaseConnection::getInstance();
        $this->service = new OrdersService(new PdoOrdersRepository($pdo));
    }

    // GET /v1/orders
    public function index(array $params = []): void
    {
        $userId = isset($_SERVER['REQUEST_USER_ID']) ? (int)$_SERVER['REQUEST_USER_ID'] : null;
        $isAdmin = isset($_SERVER['REQUEST_IS_ADMIN']) && $_SERVER['REQUEST_IS_ADMIN'];

        $filters = [
            'status'  => $_GET['status']  ?? null,
            'user_id' => $isAdmin ? ($_GET['user_id'] ?? null) : $userId,
            'limit'   => (int)($_GET['limit']  ?? 20),
            'offset'  => (int)($_GET['offset'] ?? 0),
        ];

        Response::json(['success' => true, 'data' => $this->service->list($filters)], 200);
    }

    // GET /v1/orders/{id}
    public function show(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        Response::json(['success' => true, 'data' => $this->service->getById($id)], 200);
    }

    // GET /v1/orders/number/{number}
    public function showByNumber(array $params = []): void
    {
        $number = $params['number'] ?? '';
        Response::json(['success' => true, 'data' => $this->service->getByNumber($number)], 200);
    }

    // GET /v1/orders/{id}/history
    public function statusHistory(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        Response::json(['success' => true, 'data' => $this->service->getStatusHistory($id)], 200);
    }

    // POST /v1/orders
    public function store(array $params = []): void
    {
        $data = $this->json();
        $meta = [
            'ip_address' => $_SERVER['REMOTE_ADDR']     ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ];
        $result = $this->service->create(CreateOrderDTO::fromArray($data), $meta);
        Response::json(['success' => true, 'data' => $result], 201);
    }

    // PATCH /v1/orders/{id}/status
    public function updateStatus(array $params = []): void
    {
        $id     = (int)($params['id'] ?? 0);
        $data   = $this->json();
        $status = $data['status'] ?? '';
        $note   = $data['note']   ?? null;
        $result = $this->service->updateStatus($id, $status, $note, $this->authUserId());
        Response::json(['success' => true, 'data' => $result], 200);
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
