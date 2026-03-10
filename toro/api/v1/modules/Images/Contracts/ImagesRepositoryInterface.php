<?php
/**
 * TORO — v1/modules/Images/Contracts/ImagesRepositoryInterface.php
 */
declare(strict_types=1);

interface ImagesRepositoryInterface
{
    // ── Read ───────────────────────────────────────────────────
    public function findAll(array $filters = []): array;
    public function countAll(array $filters = []): int;
    public function findById(int $id): ?array;
    public function findByOwner(int $ownerId, ?int $imageTypeId = null): array;
    public function findMainByOwner(int $ownerId): ?array;

    // ── Write ──────────────────────────────────────────────────
    public function create(array $data): int;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
    public function deleteByOwner(int $ownerId, ?int $imageTypeId = null): bool;
    public function setMain(int $id, int $ownerId): bool;
}
