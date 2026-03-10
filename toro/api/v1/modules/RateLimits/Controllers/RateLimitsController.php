<?php
/**
 * TORO — v1/modules/RateLimits/Controllers/RateLimitsController.php
 */
declare(strict_types=1);

use Shared\Core\DatabaseConnection;
use Shared\Helpers\Response;

final class RateLimitsController
{
    private RateLimitsService $service;

    public function __construct()
    {
        $pdo = DatabaseConnection::getInstance();
        $this->service = new RateLimitsService(new PdoRateLimitsRepository($pdo));
    }

    private function json(): array
    {
        return (array)json_decode(file_get_contents('php://input'), true);
    }

    // GET /v1/rate-limits/{key}
    public function show(array $params = []): void
    {
        $key = urldecode($params['key'] ?? '');
        Response::json(['success' => true, 'data' => $this->service->getByKey($key)], 200);
    }

    // GET /v1/rate-limits/{key}/blocked
    public function isBlocked(array $params = []): void
    {
        $key = urldecode($params['key'] ?? '');
        Response::json(['success' => true, 'blocked' => $this->service->isBlocked($key)], 200);
    }

    // POST /v1/rate-limits/increment
    public function increment(array $params = []): void
    {
        $data = $this->json();
        $key  = $data['key'] ?? '';
        if (empty($key)) {
            Response::json(['success' => false, 'error' => 'المفتاح مطلوب'], 422);
            return;
        }
        Response::json(['success' => true, 'data' => $this->service->increment($key)], 200);
    }

    // POST /v1/rate-limits/block
    public function block(array $params = []): void
    {
        $data     = $this->json();
        $key      = $data['key']      ?? '';
        $duration = (int)($data['duration'] ?? 3600);

        if (empty($key)) {
            Response::json(['success' => false, 'error' => 'المفتاح مطلوب'], 422);
            return;
        }

        Response::json(['success' => true, 'data' => $this->service->block($key, $duration)], 200);
    }

    // DELETE /v1/rate-limits/{key}
    public function reset(array $params = []): void
    {
        $key = urldecode($params['key'] ?? '');
        $this->service->reset($key);
        Response::json(['success' => true, 'message' => 'تم إعادة تعيين الحد بنجاح'], 200);
    }

    // DELETE /v1/rate-limits/cleanup
    public function cleanup(array $params = []): void
    {
        $count = $this->service->cleanup();
        Response::json(['success' => true, 'deleted' => $count], 200);
    }
}
