<?php
declare(strict_types=1);

final class PdoCertificatesAuditsRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = [
        'id', 'request_id', 'auditor_id', 'audit_date', 'status',
        'assigned_by', 'assigned_at', 'created_at'
    ];

    private const FILTERABLE_COLUMNS = [
        'request_id', 'auditor_id', 'status', 'assigned_by'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get list of audits with tenant verification via certificates_requests.
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
            SELECT ca.*, cr.entity_id, cr.status as request_status, e.store_name as entity_name,
                   (SELECT id FROM certificates_payments WHERE request_id = ca.request_id ORDER BY id DESC LIMIT 1) as pay_id
            FROM certificates_audits ca
            INNER JOIN certificates_requests cr ON ca.request_id = cr.id
            LEFT JOIN entities e ON cr.entity_id = e.id
            WHERE cr.tenant_id = :tenant_id
        ";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND ca.{$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        // Date range filters for audit_date
        if (!empty($filters['audit_date_from'])) {
            $sql .= " AND ca.audit_date >= :audit_date_from";
            $params[':audit_date_from'] = $filters['audit_date_from'];
        }
        if (!empty($filters['audit_date_to'])) {
            $sql .= " AND ca.audit_date <= :audit_date_to";
            $params[':audit_date_to'] = $filters['audit_date_to'];
        }

        $orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY ca.{$orderBy} {$orderDir}";

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
            SELECT COUNT(ca.id)
            FROM certificates_audits ca
            INNER JOIN certificates_requests cr ON ca.request_id = cr.id
            WHERE cr.tenant_id = :tenant_id
        ";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND ca.{$col} = :{$col}";
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
            SELECT ca.*
            FROM certificates_audits ca
            INNER JOIN certificates_requests cr ON ca.request_id = cr.id
            WHERE cr.tenant_id = :tenant_id AND ca.id = :id
            LIMIT 1
        ");
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':id'        => $id
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Save (insert or update) an audit.
     * Note: For update, we also verify tenant by joining with certificates_requests.
     */
    public function save(int $tenantId, array $data): int
    {
        $isUpdate = !empty($data['id']);

        if ($isUpdate) {
            $id = (int)$data['id'];
            unset($data['id']);

            $sets = [];
            $params = [':id' => $id, ':tenant_id' => $tenantId];
            foreach ($data as $col => $value) {
                if (in_array($col, ['request_id', 'auditor_id', 'audit_date', 'status', 'notes', 'assigned_by', 'assigned_at'])) {
                    $sets[] = "$col = :$col";
                    $params[":$col"] = ($value === '' ? null : $value);
                }
            }
            if (empty($sets)) {
                throw new InvalidArgumentException('No fields to update');
            }

            // Update with tenant verification
            $sql = "
                UPDATE certificates_audits ca
                INNER JOIN certificates_requests cr ON ca.request_id = cr.id
                SET " . implode(', ', $sets) . "
                WHERE ca.id = :id AND cr.tenant_id = :tenant_id
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('Audit not found or does not belong to this tenant.');
            }
            return $id;
        }

        // Insert
        $insertCols = [];
        $placeholders = [];
        $params = [];
        foreach (['request_id', 'auditor_id', 'audit_date', 'status', 'notes', 'assigned_by', 'assigned_at'] as $col) {
            if (array_key_exists($col, $data) && $data[$col] !== null && $data[$col] !== '') {
                $insertCols[] = $col;
                $placeholders[] = ":$col";
                $params[":$col"] = $data[$col];
            } elseif ($col === 'status' && !isset($data['status'])) {
                // Default status if not provided? Not required because column is NOT NULL, so we must set it.
                // We'll require status in validator.
            }
        }

        if (empty($insertCols)) {
            throw new InvalidArgumentException('No data provided for insert');
        }

        // We need to verify that the request_id belongs to the tenant before inserting.
        // We can either do a separate check or use a subquery. Here we'll do a check using a SELECT.
        $stmtCheck = $this->pdo->prepare("SELECT id FROM certificates_requests WHERE id = :request_id AND tenant_id = :tenant_id");
        $stmtCheck->execute([':request_id' => $data['request_id'], ':tenant_id' => $tenantId]);
        if (!$stmtCheck->fetch()) {
            throw new RuntimeException('Request does not exist or does not belong to this tenant.');
        }

        $sql = "INSERT INTO certificates_audits (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $tenantId, int $id): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE ca
            FROM certificates_audits ca
            INNER JOIN certificates_requests cr ON ca.request_id = cr.id
            WHERE ca.id = :id AND cr.tenant_id = :tenant_id
        ");
        $stmt->execute([':id' => $id, ':tenant_id' => $tenantId]);
        return $stmt->rowCount() > 0;
    }
}