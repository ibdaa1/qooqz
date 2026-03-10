<?php
/**
 * TORO — v1/modules/Menus/Repositories/PdoMenusRepository.php
 */
declare(strict_types=1);

final class PdoMenusRepository implements MenusRepositoryInterface
{
    public function __construct(private readonly \PDO $pdo) {}

    public function findAll(): array
    {
        return $this->pdo->query("
            SELECT id, slug, is_active, created_at FROM menus ORDER BY id
        ")->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, slug, is_active, created_at FROM menus WHERE id = :id LIMIT 1'
        );
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, slug, is_active, created_at FROM menus WHERE slug = :slug LIMIT 1'
        );
        $stmt->bindValue(':slug', $slug);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO menus (slug, is_active) VALUES (:slug, :is_active)'
        );
        $stmt->execute([
            ':slug'      => $data['slug'],
            ':is_active' => (int)($data['is_active'] ?? 1),
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $allowed = ['slug', 'is_active'];
        $sets    = [];
        $params  = [':__id' => $id];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[]            = "`{$col}` = :{$col}";
                $params[":{$col}"] = $data[$col];
            }
        }
        if (empty($sets)) return false;
        return $this->pdo->prepare(
            'UPDATE menus SET ' . implode(', ', $sets) . ' WHERE id = :__id'
        )->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM menus WHERE id = :id');
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }
}
