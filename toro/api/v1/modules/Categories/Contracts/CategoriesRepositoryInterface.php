<?php
/**
 * TORO — v1/modules/Categories/Contracts/CategoriesRepositoryInterface.php
 */
declare(strict_types=1);

interface CategoriesRepositoryInterface
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
    public function upsertTranslation(int $categoryId, int $languageId, array $data): bool;
    public function getTranslations(int $categoryId): array;

    // ── Language helpers ───────────────────────────────────────
    public function resolveLanguageId(string $code): ?int;
    public function getDefaultLanguageId(): int;
}