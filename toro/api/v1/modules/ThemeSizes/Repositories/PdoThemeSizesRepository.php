<?php
/**
 * TORO — v1/modules/ThemeSizes/Repositories/PdoThemeSizesRepository.php
 */
declare(strict_types=1);

final class PdoThemeSizesRepository implements ThemeSizesRepositoryInterface
{
    public function __construct(private readonly \PDO $pdo) {}

    public function findAll(array $filters = []): array
    {
        $isActive = $filters['is_active'] ?? null;
        $search   = $filters['search']    ?? null;
        $limit    = max(1, min((int)($filters['limit']  ?? 50), 200));
        $offset   = max(0, (int)($filters['offset'] ?? 0));

        $sql    = 'SELECT * FROM theme_sizes WHERE 1=1';
        $params = [];

        if ($isActive !== null) {
            $sql .= ' AND is_active = :is_active';
            $params[':is_active'] = (int)$isActive;
        }
        if ($search !== null) {
            $sql .= ' AND name LIKE :search1';
            $params[':search1'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY sort_order ASC, id ASC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, is_int($val) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit',  $limit,  \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countAll(array $filters = []): int
    {
        $isActive = $filters['is_active'] ?? null;
        $search   = $filters['search']    ?? null;
        $sql      = 'SELECT COUNT(*) FROM theme_sizes WHERE 1=1';
        $params   = [];

        if ($isActive !== null) {
            $sql .= ' AND is_active = :is_active';
            $params[':is_active'] = (int)$isActive;
        }
        if ($search !== null) {
            $sql .= ' AND name LIKE :search1';
            $params[':search1'] = '%' . $search . '%';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM theme_sizes WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByName(string $name): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM theme_sizes WHERE name = :name LIMIT 1');
        $stmt->execute([':name' => $name]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO theme_sizes (name, sort_order, is_active) VALUES (:name, :sort_order, :is_active)'
        );
        $stmt->execute([
            ':name'       => $data['name'],
            ':sort_order' => (int)($data['sort_order'] ?? 0),
            ':is_active'  => (int)($data['is_active']  ?? 1),
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $sets   = [];
        $params = [':id' => $id];

        if (array_key_exists('name', $data)) {
            $sets[] = 'name = :name';
            $params[':name'] = $data['name'];
        }
        if (array_key_exists('sort_order', $data)) {
            $sets[] = 'sort_order = :sort_order';
            $params[':sort_order'] = (int)$data['sort_order'];
        }
        if (array_key_exists('is_active', $data)) {
            $sets[] = 'is_active = :is_active';
            $params[':is_active'] = (int)$data['is_active'];
        }

        if (empty($sets)) return false;

        $stmt = $this->pdo->prepare('UPDATE theme_sizes SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM theme_sizes WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
