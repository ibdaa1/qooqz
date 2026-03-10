<?php
/**
 * TORO — v1/modules/UserSocialAccounts/Controllers/UserSocialAccountsController.php
 */
declare(strict_types=1);

use Shared\Core\DatabaseConnection;
use Shared\Helpers\Response;

final class UserSocialAccountsController
{
    private UserSocialAccountsService $service;

    public function __construct()
    {
        $pdo = DatabaseConnection::getInstance();
        $this->service = new UserSocialAccountsService(new PdoUserSocialAccountsRepository($pdo));
    }

    // GET /v1/users/{user_id}/social-accounts
    public function index(array $params = []): void
    {
        $userId = (int)($params['user_id'] ?? 0);
        Response::json(['success' => true, 'data' => $this->service->listByUser($userId)], 200);
    }

    // GET /v1/social-accounts/{id}
    public function show(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        Response::json(['success' => true, 'data' => $this->service->getById($id)], 200);
    }

    // POST /v1/social-accounts
    public function upsert(array $params = []): void
    {
        $data   = $this->json();
        $result = $this->service->upsert($data);
        Response::json(['success' => true, 'data' => $result], 200);
    }

    // DELETE /v1/social-accounts/{id}
    public function destroy(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $this->service->delete($id);
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
