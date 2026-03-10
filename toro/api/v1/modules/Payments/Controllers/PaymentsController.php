<?php
/**
 * TORO — v1/modules/Payments/Controllers/PaymentsController.php
 */
declare(strict_types=1);

use Shared\Core\DatabaseConnection;
use Shared\Helpers\Response;

final class PaymentsController
{
    private PaymentsService $service;

    public function __construct()
    {
        $pdo = DatabaseConnection::getInstance();
        $this->service = new PaymentsService(new PdoPaymentsRepository($pdo));
    }

    private function json(): array
    {
        return (array)json_decode(file_get_contents('php://input'), true);
    }

    // GET /v1/payments
    public function index(array $params = []): void
    {
        $filters = [
            'order_id' => $_GET['order_id'] ?? null,
            'status'   => $_GET['status']   ?? null,
            'limit'    => (int)($_GET['limit']  ?? 50),
            'offset'   => (int)($_GET['offset'] ?? 0),
        ];
        Response::json(['success' => true, 'data' => $this->service->list($filters)], 200);
    }

    // GET /v1/payments/{id}
    public function show(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        Response::json(['success' => true, 'data' => $this->service->getById($id)], 200);
    }

    // GET /v1/orders/{orderId}/payments
    public function byOrder(array $params = []): void
    {
        $orderId = (int)($params['orderId'] ?? 0);
        Response::json(['success' => true, 'data' => $this->service->getByOrderId($orderId)], 200);
    }

    // POST /v1/payments
    public function store(array $params = []): void
    {
        $data = $this->json();
        $dto  = CreatePaymentDTO::fromArray($data);
        Response::json(['success' => true, 'data' => $this->service->create($dto)], 201);
    }

    // PATCH /v1/payments/{id}/status
    public function updateStatus(array $params = []): void
    {
        $id     = (int)($params['id'] ?? 0);
        $data   = $this->json();
        $status = $data['status'] ?? '';
        Response::json(['success' => true, 'data' => $this->service->updateStatus($id, $status)], 200);
    }

    // GET /v1/payments/{paymentId}/refunds
    public function refunds(array $params = []): void
    {
        $paymentId = (int)($params['paymentId'] ?? 0);
        Response::json(['success' => true, 'data' => $this->service->getRefunds($paymentId)], 200);
    }

    // POST /v1/payments/{paymentId}/refunds
    public function createRefund(array $params = []): void
    {
        $paymentId = (int)($params['paymentId'] ?? 0);
        $data      = $this->json();
        Response::json(['success' => true, 'data' => $this->service->createRefund($paymentId, $data)], 201);
    }

    // PATCH /v1/refunds/{id}/process
    public function processRefund(array $params = []): void
    {
        $refundId    = (int)($params['id'] ?? 0);
        $data        = $this->json();
        $status      = $data['status']       ?? '';
        $processedBy = (int)($data['processed_by'] ?? ($_SERVER['REQUEST_USER_ID'] ?? 0));
        Response::json(['success' => true, 'data' => $this->service->processRefund($refundId, $status, $processedBy)], 200);
    }
}
