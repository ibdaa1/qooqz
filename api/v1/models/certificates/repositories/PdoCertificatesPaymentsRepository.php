<?php
declare(strict_types=1);

final class PdoCertificatesPaymentsRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = [
        'id', 'request_id', 'tenant_id', 'entity_id', 'payment_type', 'payment_reference',
        'payment_date', 'amount', 'currency', 'verification_status',
        'verified_by', 'verified_at', 'created_at', 'updated_at'
    ];

    private const FILTERABLE_COLUMNS = [
        'request_id', 'tenant_id', 'entity_id', 'payment_type', 'verification_status',
        'verified_by', 'currency'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(
        int $tenantId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        $sql = "SELECT * FROM certificates_payments WHERE tenant_id = :tenant_id";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if ($col === 'tenant_id') continue; // already applied
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND {$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        // Date range for payment_date
        if (!empty($filters['payment_date_from'])) {
            $sql .= " AND payment_date >= :payment_date_from";
            $params[':payment_date_from'] = $filters['payment_date_from'];
        }
        if (!empty($filters['payment_date_to'])) {
            $sql .= " AND payment_date <= :payment_date_to";
            $params[':payment_date_to'] = $filters['payment_date_to'];
        }

        $orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY {$orderBy} {$orderDir}";

        if ($limit !== null) {
            $sql .= " LIMIT :limit";
        }
        if ($offset !== null) {
            $sql .= " OFFSET :offset";
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        if ($limit !== null) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        }
        if ($offset !== null) {
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(int $tenantId, array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM certificates_payments WHERE tenant_id = :tenant_id";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if ($col === 'tenant_id') continue;
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND {$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function find(int $tenantId, int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM certificates_payments WHERE tenant_id = :tenant_id AND id = :id");
        $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(int $tenantId, array $data): int
    {
        $isUpdate = !empty($data['id']);

        if ($isUpdate) {
            $id = (int)$data['id'];
            unset($data['id']);

            $sets = [];
            $params = [':id' => $id, ':tenant_id' => $tenantId];
            $allowed = ['request_id', 'entity_id', 'payment_type', 'payment_reference', 'payment_date', 'amount', 'currency', 'verification_status', 'verified_by', 'verified_at', 'receipt_file', 'notes'];
            foreach ($allowed as $col) {
                if (array_key_exists($col, $data)) {
                    $sets[] = "$col = :$col";
                    $params[":$col"] = ($data[$col] === '' ? null : $data[$col]);
                }
            }
            if (empty($sets)) {
                throw new InvalidArgumentException('No fields to update');
            }

            $sql = "UPDATE certificates_payments SET " . implode(', ', $sets) . ", updated_at = CURRENT_TIMESTAMP WHERE id = :id AND tenant_id = :tenant_id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('Payment not found or does not belong to this tenant.');
            }
            return $id;
        }

        // Insert: we require tenant_id and entity_id to match the ones passed
        $data['tenant_id'] = $tenantId; // force tenant_id from URL/session
        // entity_id must be present in $data
        if (!isset($data['entity_id']) || !is_numeric($data['entity_id'])) {
            throw new InvalidArgumentException('Field "entity_id" is required and must be numeric.');
        }

        $cols = [];
        $placeholders = [];
        $params = [];
        $allowed = ['request_id', 'tenant_id', 'entity_id', 'payment_type', 'payment_reference', 'payment_date', 'amount', 'currency', 'verification_status', 'verified_by', 'verified_at', 'receipt_file', 'notes'];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data) && $data[$col] !== null && $data[$col] !== '') {
                $cols[] = $col;
                $placeholders[] = ":$col";
                $params[":$col"] = $data[$col];
            }
        }
        if (empty($cols)) {
            throw new InvalidArgumentException('No data provided for insert');
        }

        $sql = "INSERT INTO certificates_payments (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $tenantId, int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM certificates_payments WHERE tenant_id = :tenant_id AND id = :id");
        $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
        return $stmt->rowCount() > 0;
    }
}