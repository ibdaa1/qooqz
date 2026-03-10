<?php
/**
 * TORO — v1/modules/Wishlists/Contracts/WishlistsRepositoryInterface.php
 */
declare(strict_types=1);

interface WishlistsRepositoryInterface
{
    public function findByUser(int $userId, array $filters): array;
    public function countByUser(int $userId): int;
    public function exists(int $userId, int $productId): bool;
    public function add(int $userId, int $productId): int;
    public function remove(int $userId, int $productId): bool;
    public function clear(int $userId): int;
}
