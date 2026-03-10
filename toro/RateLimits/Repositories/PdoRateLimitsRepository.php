<?php
/**
 * TORO — v1/modules/RateLimits/Repositories/PdoRateLimitsRepository.php
 */
declare(strict_types=1);

final class PdoRateLimitsRepository implements RateLimitsRepositoryInterface
{
    public function __construct(private readonly \PDO $pdo) {}

    public function findByKey(string $key): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rate_limits WHERE `key` = :key LIMIT 1');
        $stmt->execute([':key' => $key]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function increment(string $key): array
    {
        // Upsert: insert or increment
        $stmt = $this->pdo->prepare(
            'INSERT INTO rate_limits (`key`, attempts)
             VALUES (:key, 1)
             ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = CURRENT_TIMESTAMP'
        );
        $stmt->execute([':key' => $key]);

        return $this->findByKey($key) ?? ['key' => $key, 'attempts' => 1, 'blocked_until' => null];
    }

    public function block(string $key, string $blockedUntil): bool
    {
        $row = $this->findByKey($key);

        if ($row) {
            $stmt = $this->pdo->prepare(
                'UPDATE rate_limits SET blocked_until = :blocked_until WHERE `key` = :key'
            );
            $stmt->execute([':blocked_until' => $blockedUntil, ':key' => $key]);
            return $stmt->rowCount() > 0;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO rate_limits (`key`, blocked_until)
             VALUES (:key, :blocked_until)'
        );
        $stmt->execute([':key' => $key, ':blocked_until' => $blockedUntil]);
        return true;
    }

    public function reset(string $key): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM rate_limits WHERE `key` = :key');
        $stmt->execute([':key' => $key]);
        return $stmt->rowCount() > 0;
    }

    public function deleteExpiredBlocks(): int
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM rate_limits WHERE blocked_until IS NOT NULL AND blocked_until <= NOW()'
        );
        $stmt->execute();
        return $stmt->rowCount();
    }
}
