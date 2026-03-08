<?php
/**
 * TORO — v1/modules/Orders/Contracts/OrdersRepositoryInterface.php
 */
declare(strict_types=1);

interface OrdersRepositoryInterface
{
    // Orders
    public function findAll(array $filters = []): array;
    public function countAll(array $filters = []): int;
    public function findById(int $id): ?array;
    public function findByNumber(string $orderNumber): ?array;
    public function create(array $data): int;
    public function updateStatus(int $id, string $status): bool;
    public function update(int $id, array $data): bool;

    // Order Items
    public function getItems(int $orderId): array;
    public function addItem(int $orderId, array $data): int;

    // Status History
    public function getStatusHistory(int $orderId): array;
    public function addStatusHistory(int $orderId, string $status, ?string $note, ?int $createdBy): int;

    // Helpers
    public function generateOrderNumber(): string;
}
