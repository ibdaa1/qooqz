<?php
/**
 * TORO — v1/modules/Languages/Contracts/LanguagesRepositoryInterface.php
 */
declare(strict_types=1);

interface LanguagesRepositoryInterface
{
    // ── Read ───────────────────────────────────────────────────
    public function findAll(array $filters = []): array;
    public function countAll(array $filters = []): int;
    public function findById(int $id): ?array;
    public function findByCode(string $code): ?array;
    public function getDefault(): ?array;

    // ── Write ──────────────────────────────────────────────────
    public function create(array $data): int;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;   // soft delete (set is_active = 0)

    // ── Helpers ────────────────────────────────────────────────
    public function getDefaultLanguageId(): int;
    public function resolveLanguageId(string $code): ?int;
    public function isCodeUnique(string $code, ?int $excludeId = null): bool;
}