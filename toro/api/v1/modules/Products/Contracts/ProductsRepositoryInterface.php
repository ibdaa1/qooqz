<?php
/**
 * TORO — v1/modules/Products/Contracts/ProductsRepositoryInterface.php
 */
declare(strict_types=1);

interface ProductsRepositoryInterface
{
    // ── Read ───────────────────────────────────────────────────
    public function findAll(array $filters = []): array;
    public function countAll(array $filters = []): int;
    public function findById(int $id, ?string $lang = null): ?array;
    public function findBySku(string $sku, ?string $lang = null): ?array;

    // ── Write ──────────────────────────────────────────────────
    public function create(array $data): int;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
    public function softDelete(int $id): bool;

    // ── Translations ───────────────────────────────────────────
    public function upsertTranslation(int $productId, int $languageId, array $data): bool;
    public function getTranslations(int $productId): array;

    // ── Images ─────────────────────────────────────────────────
    public function getImages(int $productId): array;

    // ── Language helpers ───────────────────────────────────────
    public function resolveLanguageId(string $code): ?int;
    public function getDefaultLanguageId(): int;
}
