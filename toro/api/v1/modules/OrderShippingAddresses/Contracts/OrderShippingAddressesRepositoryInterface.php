<?php
/**
 * TORO — v1/modules/OrderShippingAddresses/Contracts/OrderShippingAddressesRepositoryInterface.php
 */
declare(strict_types=1);

interface OrderShippingAddressesRepositoryInterface
{
    public function findByOrderId(int $orderId): ?array;
    public function upsert(int $orderId, array $data): int;
    public function delete(int $orderId): bool;
}
