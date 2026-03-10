<?php
/**
 * TORO — v1/modules/ProductVariants/Contracts/ProductVariantsRepositoryInterface.php
 */
declare(strict_types=1);

interface ProductVariantsRepositoryInterface
{
    public function findByProduct(int $productId): array;
    public function findById(int $id): ?array;
    public function findBySku(string $sku): ?array;
    public function create(array $data): int;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
}
