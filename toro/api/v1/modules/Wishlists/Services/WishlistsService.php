<?php
/**
 * TORO — v1/modules/Wishlists/Services/WishlistsService.php
 */
declare(strict_types=1);

final class WishlistsService
{
    public function __construct(
        private readonly WishlistsRepositoryInterface $repo
    ) {}

    public function getForUser(int $userId, array $filters = []): array
    {
        $items = $this->repo->findByUser($userId, $filters);
        $total = $this->repo->countByUser($userId);
        return ['data' => $items, 'total' => $total];
    }

    public function toggle(int $userId, int $productId): array
    {
        if ($this->repo->exists($userId, $productId)) {
            $this->repo->remove($userId, $productId);
            return ['in_wishlist' => false];
        }
        $this->repo->add($userId, $productId);
        return ['in_wishlist' => true];
    }

    public function add(int $userId, int $productId): bool
    {
        if ($this->repo->exists($userId, $productId)) {
            return false;
        }
        $this->repo->add($userId, $productId);
        return true;
    }

    public function remove(int $userId, int $productId): bool
    {
        return $this->repo->remove($userId, $productId);
    }

    public function clear(int $userId): int
    {
        return $this->repo->clear($userId);
    }

    public function isInWishlist(int $userId, int $productId): bool
    {
        return $this->repo->exists($userId, $productId);
    }
}
