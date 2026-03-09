<?php
/**
 * TORO — v1/modules/UserTokens/Contracts/UserTokensRepositoryInterface.php
 */
declare(strict_types=1);

interface UserTokensRepositoryInterface
{
    public function findByHash(string $tokenHash, string $type): ?array;
    public function findActiveByUserId(int $userId, string $type): array;
    public function create(array $data): int;
    public function markUsed(int $id): bool;
    public function revokeByUserId(int $userId, string $type): int;
    public function deleteExpired(): int;
}
