<?php
/**
 * TORO — v1/modules/ProductReviews/Repositories/PdoProductReviewsRepository.php
 */
declare(strict_types=1);

final class PdoProductReviewsRepository implements ProductReviewsRepositoryInterface
{
    public function __construct(private readonly \PDO $pdo) {}

    // ── List for a product ─────────────────────────────────────
    public function findByProduct(int $productId, array $filters = []): array
    {
        $approved = isset($filters['approved']) ? (int)(bool)$filters['approved'] : null;
        $limit    = (int)($filters['limit']  ?? 20);
        $offset   = (int)($filters['offset'] ?? 0);

        $where = ['r.product_id = :product_id'];
        $params = [':product_id' => $productId];

        if (!is_null($approved)) {
            $where[] = 'r.is_approved = :approved';
            $params[':approved'] = $approved;
        }

        $sql = "
            SELECT
                r.id, r.product_id, r.user_id, r.rating,
                r.title, r.body, r.is_approved, r.created_at,
                u.name AS user_name
            FROM product_reviews r
            LEFT JOIN users u ON u.id = r.user_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY r.created_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ── Count for a product ────────────────────────────────────
    public function countByProduct(int $productId, array $filters = []): int
    {
        $approved = isset($filters['approved']) ? (int)(bool)$filters['approved'] : null;
        $where    = ['r.product_id = :product_id'];
        $params   = [':product_id' => $productId];

        if (!is_null($approved)) {
            $where[] = 'r.is_approved = :approved';
            $params[':approved'] = $approved;
        }

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM product_reviews r WHERE ' . implode(' AND ', $where)
        );
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    // ── Single by ID ───────────────────────────────────────────
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                r.id, r.product_id, r.user_id, r.rating,
                r.title, r.body, r.is_approved, r.created_at,
                u.name AS user_name
            FROM product_reviews r
            LEFT JOIN users u ON u.id = r.user_id
            WHERE r.id = :id LIMIT 1
        ");
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ── Create ─────────────────────────────────────────────────
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO product_reviews (product_id, user_id, rating, title, body)
            VALUES (:product_id, :user_id, :rating, :title, :body)
        ");
        $stmt->execute([
            ':product_id' => $data['product_id'],
            ':user_id'    => $data['user_id'],
            ':rating'     => $data['rating'],
            ':title'      => $data['title'] ?? null,
            ':body'       => $data['body']  ?? null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    // ── Update ─────────────────────────────────────────────────
    public function update(int $id, array $data): bool
    {
        if (empty($data)) return false;

        $allowed = ['rating', 'title', 'body', 'is_approved'];
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
            'UPDATE product_reviews SET ' . implode(', ', $sets) . ' WHERE id = :__id'
        )->execute($params);
    }

    // ── Approve ────────────────────────────────────────────────
    public function approve(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE product_reviews SET is_approved = 1 WHERE id = :id'
        );
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    // ── Delete ─────────────────────────────────────────────────
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM product_reviews WHERE id = :id');
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }
}
