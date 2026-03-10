<?php
/**
 * TORO — v1/modules/RateLimits/Services/RateLimitsService.php
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\NotFoundException;

final class RateLimitsService
{
    public function __construct(private readonly RateLimitsRepositoryInterface $repo) {}

    public function getByKey(string $key): array
    {
        $row = $this->repo->findByKey($key);
        if (!$row) throw new NotFoundException("مفتاح '{$key}' غير موجود");
        return $row;
    }

    public function increment(string $key): array
    {
        return $this->repo->increment($key);
    }

    public function block(string $key, int $durationSeconds = 3600): array
    {
        $blockedUntil = date('Y-m-d H:i:s', time() + $durationSeconds);
        $this->repo->block($key, $blockedUntil);
        return $this->repo->findByKey($key) ?? ['key' => $key, 'blocked_until' => $blockedUntil];
    }

    public function isBlocked(string $key): bool
    {
        $row = $this->repo->findByKey($key);
        if (!$row) return false;
        if (empty($row['blocked_until'])) return false;
        return strtotime($row['blocked_until']) > time();
    }

    public function reset(string $key): void
    {
        $this->repo->reset($key);
    }

    public function cleanup(): int
    {
        return $this->repo->deleteExpiredBlocks();
    }
}
