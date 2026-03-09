<?php
/**
 * TORO — v1/modules/OrderShippingAddresses/Controllers/OrderShippingAddressesController.php
 */
declare(strict_types=1);

use Shared\Core\DatabaseConnection;
use Shared\Helpers\Response;

final class OrderShippingAddressesController
{
    private OrderShippingAddressesService $service;

    public function __construct()
    {
        $pdo = DatabaseConnection::getInstance();
        $this->service = new OrderShippingAddressesService(
            new PdoOrderShippingAddressesRepository($pdo)
        );
    }

    private function json(): array
    {
        return (array)json_decode(file_get_contents('php://input'), true);
    }

    // GET /v1/orders/{orderId}/shipping-address
    public function show(array $params = []): void
    {
        $orderId = (int)($params['orderId'] ?? 0);
        Response::json(['success' => true, 'data' => $this->service->getByOrderId($orderId)], 200);
    }

    // PUT /v1/orders/{orderId}/shipping-address
    public function upsert(array $params = []): void
    {
        $orderId = (int)($params['orderId'] ?? 0);
        $data    = $this->json();
        Response::json(['success' => true, 'data' => $this->service->upsert($orderId, $data)], 200);
    }
}
