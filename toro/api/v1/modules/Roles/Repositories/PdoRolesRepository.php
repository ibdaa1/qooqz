<?php
/**
 * TORO — v1/modules/Roles/Repositories/PdoRolesRepository.php
 */
declare(strict_types=1);

final class PdoRolesRepository implements RolesRepositoryInterface
{
    public function __construct(private readonly PDO $pdo) {}

    // ── List ───────────────────────────────────────────────────
    public function findAll(array $filters = []): array
    {
        $search = $filters['search'] ?? null;
        $limit  = max(1, min((int)($filters['limit'] ?? 50), 200));
        $offset = max(0, (int)($filters['offset'] ?? 0));

        $sql = "SELECT * FROM roles WHERE 1=1";
        $params = [];

        if ($search !== null) {
            $sql .= " AND (name LIKE :search OR description LIKE :search)";
            $params[':search'] = "%{$search}%";
        }

        $sql .= " ORDER BY id ASC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countAll(array $filters = []): int
    {
        $search = $filters['search'] ?? null;

        $sql = "SELECT COUNT(*) FROM roles WHERE 1=1";
        $params = [];

        if ($search !== null) {
            $sql .= " AND (name LIKE :search OR description LIKE :search)";
            $params[':search'] = "%{$search}%";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    // ── Single by ID ───────────────────────────────────────────
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM roles WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // ── Single by Slug ─────────────────────────────────────────
    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM roles WHERE slug = :slug LIMIT 1");
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // ── Create ─────────────────────────────────────────────────
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO roles (name, slug, description)
            VALUES (:name, :slug, :description)
        ");
        $stmt->execute([
            ':name'        => $data['name'],
            ':slug'        => $data['slug'],
            ':description' => $data['description'] ?? null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    // ── Update ─────────────────────────────────────────────────
    public function update(int $id, array $data): bool
    {
        if (empty($data)) return false;

        $allowed = ['name', 'slug', 'description'];
        $sets    = [];
        $params  = [':__id' => $id];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[]          = "`{$col}` = :{$col}";
                $params[":{$col}"] = $data[$col];
            }
        }

        if (empty($sets)) return false;

        $sql = 'UPDATE roles SET ' . implode(', ', $sets) . ' WHERE id = :__id';
        return $this->pdo->prepare($sql)->execute($params);
    }

    // ── Delete ─────────────────────────────────────────────────
    public function delete(int $id): bool
    {
        return $this->pdo->prepare('DELETE FROM roles WHERE id = :id')
            ->execute([':id' => $id]);
    }
}