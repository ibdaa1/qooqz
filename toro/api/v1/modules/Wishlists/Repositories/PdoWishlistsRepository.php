<?php
/**
 * TORO — v1/modules/Wishlists/Repositories/PdoWishlistsRepository.php
 */
declare(strict_types=1);

final class PdoWishlistsRepository implements WishlistsRepositoryInterface
{
    public function __construct(private readonly \PDO $pdo) {}

    // ── All wishlist items for a user ──────────────────────────
    public function findByUser(int $userId, array $filters = []): array
    {
        $limit  = (int)($filters['limit']  ?? 20);
        $offset = (int)($filters['offset'] ?? 0);
        $lang   = $filters['lang'] ?? null;

        $langSub = $lang
            ? "(SELECT id FROM languages WHERE code = " . $this->pdo->quote($lang) . " LIMIT 1)"
            : "(SELECT id FROM languages WHERE is_default = 1 LIMIT 1)";

        $stmt = $this->pdo->prepare("
            SELECT
                w.id, w.user_id, w.product_id, w.created_at,
                p.sku, p.base_price, p.sale_price, p.is_active,
                pt.name AS product_name
            FROM wishlists w
            JOIN products p ON p.id = w.product_id AND p.deleted_at IS NULL
            LEFT JOIN product_translations pt
                ON pt.product_id = p.id
                AND pt.language_id = {$langSub}
            WHERE w.user_id = :user_id
            ORDER BY w.created_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ");
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ── Count ──────────────────────────────────────────────────
    public function countByUser(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM wishlists WHERE user_id = :user_id'
        );
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    // ── Exists ─────────────────────────────────────────────────
    public function exists(int $userId, int $productId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM wishlists WHERE user_id = :uid AND product_id = :pid LIMIT 1'
        );
        $stmt->bindValue(':uid', $userId,    \PDO::PARAM_INT);
        $stmt->bindValue(':pid', $productId, \PDO::PARAM_INT);
        $stmt->execute();
        return (bool)$stmt->fetchColumn();
    }

    // ── Add ────────────────────────────────────────────────────
    public function add(int $userId, int $productId): int
    {
        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO wishlists (user_id, product_id)
            VALUES (:uid, :pid)
        ");
        $stmt->bindValue(':uid', $userId,    \PDO::PARAM_INT);
        $stmt->bindValue(':pid', $productId, \PDO::PARAM_INT);
        $stmt->execute();
        return (int)$this->pdo->lastInsertId();
    }

    // ── Remove ─────────────────────────────────────────────────
    public function remove(int $userId, int $productId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM wishlists WHERE user_id = :uid AND product_id = :pid'
        );
        $stmt->bindValue(':uid', $userId,    \PDO::PARAM_INT);
        $stmt->bindValue(':pid', $productId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    // ── Clear all for a user ───────────────────────────────────
    public function clear(int $userId): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM wishlists WHERE user_id = :uid');
        $stmt->bindValue(':uid', $userId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }
}
