<?php
/**
 * TORO — v1/modules/Brands/Contracts/BrandsRepositoryInterface.php
 */
declare(strict_types=1);

interface BrandsRepositoryInterface
{
    // ── Read ───────────────────────────────────────────────────
    public function findAll(array $filters = []): array;
    public function countAll(array $filters = []): int;
    public function findById(int $id, ?string $lang = null): ?array;
    public function findBySlug(string $slug, ?string $lang = null): ?array;

    // ── Write ──────────────────────────────────────────────────
    public function create(array $data): int;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;

    // ── Translations ───────────────────────────────────────────
    public function upsertTranslation(int $brandId, int $languageId, array $data): bool;
    public function getTranslations(int $brandId): array;

    // ── Language helpers ───────────────────────────────────────
    public function resolveLanguageId(string $code): ?int;
    public function getDefaultLanguageId(): int;
}
