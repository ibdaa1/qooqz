<?php
declare(strict_types=1);

final class PdoCertificateReceiptAllocationsRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = [
        'id', 'receipt_id', 'certificate_id', 'fee_id', 'allocated_amount', 'created_at'
    ];

    private const FILTERABLE_COLUMNS = [
        'receipt_id', 'certificate_id', 'fee_id'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get list of allocations with tenant verification via certificates_versions -> certificates_requests.
     */
    public function all(
        int $tenantId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        $sql = "
            SELECT cra.*
            FROM Certificate_receipt_allocations cra
            INNER JOIN certificates_versions cv ON cra.certificate_id = cv.id
            INNER JOIN certificates_requests cr ON cv.request_id = cr.id
            WHERE cr.tenant_id = :tenant_id
        ";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND cra.{$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        // optional date range on created_at
        if (!empty($filters['date_from'])) {
            $sql .= " AND cra.created_at >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND cra.created_at <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        $orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY cra.{$orderBy} {$orderDir}";

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
        $sql = "
            SELECT COUNT(cra.id)
            FROM Certificate_receipt_allocations cra
            INNER JOIN certificates_versions cv ON cra.certificate_id = cv.id
            INNER JOIN certificates_requests cr ON cv.request_id = cr.id
            WHERE cr.tenant_id = :tenant_id
        ";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND cra.{$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function find(int $tenantId, int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT cra.*
            FROM Certificate_receipt_allocations cra
            INNER JOIN certificates_versions cv ON cra.certificate_id = cv.id
            INNER JOIN certificates_requests cr ON cv.request_id = cr.id
            WHERE cr.tenant_id = :tenant_id AND cra.id = :id
            LIMIT 1
        ");
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
            $allowed = ['receipt_id', 'certificate_id', 'fee_id', 'allocated_amount'];
            foreach ($allowed as $col) {
                if (array_key_exists($col, $data)) {
                    $sets[] = "$col = :$col";
                    $params[":$col"] = ($data[$col] === '' ? null : $data[$col]);
                }
            }
            if (empty($sets)) {
                throw new InvalidArgumentException('No fields to update');
            }

            // Update with tenant verification through certificates_versions -> requests
            $sql = "
                UPDATE Certificate_receipt_allocations cra
                INNER JOIN certificates_versions cv ON cra.certificate_id = cv.id
                INNER JOIN certificates_requests cr ON cv.request_id = cr.id
                SET " . implode(', ', $sets) . "
                WHERE cra.id = :id AND cr.tenant_id = :tenant_id
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('Allocation not found or does not belong to this tenant.');
            }
            return $id;
        }

        // Insert
        // First verify that the certificate (version) belongs to the tenant
        $stmtCheck = $this->pdo->prepare("
            SELECT cv.id
            FROM certificates_versions cv
            INNER JOIN certificates_requests cr ON cv.request_id = cr.id
            WHERE cv.id = :certificate_id AND cr.tenant_id = :tenant_id
        ");
        $stmtCheck->execute([':certificate_id' => $data['certificate_id'], ':tenant_id' => $tenantId]);
        if (!$stmtCheck->fetch()) {
            throw new RuntimeException('Certificate version does not exist or does not belong to this tenant.');
        }

        // Also verify that the payment receipt belongs to the same tenant? payments table has tenant_id directly.
        // We can skip because payment already linked to request, but optional check:
        if (!empty($data['receipt_id'])) {
            $stmtPay = $this->pdo->prepare("SELECT id FROM certificates_payments WHERE id = :receipt_id AND tenant_id = :tenant_id");
            $stmtPay->execute([':receipt_id' => $data['receipt_id'], ':tenant_id' => $tenantId]);
            if (!$stmtPay->fetch()) {
                throw new RuntimeException('Payment receipt does not exist or does not belong to this tenant.');
            }
        }

        // fee_id can be checked optionally (fee_rules are global, no tenant)

        $insertCols = [];
        $placeholders = [];
        $params = [];
        $allowed = ['receipt_id', 'certificate_id', 'fee_id', 'allocated_amount'];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data) && $data[$col] !== null && $data[$col] !== '') {
                $insertCols[] = $col;
                $placeholders[] = ":$col";
                $params[":$col"] = $data[$col];
            }
        }
        if (empty($insertCols)) {
            throw new InvalidArgumentException('No data provided for insert');
        }

        $sql = "INSERT INTO Certificate_receipt_allocations (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $tenantId, int $id): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE cra
            FROM Certificate_receipt_allocations cra
            INNER JOIN certificates_versions cv ON cra.certificate_id = cv.id
            INNER JOIN certificates_requests cr ON cv.request_id = cr.id
            WHERE cra.id = :id AND cr.tenant_id = :tenant_id
        ");
        $stmt->execute([':id' => $id, ':tenant_id' => $tenantId]);
        return $stmt->rowCount() > 0;
    }
}