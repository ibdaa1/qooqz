<?php
declare(strict_types=1);

class PdoThemeRepository implements ThemeRepositoryInterface
{
    private \PDO $db;

    public function __construct(\PDO $pdo)
    {
        $this->db = $pdo;
    }

    // ── Active colors (public CSS endpoint) ────────────────────
    public function getActiveColors(): array
    {
        $stmt = $this->db->query('SELECT * FROM theme_colors WHERE is_active = 1 ORDER BY id ASC');
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ── List with filters ──────────────────────────────────────
    public function findAll(array $filters = []): array
    {
        $isActive = $filters['is_active'] ?? null;
        $search   = $filters['search']    ?? null;
        $limit    = max(1, min((int)($filters['limit']  ?? 100), 500));
        $offset   = max(0, (int)($filters['offset'] ?? 0));

        $sql    = 'SELECT * FROM theme_colors WHERE 1=1';
        $params = [];

        if ($isActive !== null) {
            $sql .= ' AND is_active = :is_active';
            $params[':is_active'] = (int)(bool)$isActive;
        }
        if ($search !== null) {
            $sql .= ' AND (`variable` LIKE :search1 OR `value` LIKE :search2)';
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $params[':search1'] = $like;
            $params[':search2'] = $like;
        }

        $sql .= ' ORDER BY id ASC LIMIT :limit OFFSET :offset';

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
        $isActive = $filters['is_active'] ?? null;
        $search   = $filters['search']    ?? null;

        $sql    = 'SELECT COUNT(*) FROM theme_colors WHERE 1=1';
        $params = [];

        if ($isActive !== null) {
            $sql .= ' AND is_active = :is_active';
            $params[':is_active'] = (int)(bool)$isActive;
        }
        if ($search !== null) {
            $sql .= ' AND (`variable` LIKE :search1 OR `value` LIKE :search2)';
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $params[':search1'] = $like;
            $params[':search2'] = $like;
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
        $stmt = $this->db->prepare('SELECT * FROM theme_colors WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ── Find by variable name ──────────────────────────────────
    public function findByVariable(string $variable): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM theme_colors WHERE `variable` = ?');
        $stmt->execute([$variable]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ── Create ─────────────────────────────────────────────────
    public function create(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO theme_colors (`variable`, `value`, `is_active`)
            VALUES (:variable, :value, :is_active)
        ');
        $stmt->execute([
            ':variable'  => $data['variable'],
            ':value'     => $data['value'],
            ':is_active' => (int)(bool)($data['is_active'] ?? true),
        ]);
        return (int)$this->db->lastInsertId();
    }

    // ── Update ─────────────────────────────────────────────────
    public function update(int $id, array $data): bool
    {
        $sets   = [];
        $params = [];

        if (array_key_exists('variable', $data)) {
            $sets[] = '`variable` = :variable';
            $params[':variable'] = $data['variable'];
        }
        if (array_key_exists('value', $data)) {
            $sets[] = '`value` = :value';
            $params[':value'] = $data['value'];
        }
        if (array_key_exists('is_active', $data)) {
            $sets[] = '`is_active` = :is_active';
            $params[':is_active'] = (int)(bool)$data['is_active'];
        }

        if (empty($sets)) return false;

        $sets[]       = 'updated_at = CURRENT_TIMESTAMP';
        $params[':id'] = $id;

        $stmt = $this->db->prepare('UPDATE theme_colors SET ' . implode(', ', $sets) . ' WHERE id = :id');
        return $stmt->execute($params);
    }

    // ── Delete ─────────────────────────────────────────────────
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM theme_colors WHERE id = ?');
        return $stmt->execute([$id]);
    }
}
