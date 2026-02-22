<?php
declare(strict_types=1);

final class CertificatesProductsRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = [
        'id', 'tenant_id', 'entity_id', 'brand_id', 'entity_product_code',
        'net_weight', 'weight_unit', 'sample_status',
        'product_condition', 'origin_country_id',
        'created_at', 'updated_at'
    ];

    private const FILTERABLE_COLUMNS = [
        'tenant_id',
        'entity_id',
        'brand_id',
        'entity_product_code',
        'weight_unit',
        'sample_status',
        'product_condition',
        'origin_country_id'
    ];

    private const PRODUCT_COLUMNS = [
        'tenant_id',
        'entity_id',
        'brand_id',
        'entity_product_code',
        'net_weight',
        'weight_unit',
        'sample_status',
        'product_condition',
        'origin_country_id'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // =========================================================
    // LIST WITH COUNTRY JOIN
    // =========================================================
    public function all(
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC',
        ?int $limit = null,
        ?int $offset = null
    ): array {

        $sql = "
            SELECT 
                cp.*,
                c.name AS origin_country_name
            FROM certificates_products cp
            LEFT JOIN countries c 
                ON c.id = cp.origin_country_id
            WHERE 1=1
        ";

        $params = [];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '' && $filters[$col] !== null) {

                if ($col === 'entity_product_code') {
                    $sql .= " AND cp.$col LIKE :$col";
                    $params[":$col"] = '%' . $filters[$col] . '%';
                } else {
                    $sql .= " AND cp.$col = :$col";
                    $params[":$col"] = $filters[$col];
                }
            }
        }

        $orderBy  = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

        $sql .= " ORDER BY cp.$orderBy $orderDir";

        if ($limit !== null) {
            $sql .= " LIMIT :limit";
        }

        if ($offset !== null) {
            $sql .= " OFFSET :offset";
        }

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue(
                $key,
                $value,
                is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
            );
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

    // =========================================================
    // COUNT
    // =========================================================
    public function count(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM certificates_products cp WHERE 1=1";
        $params = [];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '' && $filters[$col] !== null) {

                if ($col === 'entity_product_code') {
                    $sql .= " AND cp.$col LIKE :$col";
                    $params[":$col"] = '%' . $filters[$col] . '%';
                } else {
                    $sql .= " AND cp.$col = :$col";
                    $params[":$col"] = $filters[$col];
                }
            }
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }

    // =========================================================
    // FIND
    // =========================================================
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                cp.*,
                c.name AS origin_country_name
            FROM certificates_products cp
            LEFT JOIN countries c 
                ON c.id = cp.origin_country_id
            WHERE cp.id = :id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    // =========================================================
    // SAVE (INSERT / UPDATE)
    // =========================================================
    public function save(array $data): int
    {
        $isUpdate = !empty($data['id']);

        $params = [];

        foreach (self::PRODUCT_COLUMNS as $col) {
            $value = $data[$col] ?? null;
            $params[":$col"] = ($value === '' ? null : $value);
        }

        // ================= UPDATE =================
        if ($isUpdate) {

            $params[':id'] = (int)$data['id'];

            $stmt = $this->pdo->prepare("
                UPDATE certificates_products SET
                    tenant_id           = :tenant_id,
                    entity_id           = :entity_id,
                    brand_id            = :brand_id,
                    entity_product_code = :entity_product_code,
                    net_weight          = :net_weight,
                    weight_unit         = :weight_unit,
                    sample_status       = :sample_status,
                    product_condition   = :product_condition,
                    origin_country_id   = :origin_country_id,
                    updated_at          = CURRENT_TIMESTAMP
                WHERE id = :id
            ");

            $stmt->execute($params);

            return (int)$data['id'];
        }

        // ================= INSERT =================
        $stmt = $this->pdo->prepare("
            INSERT INTO certificates_products
                (
                    tenant_id,
                    entity_id,
                    brand_id,
                    entity_product_code,
                    net_weight,
                    weight_unit,
                    sample_status,
                    product_condition,
                    origin_country_id
                )
            VALUES
                (
                    :tenant_id,
                    :entity_id,
                    :brand_id,
                    :entity_product_code,
                    :net_weight,
                    :weight_unit,
                    :sample_status,
                    :product_condition,
                    :origin_country_id
                )
        ");

        $stmt->execute($params);

        return (int)$this->pdo->lastInsertId();
    }

    // =========================================================
    // DELETE
    // =========================================================
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM certificates_products WHERE id = :id"
        );

        return $stmt->execute([':id' => $id]);
    }
}