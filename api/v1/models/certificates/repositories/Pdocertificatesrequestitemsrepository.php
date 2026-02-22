<?php
declare(strict_types=1);

final class PdoCertificatesRequestItemsRepository
{
    private PDO $pdo;

    // product_condition حُذف من الجدول
    private const FILTERABLE = [
        'request_id', 'product_id', 'weight_unit_id'
    ];

    private const ALLOWED_ORDER_BY = [
        'id', 'request_id', 'product_id', 'quantity',
        'net_weight', 'weight_unit_id',
        'production_date', 'expiry_date', 'created_at', 'updated_at'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ================================
    // List with filters, ordering, pagination
    // ================================
    public function all(
        array $filters = [],
        ?int $limit = null,
        ?int $offset = null,
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        $sql = "
            SELECT i.*,
                   p.entity_id          AS product_entity_id,
                   p.entity_product_code,
                   p.brand_id           AS product_brand_id,
                   u.code               AS weight_unit_code,
                   c.name               AS country_of_origin
            FROM certificates_request_items i
            LEFT JOIN certificates_products p ON p.id = i.product_id
            LEFT JOIN units u ON u.id = i.weight_unit_id
            LEFT JOIN countries c ON c.id = p.origin_country_id
            WHERE 1=1
        ";
        $params = [];

        foreach (self::FILTERABLE as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND i.{$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }

        if (isset($filters['quantity_min']) && is_numeric($filters['quantity_min'])) {
            $sql .= " AND i.quantity >= :quantity_min";
            $params[':quantity_min'] = $filters['quantity_min'];
        }
        if (isset($filters['quantity_max']) && is_numeric($filters['quantity_max'])) {
            $sql .= " AND i.quantity <= :quantity_max";
            $params[':quantity_max'] = $filters['quantity_max'];
        }
        if (!empty($filters['production_date_from'])) {
            $sql .= " AND i.production_date >= :production_date_from";
            $params[':production_date_from'] = $filters['production_date_from'];
        }
        if (!empty($filters['expiry_date_to'])) {
            $sql .= " AND i.expiry_date <= :expiry_date_to";
            $params[':expiry_date_to'] = $filters['expiry_date_to'];
        }

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY i.{$orderBy} {$orderDir}";

        if ($limit !== null)  $sql .= " LIMIT :limit";
        if ($offset !== null) $sql .= " OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        if ($limit !== null)  $stmt->bindValue(':limit',  (int)$limit,  PDO::PARAM_INT);
        if ($offset !== null) $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================
    // Count
    // ================================
    public function count(array $filters = []): int
    {
        $sql    = "SELECT COUNT(*) FROM certificates_request_items i WHERE 1=1";
        $params = [];

        foreach (self::FILTERABLE as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                $sql .= " AND i.{$col} = :{$col}";
                $params[":{$col}"] = $filters[$col];
            }
        }
        if (isset($filters['quantity_min']) && is_numeric($filters['quantity_min'])) {
            $sql .= " AND i.quantity >= :quantity_min";
            $params[':quantity_min'] = $filters['quantity_min'];
        }
        if (isset($filters['quantity_max']) && is_numeric($filters['quantity_max'])) {
            $sql .= " AND i.quantity <= :quantity_max";
            $params[':quantity_max'] = $filters['quantity_max'];
        }
        if (!empty($filters['production_date_from'])) {
            $sql .= " AND i.production_date >= :production_date_from";
            $params[':production_date_from'] = $filters['production_date_from'];
        }
        if (!empty($filters['expiry_date_to'])) {
            $sql .= " AND i.expiry_date <= :expiry_date_to";
            $params[':expiry_date_to'] = $filters['expiry_date_to'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    // ================================
    // Find by ID
    // ================================
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM certificates_request_items WHERE id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================================
    // Find all items for a request
    // ================================
    public function findByRequest(int $requestId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM certificates_request_items WHERE request_id = :request_id ORDER BY id ASC"
        );
        $stmt->execute([':request_id' => $requestId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================
    // Save (Insert / Update)
    // ================================
    public function save(array $data): int
    {
        $isUpdate = !empty($data['id']);

        // product_condition محذوف من الجدول
        $params = [
            ':request_id'          => $data['request_id'],
            ':product_id'          => $data['product_id'],
            ':quantity'            => $data['quantity'],
            ':net_weight'          => isset($data['net_weight'])      && $data['net_weight'] !== ''      ? $data['net_weight']          : null,
            ':weight_unit_id'      => isset($data['weight_unit_id'])  && $data['weight_unit_id'] !== ''  ? (int)$data['weight_unit_id'] : null,
            ':production_date'     => isset($data['production_date']) && $data['production_date'] !== '' ? $data['production_date']     : null,
            ':expiry_date'         => isset($data['expiry_date'])     && $data['expiry_date'] !== ''     ? $data['expiry_date']         : null,
            ':notes'               => $data['notes'] ?? null,
        ];

        if ($isUpdate) {
            $params[':id'] = (int)$data['id'];
            $stmt = $this->pdo->prepare("
                UPDATE certificates_request_items SET
                    request_id            = :request_id,
                    product_id            = :product_id,
                    quantity              = :quantity,
                    net_weight            = :net_weight,
                    weight_unit_id        = :weight_unit_id,
                    production_date       = :production_date,
                    expiry_date           = :expiry_date,
                    notes                 = :notes,
                    updated_at            = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->execute($params);
            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO certificates_request_items
                (request_id, product_id, quantity, net_weight, weight_unit_id,
                 production_date, expiry_date, notes)
            VALUES
                (:request_id, :product_id, :quantity, :net_weight, :weight_unit_id,
                 :production_date, :expiry_date, :notes)
        ");
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    // ================================
    // Delete
    // ================================
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM certificates_request_items WHERE id = :id"
        );
        return $stmt->execute([':id' => $id]);
    }

    public function deleteByRequest(int $requestId): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM certificates_request_items WHERE request_id = :request_id"
        );
        return $stmt->execute([':request_id' => $requestId]);
    }

    /**
     * Fetch items with full product details and translations for printing/viewing.
     */
    public function getItemsWithDetails(int $requestId, string $lang = 'ar'): array
    {
        $sql = "
            SELECT i.*,
                   COALESCE(pt.name, p.entity_product_code) AS name,
                   u.code                                   AS weight_unit_code,
                   cn.name                                  AS country_of_origin
            FROM certificates_request_items i
            LEFT JOIN certificates_products p ON p.id = i.product_id
            LEFT JOIN certificates_products_translations pt ON pt.product_id = p.id AND pt.language_code = :lang
            LEFT JOIN units u ON u.id = i.weight_unit_id
            LEFT JOIN countries cn ON cn.id = p.origin_country_id
            WHERE i.request_id = :request_id
            ORDER BY i.id ASC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':request_id' => $requestId, ':lang' => $lang]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}