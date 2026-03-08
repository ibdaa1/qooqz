<?php
namespace V1\modules\Settings\Services;

use V1\modules\Settings\Repositories\PdoSettingsRepository;

class SettingsService
{
    private PdoSettingsRepository $repository;

    public function __construct(PdoSettingsRepository $repository)
    {
        $this->repository = $repository;
    }

    public function getPublicSettings(): array
    {
        return $this->repository->getPublicSettings();
    }

    public function getAllSettingsForAdmin(): array
    {
        $rows = $this->repository->getAllSettings();
        return array_map(function($row) {
            return [
                'id' => (int)$row['id'],
                'group' => $row['group'],
                'key' => $row['key'],
                'value' => $row['value'],
                'type' => $row['type'],
                'is_public' => (bool)$row['is_public']
            ];
        }, $rows);
    }

    public function getSettingById(int $id): ?array
    {
        return $this->repository->findById($id);
    }

    public function updateSetting(int $id, string $value): bool
    {
        return $this->repository->update($id, $value);
    }
}