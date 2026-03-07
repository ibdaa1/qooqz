<?php
declare(strict_types=1);

/**
 * api/v1/models/categories/services/CategoriesService.php
 */
final class CategoriesService
{
    private PdoCategoriesRepository $repo;
    private CategoriesValidator     $validator;

    public function __construct(PdoCategoriesRepository $repo, CategoriesValidator $validator)
    {
        $this->repo      = $repo;
        $this->validator = $validator;
    }

    public function list(
        int $tenantId,
        int $limit = 500,
        int $offset = 0,
        string $lang = 'ar',
        ?int $entityId = null,
        ?int $isActive = null
    ): array {
        $items = $this->repo->list($tenantId, $limit, $offset, $lang, $entityId, $isActive);
        $total = $this->repo->count($tenantId, $isActive, $entityId);
        return ['items' => $items, 'total' => $total];
    }

    public function getActive(int $tenantId, string $lang = 'ar'): array
    {
        $items = $this->repo->getActive($tenantId, $lang);
        return ['items' => $items, 'total' => count($items)];
    }

    public function getFeatured(int $tenantId, string $lang = 'ar'): array
    {
        $items = $this->repo->getFeatured($tenantId, $lang);
        return ['items' => $items, 'total' => count($items)];
    }

    public function tree(int $tenantId, string $lang = 'ar'): array
    {
        return $this->repo->tree($tenantId, $lang);
    }

    public function getById(int $tenantId, int $id, string $lang = 'ar'): array
    {
        $row = $this->repo->find($tenantId, $id, $lang);
        if (!$row) {
            throw new RuntimeException("Category {$id} not found");
        }
        return $row;
    }

    public function validateSlug(int $tenantId, array $data): array
    {
        $slug = trim($data['slug'] ?? '');
        $excludeId = (int)($data['exclude_id'] ?? 0);
        $existing  = $this->repo->findIdBySlug($tenantId, $slug);
        $available = !$existing || ($existing === $excludeId);
        return ['slug' => $slug, 'available' => $available];
    }

    public function create(int $tenantId, array $data): array
    {
        $errors = $this->validator->validateCreate($data);
        if ($errors) {
            throw new InvalidArgumentException(json_encode($errors));
        }
        $id = $this->repo->create($tenantId, $data);
        return $this->repo->find($tenantId, $id) ?? ['id' => $id];
    }

    public function update(int $tenantId, array $data): array
    {
        $errors = $this->validator->validateUpdate($data);
        if ($errors) {
            throw new InvalidArgumentException(json_encode($errors));
        }
        $id = (int)$data['id'];
        $this->repo->update($tenantId, $id, $data);
        return $this->repo->find($tenantId, $id) ?? ['id' => $id];
    }

    public function delete(int $tenantId, array $data): bool
    {
        $id = (int)($data['id'] ?? 0);
        if (!$id) {
            throw new InvalidArgumentException('ID is required');
        }
        return $this->repo->delete($tenantId, $id);
    }

    public function bulkUpdate(int $tenantId, array $data): array
    {
        $updated = 0;
        foreach ((array)($data['items'] ?? []) as $item) {
            if (!empty($item['id'])) {
                $this->repo->update($tenantId, (int)$item['id'], $item);
                $updated++;
            }
        }
        return ['updated' => $updated];
    }

    public function deleteTranslation(int $tenantId, int $categoryId, string $languageCode): array
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM category_translations WHERE category_id = :cat_id AND language_code = :lang'
        );
        $stmt->execute([':cat_id' => $categoryId, ':lang' => $languageCode]);
        return ['deleted' => true, 'category_id' => $categoryId, 'language_code' => $languageCode];
    }
}
