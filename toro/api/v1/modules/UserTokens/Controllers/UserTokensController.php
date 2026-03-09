<?php
/**
 * TORO — v1/modules/UserTokens/Controllers/UserTokensController.php
 */
declare(strict_types=1);

use Shared\Core\DatabaseConnection;
use Shared\Helpers\Response;

final class UserTokensController
{
    private UserTokensService $service;

    public function __construct()
    {
        $pdo = DatabaseConnection::getInstance();
        $this->service = new UserTokensService(new PdoUserTokensRepository($pdo));
    }

    // GET /v1/users/{user_id}/tokens?type=refresh
    public function index(array $params = []): void
    {
        $userId = (int)($params['user_id'] ?? 0);
        $type   = $_GET['type'] ?? 'refresh';
        Response::json(['success' => true, 'data' => $this->service->listActive($userId, $type)], 200);
    }

    // POST /v1/tokens/issue  { user_id, type }
    public function issue(array $params = []): void
    {
        $data   = $this->json();
        $userId = (int)($data['user_id'] ?? 0);
        $type   = $data['type'] ?? 'refresh';
        $result = $this->service->issue($userId, $type);
        Response::json(['success' => true, 'data' => $result], 201);
    }

    // POST /v1/tokens/verify  { token, type }
    public function verify(array $params = []): void
    {
        $data   = $this->json();
        $token  = $data['token'] ?? '';
        $type   = $data['type']  ?? 'refresh';
        $result = $this->service->verify($token, $type);
        Response::json(['success' => true, 'data' => $result], 200);
    }

    // POST /v1/tokens/consume  { token, type }
    public function consume(array $params = []): void
    {
        $data   = $this->json();
        $token  = $data['token'] ?? '';
        $type   = $data['type']  ?? 'refresh';
        $result = $this->service->consume($token, $type);
        Response::json(['success' => true, 'data' => $result], 200);
    }

    // DELETE /v1/users/{user_id}/tokens?type=refresh
    public function revokeAll(array $params = []): void
    {
        $userId = (int)($params['user_id'] ?? 0);
        $type   = $_GET['type'] ?? 'refresh';
        $count  = $this->service->revokeAll($userId, $type);
        Response::json(['success' => true, 'revoked' => $count], 200);
    }

    // DELETE /v1/tokens/expired  (admin maintenance)
    public function purgeExpired(array $params = []): void
    {
        $count = $this->service->purgeExpired();
        Response::json(['success' => true, 'deleted' => $count], 200);
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
