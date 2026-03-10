<?php
/**
 * TORO — v1/modules/Users/Contracts/UsersRepositoryInterface.php
 */
declare(strict_types=1);

interface UsersRepositoryInterface
{
    public function findAll(array $filters = []): array;
    public function countAll(array $filters = []): int;
    public function findById(int $id): ?array;
    public function findByEmail(string $email): ?array;
    public function create(array $data): int;
    public function update(int $id, array $data): bool;
    public function softDelete(int $id): bool;
    public function restore(int $id): bool;
    public function hardDelete(int $id): bool;
}
