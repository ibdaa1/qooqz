<?php
declare(strict_types=1);

interface ThemeRepositoryInterface
{
    // ── Read ───────────────────────────────────────────────────
    public function getActiveColors(): array;
    public function findAll(array $filters = []): array;
    public function countAll(array $filters = []): int;
    public function findById(int $id): ?array;
    public function findByVariable(string $variable): ?array;

    // ── Write ──────────────────────────────────────────────────
    public function create(array $data): int;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
}
