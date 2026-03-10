<?php
/**
 * TORO — v1/modules/ProductAttributeValues/Contracts/ProductAttributeValuesRepositoryInterface.php
 */
declare(strict_types=1);

interface ProductAttributeValuesRepositoryInterface
{
    public function findByProduct(int $productId, ?string $lang = null): array;
    public function attach(int $productId, int $valueId): int;
    public function detach(int $productId, int $valueId): bool;
    public function syncForProduct(int $productId, array $valueIds): void;
}
