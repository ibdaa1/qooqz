<?php
/**
 * TORO — v1/modules/ProductAttributeValues/Services/ProductAttributeValuesService.php
 */
declare(strict_types=1);

final class ProductAttributeValuesService
{
    public function __construct(
        private readonly ProductAttributeValuesRepositoryInterface $repo
    ) {}

    public function getForProduct(int $productId, ?string $lang = null): array
    {
        return $this->repo->findByProduct($productId, $lang);
    }

    public function attach(int $productId, int $valueId): array
    {
        $id = $this->repo->attach($productId, $valueId);
        return ['id' => $id, 'product_id' => $productId, 'value_id' => $valueId];
    }

    public function detach(int $productId, int $valueId): bool
    {
        return $this->repo->detach($productId, $valueId);
    }

    public function sync(int $productId, array $valueIds): void
    {
        $this->repo->syncForProduct($productId, $valueIds);
    }
}
