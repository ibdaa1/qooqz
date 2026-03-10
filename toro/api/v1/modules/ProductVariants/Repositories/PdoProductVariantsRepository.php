<?php
/**
 * TORO — v1/modules/ProductVariants/Repositories/PdoProductVariantsRepository.php
 */
declare(strict_types=1);

final class PdoProductVariantsRepository implements ProductVariantsRepositoryInterface
{
    public function __construct(private readonly \PDO $pdo) {}

    // ── All variants for a product ─────────────────────────────
    public function findByProduct(int $productId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                pv.id, pv.product_id, pv.size_id, pv.sku,
                pv.price, pv.sale_price, pv.stock_qty, pv.is_active, pv.created_at,
                ts.label AS size_label, ts.value AS size_value
            FROM product_variants pv
            LEFT JOIN theme_sizes ts ON ts.id = pv.size_id
            WHERE pv.product_id = :product_id
            ORDER BY pv.id
        ");
        $stmt->bindValue(':product_id', $productId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ── Single by ID ───────────────────────────────────────────
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                pv.id, pv.product_id, pv.size_id, pv.sku,
                pv.price, pv.sale_price, pv.stock_qty, pv.is_active, pv.created_at,
                ts.label AS size_label, ts.value AS size_value
            FROM product_variants pv
            LEFT JOIN theme_sizes ts ON ts.id = pv.size_id
            WHERE pv.id = :id
            LIMIT 1
        ");
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ── Single by SKU ──────────────────────────────────────────
    public function findBySku(string $sku): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                pv.id, pv.product_id, pv.size_id, pv.sku,
                pv.price, pv.sale_price, pv.stock_qty, pv.is_active, pv.created_at
            FROM product_variants pv
            WHERE pv.sku = :sku
            LIMIT 1
        ");
        $stmt->bindValue(':sku', $sku);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ── Create ─────────────────────────────────────────────────
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO product_variants
                (product_id, size_id, sku, price, sale_price, stock_qty, is_active)
            VALUES
                (:product_id, :size_id, :sku, :price, :sale_price, :stock_qty, :is_active)
        ");
        $stmt->execute([
            ':product_id' => $data['product_id'],
            ':size_id'    => $data['size_id'],
            ':sku'        => $data['sku'],
            ':price'      => $data['price'],
            ':sale_price' => $data['sale_price'] ?? null,
            ':stock_qty'  => $data['stock_qty']  ?? 0,
            ':is_active'  => (int)($data['is_active'] ?? 1),
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    // ── Update ─────────────────────────────────────────────────
    public function update(int $id, array $data): bool
    {
        if (empty($data)) return false;

        $allowed = ['size_id', 'sku', 'price', 'sale_price', 'stock_qty', 'is_active'];
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
            'UPDATE product_variants SET ' . implode(', ', $sets) . ' WHERE id = :__id'
        )->execute($params);
    }

    // ── Delete ─────────────────────────────────────────────────
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM product_variants WHERE id = :id');
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }
}
