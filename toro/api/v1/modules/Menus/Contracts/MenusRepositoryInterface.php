<?php
/**
 * TORO — v1/modules/Menus/Contracts/MenusRepositoryInterface.php
 */
declare(strict_types=1);

interface MenusRepositoryInterface
{
    public function findAll(): array;
    public function findById(int $id): ?array;
    public function findBySlug(string $slug): ?array;
    public function create(array $data): int;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
}
