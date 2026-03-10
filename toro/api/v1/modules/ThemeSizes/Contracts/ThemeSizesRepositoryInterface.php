<?php
/**
 * TORO — v1/modules/ThemeSizes/Contracts/ThemeSizesRepositoryInterface.php
 */
declare(strict_types=1);

interface ThemeSizesRepositoryInterface
{
    public function findAll(array $filters = []): array;
    public function countAll(array $filters = []): int;
    public function findById(int $id): ?array;
    public function findByName(string $name): ?array;
    public function create(array $data): int;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
}
