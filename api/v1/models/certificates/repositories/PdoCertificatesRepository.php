<?php
declare(strict_types=1);

final class PdoCertificatesRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = [
        'id', 'code', 'description', 'is_active'
    ];

    private const FILTERABLE_COLUMNS = [
        'code', 'is_active'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get list of certificates with optional filters, ordering and pagination.
     */
    public function all(
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC',
        ?int $limit = null,
        ?int $offset = null
    ): array {
        $sql = "SELECT * FROM certificates WHERE 1=1";
        $params = [];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if ($col === 'code') {
                    // استخدام LIKE للبحث الجزئي في الكود
                    $sql .= " AND {$col} LIKE :{$col}";
                    $params[":{$col}"] = '%' . $filters[$col] . '%';
                } else {
                    $sql .= " AND {$col} = :{$col}";
                    $params[":{$col}"] = $filters[$col];
                }
            }
        }

        // التحقق من صحة orderBy
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

        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $type);
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

    /**
     * Count total records after applying filters.
     */
    public function count(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM certificates WHERE 1=1";
        $params = [];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if ($col === 'code') {
                    $sql .= " AND {$col} LIKE :{$col}";
                    $params[":{$col}"] = '%' . $filters[$col] . '%';
                } else {
                    $sql .= " AND {$col} = :{$col}";
                    $params[":{$col}"] = $filters[$col];
                }
            }
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Find a single certificate by ID.
     */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM certificates WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Find a certificate by its unique code.
     */
    public function findByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM certificates WHERE code = :code LIMIT 1");
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Save (insert or update) a certificate.
     */
    public function save(array $data): int
    {
        $isUpdate = !empty($data['id']);

        if ($isUpdate) {
            $id = (int)$data['id'];
            unset($data['id']);

            $sets = [];
            $params = [':id' => $id];
            foreach ($data as $col => $value) {
                if (in_array($col, ['code', 'description', 'is_active'])) {
                    $sets[] = "$col = :$col";
                    $params[":$col"] = ($value === '' ? null : $value);
                }
            }
            if (empty($sets)) {
                throw new InvalidArgumentException('No fields to update');
            }

            $sql = "UPDATE certificates SET " . implode(', ', $sets) . " WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $id;
        }

        // Insert
        $insertCols = [];
        $placeholders = [];
        $params = [];
        foreach (['code', 'description', 'is_active'] as $col) {
            if (array_key_exists($col, $data)) {
                $insertCols[] = $col;
                $placeholders[] = ":$col";
                $params[":$col"] = ($data[$col] === '' ? null : $data[$col]);
            }
        }
        // إذا لم يتم إرسال is_active، نستخدم القيمة الافتراضية 1
        if (!array_key_exists('is_active', $data)) {
            $insertCols[] = 'is_active';
            $placeholders[] = ':is_active';
            $params[':is_active'] = 1;
        }

        $sql = "INSERT INTO certificates (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Delete a certificate by ID.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM certificates WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}