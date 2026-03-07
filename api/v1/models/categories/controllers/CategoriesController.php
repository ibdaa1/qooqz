<?php
declare(strict_types=1);

/**
 * api/v1/models/categories/controllers/CategoriesController.php
 */
final class CategoriesController
{
    private CategoriesService $service;

    public function __construct(CategoriesService $service)
    {
        $this->service = $service;
    }

    public function list(
        int $tenantId,
        int $limit = 500,
        int $offset = 0,
        string $lang = 'ar',
        ?int $entityId = null,
        ?int $isActive = null
    ): array {
        return $this->service->list($tenantId, $limit, $offset, $lang, $entityId, $isActive);
    }

    public function getActive(int $tenantId, string $lang = 'ar'): array
    {
        return $this->service->getActive($tenantId, $lang);
    }

    public function getFeatured(int $tenantId, string $lang = 'ar'): array
    {
        return $this->service->getFeatured($tenantId, $lang);
    }

    public function tree(int $tenantId, string $lang = 'ar'): array
    {
        return $this->service->tree($tenantId, $lang);
    }

    public function getById(int $tenantId, int $id, string $lang = 'ar'): array
    {
        return $this->service->getById($tenantId, $id, $lang);
    }

    public function validateSlug(int $tenantId, array $data): array
    {
        return $this->service->validateSlug($tenantId, $data);
    }

    public function create(int $tenantId, array $data): array
    {
        return $this->service->create($tenantId, $data);
    }

    public function update(int $tenantId, array $data): array
    {
        return $this->service->update($tenantId, $data);
    }

    public function delete(int $tenantId, array $data): bool
    {
        return $this->service->delete($tenantId, $data);
    }

    public function bulkUpdate(int $tenantId, array $data): array
    {
        return $this->service->bulkUpdate($tenantId, $data);
    }

    public function deleteTranslation(int $tenantId, int $categoryId, string $languageCode): array
    {
        return $this->service->deleteTranslation($tenantId, $categoryId, $languageCode);
    }
}
