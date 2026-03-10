<?php
/**
 * TORO — v1/modules/Carts/Contracts/CartsRepositoryInterface.php
 */
declare(strict_types=1);

interface CartsRepositoryInterface
{
    // Cart
    public function findCartById(int $id): ?array;
    public function findCartByUser(int $userId): ?array;
    public function findCartBySession(string $sessionKey): ?array;
    public function createCart(array $data): int;
    public function updateCart(int $id, array $data): bool;
    public function deleteCart(int $id): bool;

    // Items
    public function getItems(int $cartId): array;
    public function findItem(int $cartId, int $productId, ?int $variantId): ?array;
    public function addItem(int $cartId, array $data): int;
    public function updateItem(int $itemId, array $data): bool;
    public function removeItem(int $itemId): bool;
    public function clearItems(int $cartId): bool;
}
