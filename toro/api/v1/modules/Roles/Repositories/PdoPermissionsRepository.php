<?php
/**
 * TORO — v1/modules/Roles/Repositories/PdoPermissionsRepository.php
 */
declare(strict_types=1);

final class PdoPermissionsRepository implements PermissionsRepositoryInterface
{
    public function __construct(private readonly PDO $pdo) {}

    public function findAll(array $filters = []): array
    {
        $group  = $filters['group'] ?? null;
        $search = $filters['search'] ?? null;
        $limit  = max(1, min((int)($filters['limit'] ?? 100), 200));
        $offset = max(0, (int)($filters['offset'] ?? 0));

        $sql = "SELECT * FROM permissions WHERE 1=1";
        $params = [];

        if ($group) {
            $sql .= " AND `group` = :group";
            $params[':group'] = $group;
        }
        if ($search) {
            $sql .= " AND (name LIKE :search OR slug LIKE :search)";
            $params[':search'] = "%{$search}%";
        }

        $sql .= " ORDER BY `group` ASC, id ASC LIMIT :limit OFFSET :offset";

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
        $group  = $filters['group'] ?? null;
        $search = $filters['search'] ?? null;

        $sql = "SELECT COUNT(*) FROM permissions WHERE 1=1";
        $params = [];

        if ($group) {
            $sql .= " AND `group` = :group";
            $params[':group'] = $group;
        }
        if ($search) {
            $sql .= " AND (name LIKE :search OR slug LIKE :search)";
            $params[':search'] = "%{$search}%";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM permissions WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM permissions WHERE slug = :slug LIMIT 1");
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByGroup(?string $group): array
    {
        if ($group === null) {
            return $this->findAll(); // أو يمكن إرجاع فارغ
        }
        $stmt = $this->pdo->prepare("SELECT * FROM permissions WHERE `group` = :group ORDER BY id ASC");
        $stmt->execute([':group' => $group]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO permissions (name, slug, `group`)
            VALUES (:name, :slug, :group)
        ");
        $stmt->execute([
            ':name'  => $data['name'],
            ':slug'  => $data['slug'],
            ':group' => $data['group'] ?? null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        if (empty($data)) return false;

        $allowed = ['name', 'slug', 'group'];
        $sets = [];
        $params = [':__id' => $id];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[] = "`{$col}` = :{$col}";
                $params[":{$col}"] = $data[$col];
            }
        }

        $sql = "UPDATE permissions SET " . implode(', ', $sets) . " WHERE id = :__id";
        return $this->pdo->prepare($sql)->execute($params);
    }

    public function delete(int $id): bool
    {
        return $this->pdo->prepare("DELETE FROM permissions WHERE id = :id")->execute([':id' => $id]);
    }

    public function getAllGrouped(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM permissions ORDER BY `group` ASC, id ASC");
        $perms = $stmt->fetchAll();

        $grouped = [];
        foreach ($perms as $perm) {
            $group = $perm['group'] ?? 'other';
            if (!isset($grouped[$group])) {
                $grouped[$group] = [];
            }
            $grouped[$group][] = $perm;
        }
        return $grouped;
    }
}