<?php
/**
 * TORO — v1/modules/Users/Repositories/PdoUsersRepository.php
 *
 * Note: `avatar` is intentionally omitted — images are served via the unified
 * images table (owner_type='user', owner_id=user.id).
 */
declare(strict_types=1);

final class PdoUsersRepository implements UsersRepositoryInterface
{
    public function __construct(private readonly \PDO $pdo) {}

    // ── List ───────────────────────────────────────────────────
    public function findAll(array $filters = []): array
    {
        $isActive  = $filters['is_active']  ?? null;
        $roleId    = $filters['role_id']    ?? null;
        $search    = $filters['search']     ?? null;
        $withTrashed = !empty($filters['with_trashed']);
        $limit     = max(1, min((int)($filters['limit']  ?? 50), 200));
        $offset    = max(0, (int)($filters['offset'] ?? 0));

        $sql    = 'SELECT id, role_id, first_name, last_name, email, email_verified_at,
                          phone, phone_verified_at, language_id, is_active,
                          last_login_at, created_at, updated_at, deleted_at
                   FROM users WHERE 1=1';
        $params = [];

        if (!$withTrashed) {
            $sql .= ' AND deleted_at IS NULL';
        }
        if ($isActive !== null) {
            $sql .= ' AND is_active = :is_active';
            $params[':is_active'] = (int)$isActive;
        }
        if ($roleId !== null) {
            $sql .= ' AND role_id = :role_id';
            $params[':role_id'] = (int)$roleId;
        }
        if ($search !== null) {
            $sql .= ' AND (first_name LIKE :s1 OR last_name LIKE :s2 OR email LIKE :s3)';
            $params[':s1'] = '%' . $search . '%';
            $params[':s2'] = '%' . $search . '%';
            $params[':s3'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY id DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, is_int($val) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit',  $limit,  \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    // ── Count ──────────────────────────────────────────────────
    public function countAll(array $filters = []): int
    {
        $isActive    = $filters['is_active']  ?? null;
        $roleId      = $filters['role_id']    ?? null;
        $search      = $filters['search']     ?? null;
        $withTrashed = !empty($filters['with_trashed']);

        $sql    = 'SELECT COUNT(*) FROM users WHERE 1=1';
        $params = [];

        if (!$withTrashed) {
            $sql .= ' AND deleted_at IS NULL';
        }
        if ($isActive !== null) {
            $sql .= ' AND is_active = :is_active';
            $params[':is_active'] = (int)$isActive;
        }
        if ($roleId !== null) {
            $sql .= ' AND role_id = :role_id';
            $params[':role_id'] = (int)$roleId;
        }
        if ($search !== null) {
            $sql .= ' AND (first_name LIKE :s1 OR last_name LIKE :s2 OR email LIKE :s3)';
            $params[':s1'] = '%' . $search . '%';
            $params[':s2'] = '%' . $search . '%';
            $params[':s3'] = '%' . $search . '%';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    // ── Find ───────────────────────────────────────────────────
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, role_id, first_name, last_name, email, email_verified_at,
                    phone, phone_verified_at, language_id, is_active,
                    last_login_at, created_at, updated_at, deleted_at
             FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, role_id, first_name, last_name, email, email_verified_at,
                    phone, phone_verified_at, password_hash, language_id, is_active,
                    last_login_at, created_at, updated_at, deleted_at
             FROM users WHERE email = :email LIMIT 1'
        );
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // ── Create ─────────────────────────────────────────────────
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (role_id, first_name, last_name, email, phone, password_hash, language_id, is_active)
             VALUES (:role_id, :first_name, :last_name, :email, :phone, :password_hash, :language_id, :is_active)'
        );
        $stmt->execute([
            ':role_id'       => (int)($data['role_id']       ?? 4),
            ':first_name'    => $data['first_name'],
            ':last_name'     => $data['last_name'],
            ':email'         => $data['email'],
            ':phone'         => $data['phone']         ?? null,
            ':password_hash' => $data['password_hash'] ?? null,
            ':language_id'   => isset($data['language_id']) ? (int)$data['language_id'] : null,
            ':is_active'     => (int)($data['is_active'] ?? 1),
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    // ── Update ─────────────────────────────────────────────────
    public function update(int $id, array $data): bool
    {
        $allowed = [
            'role_id', 'first_name', 'last_name', 'email', 'phone',
            'password_hash', 'language_id', 'is_active',
            'email_verified_at', 'phone_verified_at', 'last_login_at', 'remember_token',
        ];
        $sets   = [];
        $params = [':id' => $id];

        foreach ($allowed as $field) {
            if (!array_key_exists($field, $data)) continue;
            $sets[] = "{$field} = :{$field}";
            $params[":{$field}"] = $data[$field];
        }

        if (empty($sets)) return false;

        $stmt = $this->pdo->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    // ── Soft-delete / Restore ──────────────────────────────────
    public function softDelete(int $id): bool
    {
        $stmt = $this->pdo->prepare('UPDATE users SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function restore(int $id): bool
    {
        $stmt = $this->pdo->prepare('UPDATE users SET deleted_at = NULL WHERE id = :id AND deleted_at IS NOT NULL');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function hardDelete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
