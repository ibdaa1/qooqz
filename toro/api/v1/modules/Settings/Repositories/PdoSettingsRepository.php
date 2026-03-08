<?php
declare(strict_types=1);

class PdoSettingsRepository implements SettingsRepositoryInterface
{
    private \PDO $db;

    public function __construct(\PDO $pdo)
    {
        $this->db = $pdo;
    }

    // ── Public (unauthenticated) ───────────────────────────────
    public function getPublicSettings(): array
    {
        $stmt = $this->db->prepare("SELECT `key`, `value`, `type` FROM settings WHERE is_public = 1");
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['key']] = $this->castValue($row['value'], $row['type']);
        }
        return $settings;
    }

    // ── List with optional filters ─────────────────────────────
    public function getAllSettings(array $filters = []): array
    {
        $group    = $filters['group']     ?? null;
        $isPublic = $filters['is_public'] ?? null;
        $search   = $filters['search']    ?? null;
        $limit    = max(1, min((int)($filters['limit']  ?? 100), 500));
        $offset   = max(0, (int)($filters['offset'] ?? 0));

        $sql    = 'SELECT * FROM settings WHERE 1=1';
        $params = [];

        if ($group !== null) {
            $sql .= ' AND `group` = :group';
            $params[':group'] = $group;
        }
        if ($isPublic !== null) {
            $sql .= ' AND is_public = :is_public';
            $params[':is_public'] = (int)(bool)$isPublic;
        }
        if ($search !== null) {
            $sql .= ' AND (`key` LIKE :search1 OR `value` LIKE :search2 OR `group` LIKE :search3)';
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $params[':search1'] = $like;
            $params[':search2'] = $like;
            $params[':search3'] = $like;
        }

        $sql .= ' ORDER BY `group` ASC, `key` ASC LIMIT :limit OFFSET :offset';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit',  $limit,  \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ── Count ──────────────────────────────────────────────────
    public function countAll(array $filters = []): int
    {
        $group    = $filters['group']     ?? null;
        $isPublic = $filters['is_public'] ?? null;
        $search   = $filters['search']    ?? null;

        $sql    = 'SELECT COUNT(*) FROM settings WHERE 1=1';
        $params = [];

        if ($group !== null) {
            $sql .= ' AND `group` = :group';
            $params[':group'] = $group;
        }
        if ($isPublic !== null) {
            $sql .= ' AND is_public = :is_public';
            $params[':is_public'] = (int)(bool)$isPublic;
        }
        if ($search !== null) {
            $sql .= ' AND (`key` LIKE :search1 OR `value` LIKE :search2 OR `group` LIKE :search3)';
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $params[':search1'] = $like;
            $params[':search2'] = $like;
            $params[':search3'] = $like;
        }

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    // ── Find by ID ─────────────────────────────────────────────
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM settings WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ── Find by Key ────────────────────────────────────────────
    public function findByKey(string $key): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM settings WHERE `key` = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ── Find by Group ──────────────────────────────────────────
    public function findByGroup(string $group): array
    {
        $stmt = $this->db->prepare('SELECT * FROM settings WHERE `group` = ? ORDER BY `key` ASC');
        $stmt->execute([$group]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ── Create ─────────────────────────────────────────────────
    public function create(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO settings (`group`, `key`, `value`, `type`, `is_public`)
            VALUES (:group, :key, :value, :type, :is_public)
        ');
        $stmt->execute([
            ':group'     => $data['group']     ?? 'general',
            ':key'       => $data['key'],
            ':value'     => $data['value']     ?? '',
            ':type'      => $data['type']      ?? 'string',
            ':is_public' => (int)(bool)($data['is_public'] ?? false),
        ]);
        return (int)$this->db->lastInsertId();
    }

    // ── Update ─────────────────────────────────────────────────
    public function update(int $id, string $value): bool
    {
        $stmt = $this->db->prepare('UPDATE settings SET `value` = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        return $stmt->execute([$value, $id]);
    }

    // ── Delete ─────────────────────────────────────────────────
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM settings WHERE id = ?');
        return $stmt->execute([$id]);
    }

    // ── Value casting helper ───────────────────────────────────
    private function castValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => (bool)$value,
            'number'  => str_contains((string)$value, '.') ? (float)$value : (int)$value,
            default   => $value,
        };
    }
}
