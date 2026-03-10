<?php
declare(strict_types=1);

interface SettingsRepositoryInterface
{
    // ── Read ───────────────────────────────────────────────────
    public function getPublicSettings(): array;
    public function getAllSettings(array $filters = []): array;
    public function countAll(array $filters = []): int;
    public function findById(int $id): ?array;
    public function findByKey(string $key): ?array;
    public function findByGroup(string $group): array;

    // ── Write ──────────────────────────────────────────────────
    public function create(array $data): int;
    public function update(int $id, string $value): bool;
    public function delete(int $id): bool;
}
