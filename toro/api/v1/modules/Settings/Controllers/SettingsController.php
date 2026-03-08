<?php
namespace V1\modules\Settings\Controllers;

use V1\modules\Settings\Services\SettingsService;
use Shared\Core\DatabaseConnection; // لاستخدام الاتصال من النظام

class SettingsController
{
    private function getService(): SettingsService
    {
        // نستخدم الاتصال من النظام الأساسي
        $pdo = DatabaseConnection::getInstance();
        
        // تأكد من أن الـ Repository يستخدم الـ Namespace الصحيح أيضاً
        $repository = new \V1\modules\Settings\Repositories\PdoSettingsRepository($pdo);
        return new SettingsService($repository);
    }

    public function getPublic(array $params): void
    {
        try {
            $data = $this->getService()->getPublicSettings();
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'data' => $data]);
        } catch (\Throwable $e) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function index(array $params): void
    {
        // Admin logic
    }

    public function update(array $params): void
    {
        // Update logic
    }
}