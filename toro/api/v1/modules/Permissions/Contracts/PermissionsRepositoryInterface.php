<?php
/**
 * TORO — v1/modules/Permissions/Contracts/PermissionsRepositoryInterface.php
 */
declare(strict_types=1);

interface PermissionsRepositoryInterface
{
    // ── Read ───────────────────────────────────────────────────
    public function findAll(array $filters = []): array;
    public function countAll(array $filters = []): int;
    public function findById(int $id): ?array;
    public function findBySlug(string $slug): ?array;
    public function findByGroup(?string $group): array;
    public function getAllGrouped(): array; // returns [group => [permissions]]

    // ── Write ──────────────────────────────────────────────────
    public function create(array $data): int;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
}