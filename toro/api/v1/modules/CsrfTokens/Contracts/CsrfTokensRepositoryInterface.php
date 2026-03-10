<?php
/**
 * TORO — v1/modules/CsrfTokens/Contracts/CsrfTokensRepositoryInterface.php
 */
declare(strict_types=1);

interface CsrfTokensRepositoryInterface
{
    public function findByToken(string $token): ?array;
    public function findBySession(string $sessionId): ?array;
    public function create(string $token, string $sessionId, string $expiresAt): int;
    public function delete(int $id): bool;
    public function deleteExpired(): int;
    public function deleteBySession(string $sessionId): int;
}
