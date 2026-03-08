<?php
/**
 * TORO — v1/modules/Attributes/Contracts/AttributesRepositoryInterface.php
 */
declare(strict_types=1);

interface AttributesRepositoryInterface
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
    public function upsertTranslation(int $attributeId, int $languageId, array $data): bool;
    public function getTranslations(int $attributeId): array;

    // ── Values ─────────────────────────────────────────────────
    public function getValues(int $attributeId, ?string $lang = null): array;

    // ── Language helpers ───────────────────────────────────────
    public function resolveLanguageId(string $code): ?int;
    public function getDefaultLanguageId(): int;
}
