<?php
/**
 * TORO — v1/modules/UserTokens/Repositories/PdoUserTokensRepository.php
 */
declare(strict_types=1);

final class PdoUserTokensRepository implements UserTokensRepositoryInterface
{
    public function __construct(private readonly \PDO $pdo) {}

    public function findByHash(string $tokenHash, string $type): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM user_tokens
             WHERE token_hash = :hash AND type = :type
               AND used_at IS NULL AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([':hash' => $tokenHash, ':type' => $type]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findActiveByUserId(int $userId, string $type): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, type, expires_at, used_at, created_at
             FROM user_tokens
             WHERE user_id = :user_id AND type = :type
               AND used_at IS NULL AND expires_at > NOW()
             ORDER BY id DESC'
        );
        $stmt->execute([':user_id' => $userId, ':type' => $type]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_tokens (user_id, token_hash, type, expires_at)
             VALUES (:user_id, :token_hash, :type, :expires_at)'
        );
        $stmt->execute([
            ':user_id'    => (int)$data['user_id'],
            ':token_hash' => $data['token_hash'],
            ':type'       => $data['type'],
            ':expires_at' => $data['expires_at'],
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function markUsed(int $id): bool
    {
        $stmt = $this->pdo->prepare('UPDATE user_tokens SET used_at = NOW() WHERE id = :id AND used_at IS NULL');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function revokeByUserId(int $userId, string $type): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE user_tokens SET used_at = NOW()
             WHERE user_id = :user_id AND type = :type AND used_at IS NULL'
        );
        $stmt->execute([':user_id' => $userId, ':type' => $type]);
        return $stmt->rowCount();
    }

    public function deleteExpired(): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM user_tokens WHERE expires_at < NOW()');
        $stmt->execute();
        return $stmt->rowCount();
    }
}
