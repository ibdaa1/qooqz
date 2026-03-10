<?php
/**
 * TORO — v1/modules/Payments/Repositories/PdoPaymentsRepository.php
 */
declare(strict_types=1);

final class PdoPaymentsRepository implements PaymentsRepositoryInterface
{
    public function __construct(private readonly \PDO $pdo) {}

    // ── List payments ──────────────────────────────────────────
    public function findAll(array $filters = []): array
    {
        $orderId = $filters['order_id'] ?? null;
        $status  = $filters['status']   ?? null;
        $limit   = max(1, min((int)($filters['limit']  ?? 50), 200));
        $offset  = max(0, (int)($filters['offset'] ?? 0));

        $sql    = 'SELECT * FROM payments WHERE 1=1';
        $params = [];

        if ($orderId !== null) {
            $sql .= ' AND order_id = :order_id';
            $params[':order_id'] = (int)$orderId;
        }
        if ($status !== null) {
            $sql .= ' AND status = :status';
            $params[':status'] = $status;
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

    public function countAll(array $filters = []): int
    {
        $orderId = $filters['order_id'] ?? null;
        $status  = $filters['status']   ?? null;

        $sql    = 'SELECT COUNT(*) FROM payments WHERE 1=1';
        $params = [];

        if ($orderId !== null) {
            $sql .= ' AND order_id = :order_id';
            $params[':order_id'] = (int)$orderId;
        }
        if ($status !== null) {
            $sql .= ' AND status = :status';
            $params[':status'] = $status;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM payments WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByOrderId(int $orderId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM payments WHERE order_id = :order_id ORDER BY id DESC');
        $stmt->execute([':order_id' => $orderId]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO payments
             (order_id, method, status, amount, currency, gateway, gateway_txn_id, gateway_ref, gateway_resp)
             VALUES
             (:order_id, :method, :status, :amount, :currency, :gateway, :gateway_txn_id, :gateway_ref, :gateway_resp)'
        );
        $stmt->execute([
            ':order_id'       => $data['order_id'],
            ':method'         => $data['method'],
            ':status'         => $data['status'] ?? 'pending',
            ':amount'         => $data['amount'],
            ':currency'       => $data['currency'] ?? 'SAR',
            ':gateway'        => $data['gateway'] ?? null,
            ':gateway_txn_id' => $data['gateway_txn_id'] ?? null,
            ':gateway_ref'    => $data['gateway_ref'] ?? null,
            ':gateway_resp'   => isset($data['gateway_resp']) ? json_encode($data['gateway_resp']) : null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateStatus(int $id, string $status, ?string $paidAt): bool
    {
        $sql = 'UPDATE payments SET status = :status';
        $params = [':status' => $status, ':id' => $id];

        if ($paidAt !== null) {
            $sql .= ', paid_at = :paid_at';
            $params[':paid_at'] = $paidAt;
        }

        $sql .= ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    // ── Refunds ────────────────────────────────────────────────
    public function getRefunds(int $paymentId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM refunds WHERE payment_id = :payment_id ORDER BY id DESC');
        $stmt->execute([':payment_id' => $paymentId]);
        return $stmt->fetchAll();
    }

    public function createRefund(int $paymentId, array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO refunds (payment_id, amount, reason, status)
             VALUES (:payment_id, :amount, :reason, :status)'
        );
        $stmt->execute([
            ':payment_id' => $paymentId,
            ':amount'     => $data['amount'],
            ':reason'     => $data['reason'] ?? null,
            ':status'     => 'pending',
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateRefund(int $refundId, string $status, ?int $processedBy): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE refunds SET status = :status, processed_by = :processed_by WHERE id = :id'
        );
        $stmt->execute([
            ':status'       => $status,
            ':processed_by' => $processedBy,
            ':id'           => $refundId,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function findRefundById(int $refundId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM refunds WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $refundId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
