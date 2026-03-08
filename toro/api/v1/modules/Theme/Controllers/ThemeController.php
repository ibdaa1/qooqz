<?php
namespace V1\modules\Theme\Controllers;

use V1\modules\Theme\Services\ThemeService;
use V1\modules\Theme\Repositories\PdoThemeRepository;
use Shared\Core\DatabaseConnection;

class ThemeController
{
    private function getService(): ThemeService
    {
        $pdo = DatabaseConnection::getInstance();
        $repository = new PdoThemeRepository($pdo);
        return new ThemeService($repository);
    }

    // GET /v1/theme/css
    public function getCss($params)
    {
        try {
            $css = $this->getService()->getActiveCssVariables();
            header('Content-Type: text/css'); // مهم: نوع المحتوى CSS
            echo $css;
        } catch (\Throwable $e) {
            echo "/* Error generating CSS: " . $e->getMessage() . " */";
        }
    }

    // GET /v1/theme (Admin)
    public function index($params)
    {
        try {
            $data = $this->getService()->getAllForAdmin();
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'data' => $data]);
        } catch (\Throwable $e) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // PUT /v1/theme/{id} (Admin)
    public function update($params)
    {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $id = (int)($params['id'] ?? 0);

        if (!$id || !isset($body['value'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'ID and Value required']);
            return;
        }

        try {
            $updated = $this->getService()->updateColor($id, $body['value']);
            header('Content-Type: application/json');
            echo json_encode(['status' => $updated ? 'success' : 'error']);
        } catch (\Throwable $e) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}