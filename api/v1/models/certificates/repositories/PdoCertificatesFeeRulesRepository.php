<?php
declare(strict_types=1);

final class PdoCertificatesFeeRulesRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = [
        'id', 'fee_type', 'max_items', 'amount', 'currency', 'is_active'
    ];

    private const FILTERABLE_COLUMNS = [
        'fee_type', 'max_items', 'is_active'
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
        $sql = "SELECT * FROM certificates_fee_rules WHERE 1=1";
        $params = [];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if ($col === 'max_items') {
                    // max_items can be null, so if we filter by a specific value, we need to handle nulls? 
                    // For simplicity, we treat exact match.
                    $sql .= " AND {$col} = :{$col}";
                    $params[":{$col}"] = $filters[$col];
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
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
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
        $sql = "SELECT COUNT(*) FROM certificates_fee_rules WHERE 1=1";
        $params = [];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND {$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM certificates_fee_rules WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Save (insert or update) a fee rule.
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
                if (in_array($col, ['fee_type', 'max_items', 'amount', 'currency', 'is_active'])) {
                    $sets[] = "$col = :$col";
                    $params[":$col"] = ($value === '' ? null : $value);
                }
            }
            if (empty($sets)) {
                throw new InvalidArgumentException('No fields to update');
            }

            $sql = "UPDATE certificates_fee_rules SET " . implode(', ', $sets) . " WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $id;
        }

        // Insert
        $insertCols = [];
        $placeholders = [];
        $params = [];
        foreach (['fee_type', 'max_items', 'amount', 'currency', 'is_active'] as $col) {
            if (array_key_exists($col, $data) && $data[$col] !== null && $data[$col] !== '') {
                $insertCols[] = $col;
                $placeholders[] = ":$col";
                $params[":$col"] = $data[$col];
            } elseif ($col === 'currency' && !isset($data['currency'])) {
                // default currency from schema is AED
                $insertCols[] = 'currency';
                $placeholders[] = ':currency';
                $params[':currency'] = 'AED';
            } elseif ($col === 'is_active' && !isset($data['is_active'])) {
                $insertCols[] = 'is_active';
                $placeholders[] = ':is_active';
                $params[':is_active'] = 1;
            } elseif ($col === 'max_items' && !isset($data['max_items'])) {
                // max_items is nullable, so we can omit it if not provided
                continue;
            } elseif ($col === 'fee_type' && !isset($data['fee_type'])) {
                throw new InvalidArgumentException('fee_type is required.');
            } elseif ($col === 'amount' && !isset($data['amount'])) {
                throw new InvalidArgumentException('amount is required.');
            }
        }

        $sql = "INSERT INTO certificates_fee_rules (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM certificates_fee_rules WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}