<?php
declare(strict_types=1);

/**
 * Unified Entity Products Controller
 * Handles both product-level and variant-level operations
 */
final class EntityProductsController
{
    private EntityProductsService $service;

    public function __construct(EntityProductsService $service)
    {
        $this->service = $service;
    }

    public function list(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        return $this->service->list($limit, $offset, $filters, $orderBy, $orderDir);
    }

    public function get(int $id): ?array
    {
        return $this->service->get($id);
    }

    public function getByEntityAndProduct(int $entityId, int $productId): ?array
    {
        return $this->service->getByEntityAndProduct($entityId, $productId);
    }

    public function getByEntityAndVariant(int $entityId, int $variantId): ?array
    {
        return $this->service->getByEntityAndVariant($entityId, $variantId);
    }

    public function getEntityProducts(int $entityId): array
    {
        return $this->service->getEntityProducts($entityId);
    }

    public function getEntityVariants(int $entityId): array
    {
        return $this->service->getEntityVariants($entityId);
    }

    public function getEntityProductVariants(int $entityId, int $productId): array
    {
        return $this->service->getEntityProductVariants($entityId, $productId);
    }

    public function create(array $data): int
    {
        return $this->service->create($data);
    }

    public function update(int $id, array $data): void
    {
        $this->service->update($id, $data);
    }

    public function saveEntityProducts(int $entityId, int $tenantId, array $products): array
    {
        return $this->service->saveEntityProducts($entityId, $tenantId, $products);
    }

    public function saveEntityVariants(int $entityId, int $tenantId, array $variants): array
    {
        return $this->service->saveEntityVariants($entityId, $tenantId, $variants);
    }

    public function delete(int $id): void
    {
        $this->service->delete($id);
    }

    public function deleteEntityProducts(int $entityId): void
    {
        $this->service->deleteEntityProducts($entityId);
    }

    public function deleteEntityVariants(int $entityId): void
    {
        $this->service->deleteEntityVariants($entityId);
    }

    public function deleteEntityProductVariants(int $entityId, int $productId): void
    {
        $this->service->deleteEntityProductVariants($entityId, $productId);
    }

    public function getStatistics(): array
    {
        return $this->service->getStatistics();
    }
}
