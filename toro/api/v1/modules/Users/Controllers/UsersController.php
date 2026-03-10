<?php
/**
 * TORO — v1/modules/Users/Controllers/UsersController.php
 */
declare(strict_types=1);

use Shared\Core\DatabaseConnection;
use Shared\Helpers\Response;

final class UsersController
{
    private UsersService $service;

    public function __construct()
    {
        $pdo = DatabaseConnection::getInstance();
        $this->service = new UsersService(new PdoUsersRepository($pdo));
    }

    // GET /v1/users
    public function index(array $params = []): void
    {
        $filters = [
            'is_active'    => isset($_GET['is_active'])  ? (int)$_GET['is_active']  : null,
            'role_id'      => isset($_GET['role_id'])    ? (int)$_GET['role_id']    : null,
            'search'       => $_GET['search']       ?? null,
            'with_trashed' => !empty($_GET['with_trashed']),
            'limit'        => (int)($_GET['limit']  ?? 20),
            'offset'       => (int)($_GET['offset'] ?? 0),
        ];
        Response::json(['success' => true, 'data' => $this->service->list($filters)], 200);
    }

    // GET /v1/users/{id}
    public function show(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        Response::json(['success' => true, 'data' => $this->service->getById($id)], 200);
    }

    // POST /v1/users
    public function store(array $params = []): void
    {
        $data   = $this->json();
        $result = $this->service->create($data);
        Response::json(['success' => true, 'data' => $result], 201);
    }

    // PUT /v1/users/{id}
    public function update(array $params = []): void
    {
        $id     = (int)($params['id'] ?? 0);
        $data   = $this->json();
        $result = $this->service->update($id, $data);
        Response::json(['success' => true, 'data' => $result], 200);
    }

    // DELETE /v1/users/{id}
    public function destroy(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $this->service->delete($id);
        Response::json(['success' => true], 200);
    }

    // POST /v1/users/{id}/restore
    public function restore(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $this->service->restore($id);
        Response::json(['success' => true], 200);
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
