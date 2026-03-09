<?php
/**
 * TORO — v1/modules/CsrfTokens/Controllers/CsrfTokensController.php
 */
declare(strict_types=1);

use Shared\Core\DatabaseConnection;
use Shared\Helpers\Response;

final class CsrfTokensController
{
    private CsrfTokensService $service;

    public function __construct()
    {
        $pdo = DatabaseConnection::getInstance();
        $this->service = new CsrfTokensService(new PdoCsrfTokensRepository($pdo));
    }

    private function json(): array
    {
        return (array)json_decode(file_get_contents('php://input'), true);
    }

    // POST /v1/csrf/generate
    public function generate(array $params = []): void
    {
        $data      = $this->json();
        $sessionId = $data['session_id'] ?? session_id() ?: '';

        if (empty($sessionId)) {
            Response::json(['success' => false, 'error' => 'معرف الجلسة مطلوب'], 422);
            return;
        }

        Response::json(['success' => true, 'data' => $this->service->generate($sessionId)], 200);
    }

    // POST /v1/csrf/verify
    public function verify(array $params = []): void
    {
        $data      = $this->json();
        $token     = $data['token']      ?? '';
        $sessionId = $data['session_id'] ?? session_id() ?: '';

        $valid = $this->service->verify($token, $sessionId);
        Response::json(['success' => true, 'valid' => $valid], 200);
    }

    // DELETE /v1/csrf/cleanup (internal use)
    public function cleanup(array $params = []): void
    {
        $count = $this->service->cleanup();
        Response::json(['success' => true, 'deleted' => $count], 200);
    }
}
