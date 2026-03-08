<?php
/**
 * TORO — v1/modules/Carts/Controllers/CartsController.php
 */
declare(strict_types=1);

use Shared\Core\DatabaseConnection;
use Shared\Helpers\Response;

final class CartsController
{
    private CartsService $service;

    public function __construct()
    {
        $pdo = DatabaseConnection::getInstance();
        $this->service = new CartsService(new PdoCartsRepository($pdo));
    }

    // GET /v1/carts — get or create
    public function index(array $params = []): void
    {
        $userId     = isset($_SERVER['REQUEST_USER_ID']) ? (int)$_SERVER['REQUEST_USER_ID'] : null;
        $sessionKey = $_GET['session_key'] ?? null;
        $result = $this->service->getOrCreate($userId, $sessionKey);
        Response::json(['success' => true, 'data' => $result], 200);
    }

    // GET /v1/carts/{id}
    public function show(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        Response::json(['success' => true, 'data' => $this->service->getById($id)], 200);
    }

    // POST /v1/carts/{id}/items
    public function addItem(array $params = []): void
    {
        $cartId = (int)($params['id'] ?? 0);
        $data   = $this->json();
        $result = $this->service->addItem($cartId, $data);
        Response::json(['success' => true, 'data' => $result], 200);
    }

    // PUT /v1/carts/{id}/items/{item_id}
    public function updateItem(array $params = []): void
    {
        $cartId = (int)($params['id']      ?? 0);
        $itemId = (int)($params['item_id'] ?? 0);
        $qty    = (int)($this->json()['qty'] ?? 1);
        $result = $this->service->updateItem($cartId, $itemId, $qty);
        Response::json(['success' => true, 'data' => $result], 200);
    }

    // DELETE /v1/carts/{id}/items/{item_id}
    public function removeItem(array $params = []): void
    {
        $cartId = (int)($params['id']      ?? 0);
        $itemId = (int)($params['item_id'] ?? 0);
        $result = $this->service->removeItem($cartId, $itemId);
        Response::json(['success' => true, 'data' => $result], 200);
    }

    // DELETE /v1/carts/{id}/items — clear all
    public function clearItems(array $params = []): void
    {
        $cartId = (int)($params['id'] ?? 0);
        $this->service->clear($cartId);
        Response::json(['success' => true, 'message' => 'تم تفريغ السلة'], 200);
    }

    // PATCH /v1/carts/{id}/coupon
    public function applyCoupon(array $params = []): void
    {
        $cartId   = (int)($params['id'] ?? 0);
        $couponId = $this->json()['coupon_id'] ?? null;
        $result   = $this->service->applyCoupon($cartId, $couponId ? (int)$couponId : null);
        Response::json(['success' => true, 'data' => $result], 200);
    }

    // DELETE /v1/carts/{id}
    public function destroy(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $this->service->delete($id);
        Response::json(['success' => true, 'message' => 'تم حذف السلة'], 200);
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
}
