<?php
/**
 * TORO — v1/modules/UserAddresses/Contracts/UserAddressesRepositoryInterface.php
 */
declare(strict_types=1);

interface UserAddressesRepositoryInterface
{
    public function findByUserId(int $userId): array;
    public function findById(int $id): ?array;
    public function create(array $data): int;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
    public function setDefault(int $id, int $userId): bool;
    public function clearDefault(int $userId): bool;
}
