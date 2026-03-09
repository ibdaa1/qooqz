<?php
/**
 * TORO — v1/modules/UserAddresses/Controllers/UserAddressesController.php
 */
declare(strict_types=1);

use Shared\Core\DatabaseConnection;
use Shared\Helpers\Response;

final class UserAddressesController
{
    private UserAddressesService $service;

    public function __construct()
    {
        $pdo = DatabaseConnection::getInstance();
        $this->service = new UserAddressesService(new PdoUserAddressesRepository($pdo));
    }

    // GET /v1/users/{user_id}/addresses
    public function index(array $params = []): void
    {
        $userId = (int)($params['user_id'] ?? $this->authUserId());
        Response::json(['success' => true, 'data' => $this->service->listByUser($userId)], 200);
    }

    // GET /v1/addresses/{id}
    public function show(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        Response::json(['success' => true, 'data' => $this->service->getById($id)], 200);
    }

    // POST /v1/addresses
    public function store(array $params = []): void
    {
        $data            = $this->json();
        $data['user_id'] = $data['user_id'] ?? $this->authUserId();
        $result          = $this->service->create($data);
        Response::json(['success' => true, 'data' => $result], 201);
    }

    // PUT /v1/addresses/{id}
    public function update(array $params = []): void
    {
        $id     = (int)($params['id'] ?? 0);
        $data   = $this->json();
        $result = $this->service->update($id, $data);
        Response::json(['success' => true, 'data' => $result], 200);
    }

    // DELETE /v1/addresses/{id}
    public function destroy(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $this->service->delete($id);
        Response::json(['success' => true], 200);
    }

    // PATCH /v1/addresses/{id}/default
    public function setDefault(array $params = []): void
    {
        $id     = (int)($params['id'] ?? 0);
        $userId = $this->authUserId();
        $result = $this->service->setDefault($id, $userId);
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
