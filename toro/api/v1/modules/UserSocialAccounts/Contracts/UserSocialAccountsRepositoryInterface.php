<?php
/**
 * TORO — v1/modules/UserSocialAccounts/Contracts/UserSocialAccountsRepositoryInterface.php
 */
declare(strict_types=1);

interface UserSocialAccountsRepositoryInterface
{
    public function findByUserId(int $userId): array;
    public function findByProvider(string $provider, string $providerUid): ?array;
    public function findById(int $id): ?array;
    public function create(array $data): int;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
    public function deleteByUserId(int $userId): int;
}
