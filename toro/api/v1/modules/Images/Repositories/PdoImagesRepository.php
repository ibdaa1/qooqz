<?php
/**
 * TORO — v1/modules/Images/Repositories/PdoImagesRepository.php
 * جدول الصور الموحد — يعمل مع كل الكيانات عبر owner_id
 */
declare(strict_types=1);

final class PdoImagesRepository implements ImagesRepositoryInterface
{
    public function __construct(private readonly \PDO $pdo) {}

    // ── List ───────────────────────────────────────────────────
    public function findAll(array $filters = []): array
    {
        $ownerId     = $filters['owner_id']     ?? null;
        $imageTypeId = $filters['image_type_id'] ?? null;
        $visibility  = $filters['visibility']   ?? null;
        $userId      = $filters['user_id']       ?? null;
        $limit       = max(1, min((int)($filters['limit'] ?? 50), 200));
        $offset      = max(0, (int)($filters['offset'] ?? 0));

        $sql = "
            SELECT i.*, it.code AS image_type_code, it.name AS image_type_name
            FROM images i
            LEFT JOIN image_types it ON it.id = i.image_type_id
            WHERE 1=1
        ";
        $params = [];

        if ($ownerId !== null) {
            $sql .= ' AND i.owner_id = :owner_id';
            $params[':owner_id'] = (int)$ownerId;
        }

        if ($imageTypeId !== null) {
            $sql .= ' AND i.image_type_id = :image_type_id';
            $params[':image_type_id'] = (int)$imageTypeId;
        }

        if ($visibility !== null) {
            $sql .= ' AND i.visibility = :visibility';
            $params[':visibility'] = $visibility;
        }

        if ($userId !== null) {
            $sql .= ' AND i.user_id = :user_id';
            $params[':user_id'] = (int)$userId;
        }

        $sql .= ' ORDER BY i.sort_order ASC, i.id ASC LIMIT :limit OFFSET :offset';

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
        $ownerId     = $filters['owner_id']      ?? null;
        $imageTypeId = $filters['image_type_id'] ?? null;
        $visibility  = $filters['visibility']    ?? null;

        $sql    = 'SELECT COUNT(*) FROM images WHERE 1=1';
        $params = [];

        if ($ownerId !== null) {
            $sql .= ' AND owner_id = :owner_id';
            $params[':owner_id'] = (int)$ownerId;
        }

        if ($imageTypeId !== null) {
            $sql .= ' AND image_type_id = :image_type_id';
            $params[':image_type_id'] = (int)$imageTypeId;
        }

        if ($visibility !== null) {
            $sql .= ' AND visibility = :visibility';
            $params[':visibility'] = $visibility;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    // ── Single ─────────────────────────────────────────────────
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT i.*, it.code AS image_type_code, it.name AS image_type_name
            FROM images i
            LEFT JOIN image_types it ON it.id = i.image_type_id
            WHERE i.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    // ── By Owner ───────────────────────────────────────────────
    public function findByOwner(int $ownerId, ?int $imageTypeId = null): array
    {
        $sql    = "SELECT i.*, it.code AS image_type_code FROM images i LEFT JOIN image_types it ON it.id = i.image_type_id WHERE i.owner_id = :owner_id";
        $params = [':owner_id' => $ownerId];

        if ($imageTypeId !== null) {
            $sql .= ' AND i.image_type_id = :image_type_id';
            $params[':image_type_id'] = $imageTypeId;
        }

        $sql .= ' ORDER BY i.sort_order ASC, i.id ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // ── Main Image ─────────────────────────────────────────────
    public function findMainByOwner(int $ownerId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT i.*, it.code AS image_type_code
            FROM images i
            LEFT JOIN image_types it ON it.id = i.image_type_id
            WHERE i.owner_id = :owner_id AND i.is_main = 1
            LIMIT 1
        ");
        $stmt->execute([':owner_id' => $ownerId]);
        return $stmt->fetch() ?: null;
    }

    // ── Create ─────────────────────────────────────────────────
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO images
                (owner_id, image_type_id, user_id, filename, url, thumb_url, mime_type, size, visibility, is_main, sort_order)
            VALUES
                (:owner_id, :image_type_id, :user_id, :filename, :url, :thumb_url, :mime_type, :size, :visibility, :is_main, :sort_order)
        ");
        $stmt->execute([
            ':owner_id'     => $data['owner_id']     ?? null,
            ':image_type_id'=> $data['image_type_id'] ?? null,
            ':user_id'      => $data['user_id']       ?? null,
            ':filename'     => $data['filename']      ?? null,
            ':url'          => $data['url']           ?? null,
            ':thumb_url'    => $data['thumb_url']     ?? null,
            ':mime_type'    => $data['mime_type']     ?? null,
            ':size'         => $data['size']          ?? null,
            ':visibility'   => $data['visibility']    ?? 'public',
            ':is_main'      => (int)($data['is_main'] ?? 0),
            ':sort_order'   => $data['sort_order']    ?? 0,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    // ── Update ─────────────────────────────────────────────────
    public function update(int $id, array $data): bool
    {
        if (empty($data)) return false;

        $allowed = ['owner_id', 'image_type_id', 'filename', 'url', 'thumb_url', 'mime_type', 'size', 'visibility', 'is_main', 'sort_order'];
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
            'UPDATE images SET ' . implode(', ', $sets) . ' WHERE id = :__id'
        )->execute($params);
    }

    // ── Delete ─────────────────────────────────────────────────
    public function delete(int $id): bool
    {
        return $this->pdo->prepare('DELETE FROM images WHERE id = :id')
            ->execute([':id' => $id]);
    }

    public function deleteByOwner(int $ownerId, ?int $imageTypeId = null): bool
    {
        if ($imageTypeId !== null) {
            return $this->pdo->prepare('DELETE FROM images WHERE owner_id = :owner_id AND image_type_id = :itid')
                ->execute([':owner_id' => $ownerId, ':itid' => $imageTypeId]);
        }
        return $this->pdo->prepare('DELETE FROM images WHERE owner_id = :owner_id')
            ->execute([':owner_id' => $ownerId]);
    }

    // ── Set Main ───────────────────────────────────────────────
    public function setMain(int $id, int $ownerId): bool
    {
        $this->pdo->prepare('UPDATE images SET is_main = 0 WHERE owner_id = :owner_id')
            ->execute([':owner_id' => $ownerId]);

        return $this->pdo->prepare('UPDATE images SET is_main = 1 WHERE id = :id')
            ->execute([':id' => $id]);
    }
}
