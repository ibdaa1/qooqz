<?php
/**
 * TORO — v1/modules/Auth/Repositories/PdoAuthRepository.php
 */
declare(strict_types=1);
namespace V1\Modules\Auth\Repositories;

use PDO;
use V1\Modules\Auth\Contracts\AuthRepositoryInterface;

final class PdoAuthRepository implements AuthRepositoryInterface
{
    public function __construct(private readonly PDO $pdo) {}

    // ── Users ──────────────────────────────────────────────────
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT u.*, r.slug as role_slug, l.code as lang_code
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            LEFT JOIN languages l ON u.language_id = l.id
            WHERE u.email = :email AND u.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':email' => $email]);
        return $stmt->fetch() ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT u.id, u.first_name, u.last_name, u.email, u.phone,
                   u.avatar, u.is_active, u.email_verified_at, u.created_at,
                   r.slug as role_slug, r.name as role_name,
                   l.code as lang_code
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            LEFT JOIN languages l ON u.language_id = l.id
            WHERE u.id = :id AND u.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function createUser(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO users
                (role_id, first_name, last_name, email, password_hash, phone, language_id, is_active)
            VALUES
                (:role_id, :first_name, :last_name, :email, :password_hash, :phone, :language_id, 1)
        ");
        $stmt->execute([
            ':role_id'       => $data['role_id']       ?? 4,
            ':first_name'    => $data['first_name'],
            ':last_name'     => $data['last_name'],
            ':email'         => $data['email'],
            ':password_hash' => $data['password_hash']  ?? null,
            ':phone'         => $data['phone']           ?? null,
            ':language_id'   => $data['language_id']    ?? null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateUser(int $id, array $data): bool
    {
        if (empty($data)) return false;
        $sets = implode(', ', array_map(fn($k) => "`{$k}` = :{$k}", array_keys($data)));
        $stmt = $this->pdo->prepare("UPDATE users SET {$sets}, updated_at = NOW() WHERE id = :__id");
        $data[':__id'] = $id;          // rename to avoid collision
        $params = [];
        foreach ($data as $k => $v) $params[':' . ltrim($k,':')] = $v;
        $params[':__id'] = $id;
        return $stmt->execute($params);
    }

    // ── Social Accounts ────────────────────────────────────────
    public function findSocialAccount(string $provider, string $uid): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT sa.*, u.id as user_id, u.is_active
            FROM user_social_accounts sa
            JOIN users u ON sa.user_id = u.id
            WHERE sa.provider = :provider AND sa.provider_uid = :uid
            LIMIT 1
        ");
        $stmt->execute([':provider' => $provider, ':uid' => $uid]);
        return $stmt->fetch() ?: null;
    }

    public function createSocialAccount(int $userId, string $provider, string $uid, ?string $token): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO user_social_accounts (user_id, provider, provider_uid, token)
            VALUES (:user_id, :provider, :uid, :token)
            ON DUPLICATE KEY UPDATE token = :token, provider_uid = :uid
        ");
        return $stmt->execute([
            ':user_id'  => $userId,
            ':provider' => $provider,
            ':uid'      => $uid,
            ':token'    => $token,
        ]);
    }

    // ── Tokens ─────────────────────────────────────────────────
    public function saveToken(int $userId, string $type, string $hash, \DateTimeImmutable $expiresAt): bool
    {
        // حذف التوكن القديم لنفس المستخدم والنوع أولاً
        $this->pdo->prepare("DELETE FROM user_tokens WHERE user_id = :uid AND type = :type")
            ->execute([':uid' => $userId, ':type' => $type]);

        $stmt = $this->pdo->prepare("
            INSERT INTO user_tokens (user_id, token_hash, type, expires_at)
            VALUES (:user_id, :hash, :type, :expires_at)
        ");
        return $stmt->execute([
            ':user_id'    => $userId,
            ':hash'       => $hash,
            ':type'       => $type,
            ':expires_at' => $expiresAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findToken(string $type, string $hash): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM user_tokens
            WHERE type = :type AND token_hash = :hash AND used_at IS NULL AND expires_at > NOW()
            LIMIT 1
        ");
        $stmt->execute([':type' => $type, ':hash' => $hash]);
        return $stmt->fetch() ?: null;
    }

    public function consumeToken(int $tokenId): bool
    {
        return $this->pdo->prepare("UPDATE user_tokens SET used_at = NOW() WHERE id = :id")
            ->execute([':id' => $tokenId]);
    }

    public function deleteExpiredTokens(): void
    {
        $this->pdo->exec("DELETE FROM user_tokens WHERE expires_at < NOW() OR used_at IS NOT NULL");
    }

    // ── Languages ──────────────────────────────────────────────
    public function findLanguageByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare("SELECT id, code FROM languages WHERE code = :code AND is_active = 1 LIMIT 1");
        $stmt->execute([':code' => $code]);
        return $stmt->fetch() ?: null;
    }

    public function getDefaultLanguageId(): int
    {
        $id = $this->pdo->query("SELECT id FROM languages WHERE is_default = 1 LIMIT 1")->fetchColumn();
        return $id ? (int)$id : 1;
    }
}
