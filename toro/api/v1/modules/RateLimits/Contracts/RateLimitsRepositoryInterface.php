<?php
/**
 * TORO — v1/modules/RateLimits/Contracts/RateLimitsRepositoryInterface.php
 */
declare(strict_types=1);

interface RateLimitsRepositoryInterface
{
    public function findByKey(string $key): ?array;
    public function increment(string $key): array;
    public function block(string $key, string $blockedUntil): bool;
    public function reset(string $key): bool;
    public function deleteExpiredBlocks(): int;
}
