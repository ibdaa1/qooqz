<?php
declare(strict_types=1);

final class PdoMunicipalityOfficialsRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = [
        'id', 'name', 'position', 'tenant_id', 'created_at', 'updated_at'
    ];

    private const FILTERABLE_COLUMNS = [
        'name', 'position', 'tenant_id'
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
        $sql = "SELECT * FROM municipality_officials WHERE tenant_id = :tenant_id";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if ($col === 'tenant_id') continue;
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if ($col === 'name' || $col === 'position') {
                    // بحث جزئي للنصوص
                    $sql .= " AND {$col} LIKE :{$col}";
                    $params[":{$col}"] = '%' . $filters[$col] . '%';
                } else {
                    $sql .= " AND {$col} = :{$col}";
                    $params[":{$col}"] = $filters[$col];
                }
            }
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
        $sql = "SELECT COUNT(*) FROM municipality_officials WHERE tenant_id = :tenant_id";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if ($col === 'tenant_id') continue;
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if ($col === 'name' || $col === 'position') {
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

    public function find(int $tenantId, int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM municipality_officials WHERE tenant_id = :tenant_id AND id = :id");
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
            $allowed = ['name', 'position'];
            foreach ($allowed as $col) {
                if (array_key_exists($col, $data)) {
                    $sets[] = "$col = :$col";
                    $params[":$col"] = ($data[$col] === '' ? null : $data[$col]);
                }
            }
            if (empty($sets)) {
                throw new InvalidArgumentException('No fields to update');
            }

            $sql = "UPDATE municipality_officials SET " . implode(', ', $sets) . ", updated_at = CURRENT_TIMESTAMP WHERE id = :id AND tenant_id = :tenant_id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('Official not found or does not belong to this tenant.');
            }
            return $id;
        }

        // Insert
        $cols = [];
        $placeholders = [];
        $params = [':tenant_id' => $tenantId];
        $allowed = ['name', 'position'];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data) && $data[$col] !== null && $data[$col] !== '') {
                $cols[] = $col;
                $placeholders[] = ":$col";
                $params[":$col"] = $data[$col];
            } elseif ($col === 'name' && !isset($data['name'])) {
                throw new InvalidArgumentException('Field "name" is required.');
            }
        }
        // Always include tenant_id
        $cols[] = 'tenant_id';
        $placeholders[] = ':tenant_id';

        $sql = "INSERT INTO municipality_officials (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $tenantId, int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM municipality_officials WHERE tenant_id = :tenant_id AND id = :id");
        $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
        return $stmt->rowCount() > 0;
    }
}