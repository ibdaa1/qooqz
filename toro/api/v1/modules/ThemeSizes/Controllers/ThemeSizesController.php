<?php
/**
 * TORO — v1/modules/ThemeSizes/Controllers/ThemeSizesController.php
 */
declare(strict_types=1);

use Shared\Core\DatabaseConnection;
use Shared\Helpers\Response;

final class ThemeSizesController
{
    private ThemeSizesService $service;

    public function __construct()
    {
        $pdo = DatabaseConnection::getInstance();
        $this->service = new ThemeSizesService(new PdoThemeSizesRepository($pdo));
    }

    // GET /v1/theme-sizes
    public function index(array $params = []): void
    {
        $filters = [
            'is_active' => isset($_GET['is_active']) ? (int)$_GET['is_active'] : null,
            'search'    => $_GET['search'] ?? null,
            'limit'     => (int)($_GET['limit']  ?? 50),
            'offset'    => (int)($_GET['offset'] ?? 0),
        ];
        Response::json(['success' => true, 'data' => $this->service->list($filters)], 200);
    }

    // GET /v1/theme-sizes/{id}
    public function show(array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        Response::json(['success' => true, 'data' => $this->service->getById($id)], 200);
    }

    // POST /v1/theme-sizes
    public function store(array $params = []): void
    {
        $data   = $this->json();
        $result = $this->service->create($data);
        Response::json(['success' => true, 'data' => $result], 201);
    }

    // PUT /v1/theme-sizes/{id}
    public function update(array $params = []): void
    {
        $id     = (int)($params['id'] ?? 0);
        $data   = $this->json();
        $result = $this->service->update($id, $data);
        Response::json(['success' => true, 'data' => $result], 200);
    }

    // DELETE /v1/theme-sizes/{id}
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
