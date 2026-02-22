<?php
declare(strict_types=1);

final class PdoCertificatesRequestsTranslationsRepository
{
    private PDO $pdo;

    // Allowed columns for sorting
    private const ALLOWED_ORDER_BY = ['id', 'request_id', 'language_code', 'description'];

    // Columns available for filtering
    private const FILTERABLE_COLUMNS = ['id', 'request_id', 'language_code'];

    // Columns allowed for Insert/Update
    private const TRANSLATION_COLUMNS = [
        'request_id', 'language_code', 'description', 'notes'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ================================
    // List with dynamic filters, search, ordering, pagination
    // ================================
    public function all(
        int $tenantId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        $sql = "
            SELECT t.*
            FROM certificates_requests_translations t
            INNER JOIN certificates_requests r ON t.request_id = r.id
            WHERE r.tenant_id = :tenant_id
        ";
        
        $params = [':tenant_id' => $tenantId];

        // Apply dynamic filters
        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if ($col === 'language_code') {
                    $sql .= " AND t.{$col} = :{$col}";
                    $params[":{$col}"] = $filters[$col];
                } else {
                    // ID and request_id are integers
                    $sql .= " AND t.{$col} = :{$col}";
                    $params[":{$col}"] = (int)$filters[$col];
                }
            }
        }

        // Ordering
        $orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY t.{$orderBy} {$orderDir}";

        // Pagination
        if ($limit !== null) $sql .= " LIMIT :limit";
        if ($offset !== null) $sql .= " OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $type);
        }
        if ($limit !== null) $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        if ($offset !== null) $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================
    // Count for pagination
    // ================================
    public function count(int $tenantId, array $filters = []): int
    {
        $sql = "
            SELECT COUNT(t.id)
            FROM certificates_requests_translations t
            INNER JOIN certificates_requests r ON t.request_id = r.id
            WHERE r.tenant_id = :tenant_id
        ";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND t.{$col} = :{$col}";
                $params[":{$col}"] = is_int($filters[$col]) ? (int)$filters[$col] : $filters[$col];
            }
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    // ================================
    // Find by ID
    // ================================
    public function find(int $tenantId, int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT t.*
            FROM certificates_requests_translations t
            INNER JOIN certificates_requests r ON t.request_id = r.id
            WHERE r.tenant_id = :tenant_id AND t.id = :id
            LIMIT 1
        ");
        $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================================
    // Create / Update
    // ================================
    public function save(int $tenantId, array $data): int
    {
        $isUpdate = !empty($data['id']);

        // Verify parent request belongs to tenant
        $parentReqId = $isUpdate 
            ? ($this->find((int)$data['id'])['request_id'] ?? null) 
            : ($data['request_id'] ?? null);

        if ($parentReqId) {
            $checkStmt = $this->pdo->prepare("SELECT id FROM certificates_requests WHERE id = :rid AND tenant_id = :tid LIMIT 1");
            $checkStmt->execute([':rid' => $parentReqId, ':tid' => $tenantId]);
            if (!$checkStmt->fetch()) {
                throw new InvalidArgumentException("Parent request does not exist or does not belong to this tenant.");
            }
        } elseif (!$isUpdate) {
             throw new InvalidArgumentException("request_id is required.");
        }

        $params = [];
        foreach (self::TRANSLATION_COLUMNS as $col) {
            $val = $data[$col] ?? null;
            $params[':' . $col] = ($val === '' || $val === null) ? null : $val;
        }

        if ($isUpdate) {
            $params[':id'] = (int)$data['id'];
            $sql = "UPDATE certificates_requests_translations SET ";
            $sets = [];
            foreach (self::TRANSLATION_COLUMNS as $col) {
                $sets[] = "{$col} = :{$col}";
            }
            $sql .= implode(', ', $sets) . " WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return (int)$data['id'];
        }

        $cols = implode(', ', self::TRANSLATION_COLUMNS);
        $placeholders = implode(', ', array_map(fn($c) => ":{$c}", self::TRANSLATION_COLUMNS));
        $sql = "INSERT INTO certificates_requests_translations ($cols) VALUES ($placeholders)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    // ================================
    // Delete
    // ================================
    public function delete(int $tenantId, int $id): bool
    {
        // Verify ownership before deleting
        if (!$this->find($tenantId, $id)) {
            return false;
        }

        $stmt = $this->pdo->prepare("
            DELETE t FROM certificates_requests_translations t
            INNER JOIN certificates_requests r ON t.request_id = r.id
            WHERE r.tenant_id = :tenant_id AND t.id = :id
        ");
        return $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
    }
}