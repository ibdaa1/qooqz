<?php
/**
 * TORO — v1/modules/Banners/Contracts/BannersRepositoryInterface.php
 */
declare(strict_types=1);

interface BannersRepositoryInterface
{
    public function findAll(array $filters = []): array;
    public function countAll(array $filters = []): int;
    public function findById(int $id, ?string $lang = null): ?array;

    public function create(array $data): int;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;

    public function upsertTranslation(int $bannerId, int $languageId, array $data): bool;
    public function getTranslations(int $bannerId): array;

    public function resolveLanguageId(string $code): ?int;
}
