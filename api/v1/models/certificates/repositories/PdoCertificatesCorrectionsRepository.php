<?php
declare(strict_types=1);

final class PdoCertificatesCorrectionsRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = [
        'id', 'request_id', 'requested_by', 'error_source', 'status',
        'payment_required', 'payment_paid', 'reviewed_by', 'reviewed_at',
        'created_at'
    ];

    private const FILTERABLE_COLUMNS = [
        'request_id', 'requested_by', 'error_source', 'status',
        'payment_required', 'payment_paid', 'reviewed_by'
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
        $sql = "
            SELECT cc.*
            FROM certificates_corrections cc
            INNER JOIN certificates_requests cr ON cc.request_id = cr.id
            WHERE cr.tenant_id = :tenant_id
        ";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND cc.{$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        // Date range for created_at or reviewed_at could be added if needed

        $orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY cc.{$orderBy} {$orderDir}";

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
            SELECT COUNT(cc.id)
            FROM certificates_corrections cc
            INNER JOIN certificates_requests cr ON cc.request_id = cr.id
            WHERE cr.tenant_id = :tenant_id
        ";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND cc.{$col} = :{$col}";
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
            SELECT cc.*
            FROM certificates_corrections cc
            INNER JOIN certificates_requests cr ON cc.request_id = cr.id
            WHERE cr.tenant_id = :tenant_id AND cc.id = :id
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
            $allowed = [
                'request_id', 'requested_by', 'correction_reason', 'error_source',
                'status', 'payment_required', 'payment_paid', 'reviewed_by', 'reviewed_at'
            ];
            foreach ($allowed as $col) {
                if (array_key_exists($col, $data)) {
                    $sets[] = "$col = :$col";
                    $params[":$col"] = ($data[$col] === '' ? null : $data[$col]);
                }
            }
            if (empty($sets)) {
                throw new InvalidArgumentException('No fields to update');
            }

            // Update with tenant verification
            $sql = "
                UPDATE certificates_corrections cc
                INNER JOIN certificates_requests cr ON cc.request_id = cr.id
                SET " . implode(', ', $sets) . "
                WHERE cc.id = :id AND cr.tenant_id = :tenant_id
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('Correction record not found or does not belong to this tenant.');
            }
            return $id;
        }

        // Insert
        // First verify that the request_id belongs to the tenant
        $stmtCheck = $this->pdo->prepare("SELECT id FROM certificates_requests WHERE id = :request_id AND tenant_id = :tenant_id");
        $stmtCheck->execute([':request_id' => $data['request_id'], ':tenant_id' => $tenantId]);
        if (!$stmtCheck->fetch()) {
            throw new RuntimeException('Request does not exist or does not belong to this tenant.');
        }

        $cols = [];
        $placeholders = [];
        $params = [];
        $allowed = [
            'request_id', 'requested_by', 'correction_reason', 'error_source',
            'status', 'payment_required', 'payment_paid', 'reviewed_by', 'reviewed_at'
        ];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data) && $data[$col] !== null && $data[$col] !== '') {
                $cols[] = $col;
                $placeholders[] = ":$col";
                $params[":$col"] = $data[$col];
            } elseif ($col === 'status' && !isset($data['status'])) {
                // default status is 'pending' according to table default? Actually default is NULL but set to 'pending' in enum? Let's check: status enum(...) YES pending, so default can be pending. We'll allow not setting it.
            } elseif (in_array($col, ['payment_required', 'payment_paid']) && !isset($data[$col])) {
                // these default to 0 in database, so we can omit them.
            }
        }
        // Ensure required fields: request_id, requested_by, correction_reason, error_source must be present
        $required = ['request_id', 'requested_by', 'correction_reason', 'error_source'];
        foreach ($required as $req) {
            if (!in_array($req, $cols)) {
                throw new InvalidArgumentException("Field '$req' is required.");
            }
        }

        $sql = "INSERT INTO certificates_corrections (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $tenantId, int $id): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE cc
            FROM certificates_corrections cc
            INNER JOIN certificates_requests cr ON cc.request_id = cr.id
            WHERE cc.id = :id AND cr.tenant_id = :tenant_id
        ");
        $stmt->execute([':id' => $id, ':tenant_id' => $tenantId]);
        return $stmt->rowCount() > 0;
    }
}