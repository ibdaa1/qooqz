<?php
declare(strict_types=1);

final class PdoCertificatesRequestItemsTranslationsRepository
{
    private PDO $pdo;

    // الأعمدة المسموح بها للفرز
    private const ALLOWED_ORDER_BY = ['id', 'item_id', 'language_code', 'name'];

    // الأعمدة القابلة للفلاتر
    private const FILTERABLE_COLUMNS = ['id', 'item_id', 'language_code'];

    // الأعمدة المسموحة للحفظ (تمت إزالة brand لأنها غير موجودة في الجدول)
    private const ITEM_TRANSLATION_COLUMNS = [
        'item_id', 'language_code', 'name', 'notes'
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
            FROM certificates_request_items_translations t
            INNER JOIN certificates_request_items i ON t.item_id = i.id
            INNER JOIN certificates_requests r ON i.request_id = r.id
            WHERE r.tenant_id = :tenant_id
        ";
        
        $params = [':tenant_id' => $tenantId];

        // تطبيق كل الفلاتر بشكل ديناميكي
        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if ($col === 'language_code') {
                    $sql .= " AND t.{$col} = :{$col}";
                    $params[":{$col}"] = $filters[$col];
                } else {
                    // IDs are integers
                    $sql .= " AND t.{$col} = :{$col}";
                    $params[":{$col}"] = (int)$filters[$col];
                }
            }
        }

        // الفرز
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
            FROM certificates_request_items_translations t
            INNER JOIN certificates_request_items i ON t.item_id = i.id
            INNER JOIN certificates_requests r ON i.request_id = r.id
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
            FROM certificates_request_items_translations t
            INNER JOIN certificates_request_items i ON t.item_id = i.id
            INNER JOIN certificates_requests r ON i.request_id = r.id
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

        // التحقق من العنصر الأب (Parent Item) والتأكد من أنه يتبع نفس المستأجر
        $parentItemId = $isUpdate 
            ? ($this->find((int)$data['id'])['item_id'] ?? null) 
            : ($data['item_id'] ?? null);

        if ($parentItemId) {
            $checkStmt = $this->pdo->prepare("
                SELECT i.id 
                FROM certificates_request_items i 
                INNER JOIN certificates_requests r ON i.request_id = r.id 
                WHERE i.id = :item_id AND r.tenant_id = :tenant_id 
                LIMIT 1
            ");
            $checkStmt->execute([':item_id' => $parentItemId, ':tenant_id' => $tenantId]);
            if (!$checkStmt->fetch()) {
                throw new InvalidArgumentException("Parent item does not exist or does not belong to this tenant.");
            }
        } elseif (!$isUpdate) {
             throw new InvalidArgumentException("item_id is required.");
        }

        $params = [];
        foreach (self::ITEM_TRANSLATION_COLUMNS as $col) {
            $val = $data[$col] ?? null;
            $params[':' . $col] = ($val === '' || $val === null) ? null : $val;
        }

        if ($isUpdate) {
            $params[':id'] = (int)$data['id'];
            $sql = "UPDATE certificates_request_items_translations SET ";
            $sets = [];
            foreach (self::ITEM_TRANSLATION_COLUMNS as $col) {
                $sets[] = "{$col} = :{$col}";
            }
            $sql .= implode(', ', $sets) . " WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return (int)$data['id'];
        }

        $cols = implode(', ', self::ITEM_TRANSLATION_COLUMNS);
        $placeholders = implode(', ', array_map(fn($c) => ":{$c}", self::ITEM_TRANSLATION_COLUMNS));
        $sql = "INSERT INTO certificates_request_items_translations ($cols) VALUES ($placeholders)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    // ================================
    // Delete
    // ================================
    public function delete(int $tenantId, int $id): bool
    {
        // التحقق من الملكية قبل الحذف
        if (!$this->find($tenantId, $id)) {
            return false;
        }

        $stmt = $this->pdo->prepare("
            DELETE t FROM certificates_request_items_translations t
            INNER JOIN certificates_request_items i ON t.item_id = i.id
            INNER JOIN certificates_requests r ON i.request_id = r.id
            WHERE r.tenant_id = :tenant_id AND t.id = :id
        ");
        return $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
    }
}