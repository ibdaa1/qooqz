<?php
/**
 * TORO — v1/modules/Pages/Contracts/PagesRepositoryInterface.php
 */
declare(strict_types=1);

interface PagesRepositoryInterface
{
    public function findAll(array $filters = []): array;
    public function countAll(array $filters = []): int;
    public function findById(int $id, ?string $lang = null): ?array;
    public function findBySlug(string $slug, ?string $lang = null): ?array;

    public function create(array $data): int;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;

    public function upsertTranslation(int $pageId, int $languageId, array $data): bool;
    public function getTranslations(int $pageId): array;

    public function resolveLanguageId(string $code): ?int;
}
