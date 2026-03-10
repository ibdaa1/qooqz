<?php
/**
 * TORO — v1/modules/Images/Contracts/ImageTypesRepositoryInterface.php
 */
declare(strict_types=1);

interface ImageTypesRepositoryInterface
{
    public function findAll(array $filters = []): array;
    public function countAll(array $filters = []): int;
    public function findById(int $id): ?array;
    public function findByCode(string $code): ?array;

    public function create(array $data): int;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
}
