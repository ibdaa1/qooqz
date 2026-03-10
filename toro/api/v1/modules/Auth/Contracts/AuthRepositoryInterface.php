<?php
/**
 * TORO — v1/modules/Auth/Contracts/AuthRepositoryInterface.php
 */
declare(strict_types=1);

interface AuthRepositoryInterface
{
    public function findByEmail(string $email): ?array;
    public function findById(int $id): ?array;
    public function createUser(array $data): int;
    public function updateUser(int $id, array $data): bool;

    // Social accounts
    public function findSocialAccount(string $provider, string $uid): ?array;
    public function createSocialAccount(int $userId, string $provider, string $uid, ?string $token): bool;

    // Tokens
    public function saveToken(int $userId, string $type, string $hash, \DateTimeImmutable $expiresAt): bool;
    public function findToken(string $type, string $hash): ?array;
    public function consumeToken(int $tokenId): bool;
    public function deleteExpiredTokens(): void;

    // Language
    public function findLanguageByCode(string $code): ?array;
    public function getDefaultLanguageId(): int;
}
