<?php
/**
 * TORO — v1/modules/CsrfTokens/Repositories/PdoCsrfTokensRepository.php
 */
declare(strict_types=1);

final class PdoCsrfTokensRepository implements CsrfTokensRepositoryInterface
{
    public function __construct(private readonly \PDO $pdo) {}

    public function findByToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM csrf_tokens WHERE token = :token AND expires_at > NOW() LIMIT 1'
        );
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findBySession(string $sessionId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM csrf_tokens WHERE session_id = :session_id AND expires_at > NOW() LIMIT 1'
        );
        $stmt->execute([':session_id' => $sessionId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(string $token, string $sessionId, string $expiresAt): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO csrf_tokens (token, session_id, expires_at)
             VALUES (:token, :session_id, :expires_at)'
        );
        $stmt->execute([
            ':token'      => $token,
            ':session_id' => $sessionId,
            ':expires_at' => $expiresAt,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM csrf_tokens WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function deleteExpired(): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM csrf_tokens WHERE expires_at <= NOW()');
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function deleteBySession(string $sessionId): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM csrf_tokens WHERE session_id = :session_id');
        $stmt->execute([':session_id' => $sessionId]);
        return $stmt->rowCount();
    }
}
