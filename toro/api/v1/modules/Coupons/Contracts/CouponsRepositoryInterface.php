<?php
/**
 * TORO — v1/modules/Coupons/Contracts/CouponsRepositoryInterface.php
 */
declare(strict_types=1);

interface CouponsRepositoryInterface
{
    public function findAll(array $filters = []): array;
    public function countAll(array $filters = []): int;
    public function findById(int $id, ?string $lang = null): ?array;
    public function findByCode(string $code): ?array;

    public function create(array $data): int;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
    public function incrementUsesCount(int $id): bool;

    public function upsertTranslation(int $couponId, int $languageId, array $data): bool;
    public function getTranslations(int $couponId): array;

    public function resolveLanguageId(string $code): ?int;
}
