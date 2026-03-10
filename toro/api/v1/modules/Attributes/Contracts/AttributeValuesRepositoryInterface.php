<?php
/**
 * TORO — v1/modules/Attributes/Contracts/AttributeValuesRepositoryInterface.php
 */
declare(strict_types=1);

interface AttributeValuesRepositoryInterface
{
    // ── Read ───────────────────────────────────────────────────
    public function findById(int $id, ?string $lang = null): ?array;
    public function findByAttributeId(int $attributeId, ?string $lang = null): array;

    // ── Write ──────────────────────────────────────────────────
    public function create(array $data): int;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;

    // ── Translations ───────────────────────────────────────────
    public function upsertTranslation(int $valueId, int $languageId, array $data): bool;
    public function getTranslations(int $valueId): array;

    // ── Language helpers ───────────────────────────────────────
    public function resolveLanguageId(string $code): ?int;
}
