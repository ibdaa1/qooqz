<?php
/**
 * TORO — v1/modules/Orders/Repositories/PdoOrdersRepository.php
 */
declare(strict_types=1);

final class PdoOrdersRepository implements OrdersRepositoryInterface
{
    public function __construct(private readonly \PDO $pdo) {}

    // ── List ───────────────────────────────────────────────────
    public function findAll(array $filters = []): array
    {
        $status = $filters['status'] ?? null;
        $userId = $filters['user_id'] ?? null;
        $limit  = max(1, min((int)($filters['limit'] ?? 50), 200));
        $offset = max(0, (int)($filters['offset'] ?? 0));

        $sql    = 'SELECT * FROM orders WHERE 1=1';
        $params = [];

        if ($status !== null) {
            $sql .= ' AND status = :status';
            $params[':status'] = $status;
        }
        if ($userId !== null) {
            $sql .= ' AND user_id = :user_id';
            $params[':user_id'] = (int)$userId;
        }

        $sql .= ' ORDER BY id DESC LIMIT :limit OFFSET :offset';

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
        $status = $filters['status']  ?? null;
        $userId = $filters['user_id'] ?? null;
        $sql    = 'SELECT COUNT(*) FROM orders WHERE 1=1';
        $params = [];

        if ($status !== null) {
            $sql .= ' AND status = :status';
            $params[':status'] = $status;
        }
        if ($userId !== null) {
            $sql .= ' AND user_id = :user_id';
            $params[':user_id'] = (int)$userId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    // ── Find ───────────────────────────────────────────────────
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM orders WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByNumber(string $orderNumber): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM orders WHERE order_number = :number LIMIT 1');
        $stmt->execute([':number' => $orderNumber]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // ── Create ─────────────────────────────────────────────────
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO orders
                (order_number, user_id, address_id, status, subtotal, discount, shipping_cost, tax, total,
                 currency, coupon_id, notes, ip_address, user_agent, language_id)
            VALUES
                (:order_number, :user_id, :address_id, :status, :subtotal, :discount, :shipping_cost, :tax, :total,
                 :currency, :coupon_id, :notes, :ip_address, :user_agent, :language_id)
        ");
        $stmt->execute([
            ':order_number'  => $data['order_number'],
            ':user_id'       => $data['user_id']       ?? null,
            ':address_id'    => $data['address_id']    ?? null,
            ':status'        => $data['status']        ?? 'pending',
            ':subtotal'      => $data['subtotal']      ?? 0,
            ':discount'      => $data['discount']      ?? 0,
            ':shipping_cost' => $data['shipping_cost'] ?? 0,
            ':tax'           => $data['tax']           ?? 0,
            ':total'         => $data['total'],
            ':currency'      => $data['currency']      ?? 'SAR',
            ':coupon_id'     => $data['coupon_id']     ?? null,
            ':notes'         => $data['notes']         ?? null,
            ':ip_address'    => $data['ip_address']    ?? null,
            ':user_agent'    => $data['user_agent']    ?? null,
            ':language_id'   => $data['language_id']   ?? null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    // ── Update status ──────────────────────────────────────────
    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->pdo->prepare('UPDATE orders SET status = :status WHERE id = :id');
        $stmt->execute([':status' => $status, ':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    // ── Update general ─────────────────────────────────────────
    public function update(int $id, array $data): bool
    {
        $allowed = ['status', 'address_id', 'coupon_id', 'notes', 'discount', 'shipping_cost', 'tax', 'total'];
        $sets    = [];
        $params  = [':id' => $id];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[]           = "{$col} = :{$col}";
                $params[":{$col}"] = $data[$col];
            }
        }

        if (!$sets) return false;

        $stmt = $this->pdo->prepare('UPDATE orders SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    // ── Order Items ────────────────────────────────────────────
    public function getItems(int $orderId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM order_items WHERE order_id = :order_id ORDER BY id ASC');
        $stmt->execute([':order_id' => $orderId]);
        return $stmt->fetchAll();
    }

    public function addItem(int $orderId, array $data): int
    {
        $qty       = max(1, (int)($data['qty'] ?? 1));
        $unitPrice = (float)($data['unit_price'] ?? 0);
        $discount  = (float)($data['discount']   ?? 0);

        $stmt = $this->pdo->prepare("
            INSERT INTO order_items
                (order_id, product_id, variant_id, product_name, sku, qty, unit_price, discount, total)
            VALUES
                (:order_id, :product_id, :variant_id, :product_name, :sku, :qty, :unit_price, :discount, :total)
        ");
        $stmt->execute([
            ':order_id'    => $orderId,
            ':product_id'  => $data['product_id'],
            ':variant_id'  => $data['variant_id']   ?? null,
            ':product_name'=> $data['product_name'],
            ':sku'         => $data['sku'],
            ':qty'         => $qty,
            ':unit_price'  => $unitPrice,
            ':discount'    => $discount,
            ':total'       => ($unitPrice * $qty) - $discount,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    // ── Status History ─────────────────────────────────────────
    public function getStatusHistory(int $orderId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM order_status_history WHERE order_id = :order_id ORDER BY id ASC');
        $stmt->execute([':order_id' => $orderId]);
        return $stmt->fetchAll();
    }

    public function addStatusHistory(int $orderId, string $status, ?string $note, ?int $createdBy): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO order_status_history (order_id, status, note, created_by)
            VALUES (:order_id, :status, :note, :created_by)
        ");
        $stmt->execute([
            ':order_id'   => $orderId,
            ':status'     => $status,
            ':note'       => $note,
            ':created_by' => $createdBy,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    // ── Helpers ────────────────────────────────────────────────
    public function generateOrderNumber(): string
    {
        return 'ORD-' . strtoupper(substr(uniqid('', true), -8)) . '-' . date('Ymd');
    }
}
