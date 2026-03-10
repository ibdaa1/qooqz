<?php
/**
 * TORO — v1/modules/Carts/Repositories/PdoCartsRepository.php
 */
declare(strict_types=1);

final class PdoCartsRepository implements CartsRepositoryInterface
{
    public function __construct(private readonly \PDO $pdo) {}

    // ── Cart CRUD ──────────────────────────────────────────────
    public function findCartById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM carts WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findCartByUser(int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM carts WHERE user_id = :user_id ORDER BY id DESC LIMIT 1');
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findCartBySession(string $sessionKey): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM carts WHERE session_key = :session_key LIMIT 1');
        $stmt->execute([':session_key' => $sessionKey]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function createCart(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO carts (user_id, session_key, coupon_id)
            VALUES (:user_id, :session_key, :coupon_id)
        ");
        $stmt->execute([
            ':user_id'     => $data['user_id']     ?? null,
            ':session_key' => $data['session_key'] ?? null,
            ':coupon_id'   => $data['coupon_id']   ?? null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateCart(int $id, array $data): bool
    {
        $allowed = ['user_id', 'session_key', 'coupon_id'];
        $sets    = [];
        $params  = [':id' => $id];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[]           = "{$col} = :{$col}";
                $params[":{$col}"] = $data[$col];
            }
        }

        if (!$sets) return false;

        $stmt = $this->pdo->prepare('UPDATE carts SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function deleteCart(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM carts WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    // ── Items ──────────────────────────────────────────────────
    public function getItems(int $cartId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ci.*
            FROM cart_items ci
            WHERE ci.cart_id = :cart_id
            ORDER BY ci.added_at ASC
        ");
        $stmt->execute([':cart_id' => $cartId]);
        return $stmt->fetchAll();
    }

    public function findItem(int $cartId, int $productId, ?int $variantId): ?array
    {
        if ($variantId !== null) {
            $stmt = $this->pdo->prepare("
                SELECT * FROM cart_items
                WHERE cart_id = :cart_id AND product_id = :product_id AND variant_id = :variant_id
                LIMIT 1
            ");
            $stmt->bindValue(':cart_id',    $cartId,    \PDO::PARAM_INT);
            $stmt->bindValue(':product_id', $productId, \PDO::PARAM_INT);
            $stmt->bindValue(':variant_id', $variantId, \PDO::PARAM_INT);
        } else {
            $stmt = $this->pdo->prepare("
                SELECT * FROM cart_items
                WHERE cart_id = :cart_id AND product_id = :product_id AND variant_id IS NULL
                LIMIT 1
            ");
            $stmt->bindValue(':cart_id',    $cartId,    \PDO::PARAM_INT);
            $stmt->bindValue(':product_id', $productId, \PDO::PARAM_INT);
        }
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function addItem(int $cartId, array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO cart_items (cart_id, product_id, variant_id, qty, unit_price)
            VALUES (:cart_id, :product_id, :variant_id, :qty, :unit_price)
            ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty)
        ");
        $stmt->execute([
            ':cart_id'    => $cartId,
            ':product_id' => $data['product_id'],
            ':variant_id' => $data['variant_id'] ?? null,
            ':qty'        => $data['qty']        ?? 1,
            ':unit_price' => $data['unit_price'],
        ]);
        $lastId = (int)$this->pdo->lastInsertId();
        if ($lastId > 0) return $lastId;

        $existing = $this->findItem($cartId, (int)$data['product_id'], $data['variant_id'] ?? null);
        return $existing ? (int)$existing['id'] : 0;
    }

    public function updateItem(int $itemId, array $data): bool
    {
        $allowed = ['qty', 'unit_price'];
        $sets    = [];
        $params  = [':id' => $itemId];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[]           = "{$col} = :{$col}";
                $params[":{$col}"] = $data[$col];
            }
        }

        if (!$sets) return false;

        $stmt = $this->pdo->prepare('UPDATE cart_items SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function removeItem(int $itemId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM cart_items WHERE id = :id');
        $stmt->execute([':id' => $itemId]);
        return $stmt->rowCount() > 0;
    }

    public function clearItems(int $cartId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM cart_items WHERE cart_id = :cart_id');
        $stmt->execute([':cart_id' => $cartId]);
        return true;
    }
}
