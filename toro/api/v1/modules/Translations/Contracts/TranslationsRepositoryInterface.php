<?php
/**
 * TORO — v1/modules/Translations/Contracts/TranslationsRepositoryInterface.php
 */
declare(strict_types=1);

interface TranslationsRepositoryInterface
{
    // ── Keys ───────────────────────────────────────────────────
    public function findAllKeys(array $filters = []): array;
    public function countAllKeys(array $filters = []): int;
    public function findKeyById(int $id): ?array;
    public function findKeyByName(string $keyName): ?array;
    public function createKey(array $data): int;
    public function updateKey(int $id, array $data): bool;
    public function deleteKey(int $id): bool;

    // ── Values ─────────────────────────────────────────────────
    public function getValue(int $keyId, int $languageId): ?string;
    public function getValuesByKeyId(int $keyId): array;
    public function getValuesByLanguageId(int $languageId): array;
    public function upsertValue(int $keyId, int $languageId, string $value): bool;
    public function deleteValue(int $keyId, int $languageId): bool;
    public function deleteAllValuesForKey(int $keyId): bool;

    // ── Bulk operations ────────────────────────────────────────
    public function getTranslationsByKeys(array $keys, int $languageId): array;
    public function getAllTranslationsForLanguage(int $languageId): array;

    // ── Language helpers ───────────────────────────────────────
    public function resolveLanguageId(string $code): ?int;
    public function getDefaultLanguageId(): int;
}