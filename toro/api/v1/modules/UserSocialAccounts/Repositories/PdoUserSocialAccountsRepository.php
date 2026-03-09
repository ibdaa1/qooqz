<?php
/**
 * TORO — v1/modules/UserSocialAccounts/Repositories/PdoUserSocialAccountsRepository.php
 */
declare(strict_types=1);

final class PdoUserSocialAccountsRepository implements UserSocialAccountsRepositoryInterface
{
    public function __construct(private readonly \PDO $pdo) {}

    public function findByUserId(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, provider, provider_uid, expires_at, created_at
             FROM user_social_accounts WHERE user_id = :user_id ORDER BY id ASC'
        );
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function findByProvider(string $provider, string $providerUid): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM user_social_accounts WHERE provider = :provider AND provider_uid = :uid LIMIT 1'
        );
        $stmt->execute([':provider' => $provider, ':uid' => $providerUid]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, provider, provider_uid, expires_at, created_at
             FROM user_social_accounts WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_social_accounts (user_id, provider, provider_uid, token, refresh_token, expires_at)
             VALUES (:user_id, :provider, :provider_uid, :token, :refresh_token, :expires_at)'
        );
        $stmt->execute([
            ':user_id'       => (int)$data['user_id'],
            ':provider'      => $data['provider'],
            ':provider_uid'  => $data['provider_uid'],
            ':token'         => $data['token']         ?? null,
            ':refresh_token' => $data['refresh_token'] ?? null,
            ':expires_at'    => $data['expires_at']    ?? null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $allowed = ['token', 'refresh_token', 'expires_at'];
        $sets    = [];
        $params  = [':id' => $id];

        foreach ($allowed as $field) {
            if (!array_key_exists($field, $data)) continue;
            $sets[] = "{$field} = :{$field}";
            $params[":{$field}"] = $data[$field];
        }

        if (empty($sets)) return false;

        $stmt = $this->pdo->prepare(
            'UPDATE user_social_accounts SET ' . implode(', ', $sets) . ' WHERE id = :id'
        );
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM user_social_accounts WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function deleteByUserId(int $userId): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM user_social_accounts WHERE user_id = :user_id');
        $stmt->execute([':user_id' => $userId]);
        return $stmt->rowCount();
    }
}
