<?php
/**
 * TORO — v1/modules/ProductReviews/Contracts/ProductReviewsRepositoryInterface.php
 */
declare(strict_types=1);

interface ProductReviewsRepositoryInterface
{
    public function findByProduct(int $productId, array $filters): array;
    public function countByProduct(int $productId, array $filters): int;
    public function findById(int $id): ?array;
    public function create(array $data): int;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
    public function approve(int $id): bool;
}
