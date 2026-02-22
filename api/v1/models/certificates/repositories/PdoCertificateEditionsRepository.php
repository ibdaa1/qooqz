<?php
declare(strict_types=1);

final class PdoCertificateEditionsRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = [
        'id', 'certificate_id', 'code', 'language_code', 'scope',
        'certificate_version', 'is_active'
    ];

    private const FILTERABLE_COLUMNS = [
        'certificate_id', 'code', 'language_code', 'scope', 'certificate_version', 'is_active'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC',
        ?int $limit = null,
        ?int $offset = null
    ): array {
        $sql = "SELECT * FROM certificate_editions WHERE 1=1";
        $params = [];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if ($col === 'code') {
                    $sql .= " AND code LIKE :code";
                    $params[':code'] = '%' . $filters['code'] . '%';
                } else {
                    $sql .= " AND $col = :$col";
                    $params[":$col"] = $filters[$col];
                }
            }
        }

        $orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY $orderBy $orderDir";

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

    public function count(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM certificate_editions WHERE 1=1";
        $params = [];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if ($col === 'code') {
                    $sql .= " AND code LIKE :code";
                    $params[':code'] = '%' . $filters['code'] . '%';
                } else {
                    $sql .= " AND $col = :$col";
                    $params[":$col"] = $filters[$col];
                }
            }
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM certificate_editions WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM certificate_editions WHERE code = :code");
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(array $data): int
    {
        $isUpdate = !empty($data['id']);

        if ($isUpdate) {
            $id = (int)$data['id'];
            unset($data['id']);

            $sets = [];
            $params = [':id' => $id];
            $allowed = ['certificate_id', 'code', 'language_code', 'scope', 'certificate_version', 'is_active'];
            foreach ($allowed as $col) {
                if (array_key_exists($col, $data)) {
                    $sets[] = "$col = :$col";
                    $params[":$col"] = ($data[$col] === '' ? null : $data[$col]);
                }
            }
            if (empty($sets)) {
                throw new InvalidArgumentException('No fields to update');
            }

            $sql = "UPDATE certificate_editions SET " . implode(', ', $sets) . " WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $id;
        }

        // Insert
        $cols = [];
        $placeholders = [];
        $params = [];
        $allowed = ['certificate_id', 'code', 'language_code', 'scope', 'certificate_version', 'is_active'];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data) && $data[$col] !== null && $data[$col] !== '') {
                $cols[] = $col;
                $placeholders[] = ":$col";
                $params[":$col"] = $data[$col];
            }
        }
        // If is_active not provided, default 1 will be used by database (default defined in schema)
        // But we may want to explicitly set it to 1 if not present? We'll let DB handle default.

        if (empty($cols)) {
            throw new InvalidArgumentException('No data provided for insert');
        }

        $sql = "INSERT INTO certificate_editions (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM certificate_editions WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}