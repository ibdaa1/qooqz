<?php
/**
 * TORO — v1/modules/Images/Repositories/PdoImageTypesRepository.php
 */
declare(strict_types=1);

final class PdoImageTypesRepository implements ImageTypesRepositoryInterface
{
    public function __construct(private readonly \PDO $pdo) {}

    public function findAll(array $filters = []): array
    {
        $isThumbnail = $filters['is_thumbnail'] ?? null;
        $limit       = max(1, min((int)($filters['limit'] ?? 100), 500));
        $offset      = max(0, (int)($filters['offset'] ?? 0));

        $sql    = 'SELECT * FROM image_types WHERE 1=1';
        $params = [];

        if ($isThumbnail !== null) {
            $sql .= ' AND is_thumbnail = :is_thumbnail';
            $params[':is_thumbnail'] = (int)(bool)$isThumbnail;
        }

        $sql .= ' ORDER BY id ASC LIMIT :limit OFFSET :offset';

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
        $isThumbnail = $filters['is_thumbnail'] ?? null;

        $sql    = 'SELECT COUNT(*) FROM image_types WHERE 1=1';
        $params = [];

        if ($isThumbnail !== null) {
            $sql .= ' AND is_thumbnail = :is_thumbnail';
            $params[':is_thumbnail'] = (int)(bool)$isThumbnail;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM image_types WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function findByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM image_types WHERE code = :code LIMIT 1');
        $stmt->execute([':code' => $code]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO image_types
                (code, name, description, width, height, crop, quality, format, is_thumbnail)
            VALUES
                (:code, :name, :description, :width, :height, :crop, :quality, :format, :is_thumbnail)
        ");
        $stmt->execute([
            ':code'         => $data['code'],
            ':name'         => $data['name'],
            ':description'  => $data['description']  ?? null,
            ':width'        => $data['width'],
            ':height'       => $data['height'],
            ':crop'         => $data['crop']         ?? 'cover',
            ':quality'      => $data['quality']      ?? 85,
            ':format'       => $data['format']       ?? 'webp',
            ':is_thumbnail' => (int)($data['is_thumbnail'] ?? 0),
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        if (empty($data)) return false;

        $allowed = ['code', 'name', 'description', 'width', 'height', 'crop', 'quality', 'format', 'is_thumbnail'];
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
            'UPDATE image_types SET ' . implode(', ', $sets) . ' WHERE id = :__id'
        )->execute($params);
    }

    public function delete(int $id): bool
    {
        return $this->pdo->prepare('DELETE FROM image_types WHERE id = :id')
            ->execute([':id' => $id]);
    }
}
