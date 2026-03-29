<?php
declare(strict_types=1);

/**
 * Unified Entity Products Repository
 * Handles both product-level (variant_id IS NULL) and variant-level records
 */
final class PdoEntityProductsRepository
{
    private PDO $pdo;

    private const ALLOWED_ORDER_BY = [
        'id', 'entity_id', 'product_id', 'variant_id', 'price', 'stock_quantity',
        'is_active', 'is_featured', 'created_at', 'updated_at'
    ];

    private const FILTERABLE_COLUMNS = [
        'entity_id', 'product_id', 'variant_id', 'tenant_id', 'is_active', 'is_featured', 'stock_status'
    ];

    private const ENTITY_PRODUCT_COLUMNS = [
        'tenant_id', 'entity_id', 'product_id', 'variant_id',
        'stock_quantity', 'low_stock_threshold', 'manage_stock', 'stock_status',
        'is_active', 'is_featured'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Build shared WHERE clause and params for filters
     */
    private function buildFilterClauses(array $filters): array
    {
        $sql = '';
        $params = [];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if ($col === 'stock_status') {
                    $sql .= " AND ep.stock_status = :stock_status";
                    $params[":stock_status"] = $filters[$col];
                } elseif (is_numeric($filters[$col])) {
                    $sql .= " AND ep.{$col} = :{$col}";
                    $params[":{$col}"] = (int)$filters[$col];
                }
            }
        }

        // Filter: products only (variant_id IS NULL)
        if (isset($filters['products_only']) && $filters['products_only']) {
            $sql .= " AND ep.variant_id IS NULL";
        }

        // Filter: variants only (variant_id IS NOT NULL)
        if (isset($filters['variants_only']) && $filters['variants_only']) {
            $sql .= " AND ep.variant_id IS NOT NULL";
        }

        if (isset($filters['store_name']) && !empty($filters['store_name'])) {
            $sql .= " AND e.store_name LIKE :store_name";
            $params[":store_name"] = '%' . $filters['store_name'] . '%';
        }

        if (isset($filters['product_name']) && !empty($filters['product_name'])) {
            $sql .= " AND pt.name LIKE :product_name";
            $params[":product_name"] = '%' . $filters['product_name'] . '%';
        }

        if (isset($filters['product_sku']) && !empty($filters['product_sku'])) {
            $sql .= " AND p.sku LIKE :product_sku";
            $params[":product_sku"] = '%' . $filters['product_sku'] . '%';
        }

        if (isset($filters['variant_sku']) && !empty($filters['variant_sku'])) {
            $sql .= " AND pv.sku LIKE :variant_sku";
            $params[":variant_sku"] = '%' . $filters['variant_sku'] . '%';
        }

        if (isset($filters['search']) && !empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $sql .= " AND (pt.name LIKE :search_name OR p.sku LIKE :search_sku OR e.store_name LIKE :search_store OR pv.sku LIKE :search_vsku)";
            $params[":search_name"] = $searchTerm;
            $params[":search_sku"] = $searchTerm;
            $params[":search_store"] = $searchTerm;
            $params[":search_vsku"] = $searchTerm;
        }

        return ['sql' => $sql, 'params' => $params];
    }

    /**
     * List with dynamic filters, search, ordering, pagination
     */
    public function all(
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        $filterResult = $this->buildFilterClauses($filters);

        $sql = "
            SELECT ep.*,
                   e.store_name,
                   e.status as entity_status,
                   COALESCE(pt.name, '') as product_name,
                   p.sku as product_sku,
                   pv.sku as variant_sku,
                   pv.barcode as variant_barcode
            FROM entity_products ep
            LEFT JOIN entities e ON ep.entity_id = e.id
            LEFT JOIN products p ON ep.product_id = p.id
            LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = 'ar'
            LEFT JOIN product_variants pv ON ep.variant_id = pv.id
            WHERE 1=1
        " . $filterResult['sql'];
        $params = $filterResult['params'];

        $orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY ep.{$orderBy} {$orderDir}";

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

    /**
     * Count for pagination
     */
    public function count(array $filters = []): int
    {
        $filterResult = $this->buildFilterClauses($filters);

        $sql = "
            SELECT COUNT(*)
            FROM entity_products ep
            LEFT JOIN entities e ON ep.entity_id = e.id
            LEFT JOIN products p ON ep.product_id = p.id
            LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = 'ar'
            LEFT JOIN product_variants pv ON ep.variant_id = pv.id
            WHERE 1=1
        " . $filterResult['sql'];

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($filterResult['params']);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Find by ID
     */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT ep.*,
                   e.store_name,
                   e.status as entity_status,
                   COALESCE(pt.name, '') as product_name,
                   p.sku as product_sku,
                   pv.sku as variant_sku,
                   pv.barcode as variant_barcode
            FROM entity_products ep
            LEFT JOIN entities e ON ep.entity_id = e.id
            LEFT JOIN products p ON ep.product_id = p.id
            LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = 'ar'
            LEFT JOIN product_variants pv ON ep.variant_id = pv.id
            WHERE ep.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Find by entity and product (product-level record, variant_id IS NULL)
     */
    public function findByEntityAndProduct(int $entityId, int $productId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT ep.*,
                   e.store_name,
                   e.status as entity_status,
                   COALESCE(pt.name, '') as product_name,
                   p.sku as product_sku
            FROM entity_products ep
            LEFT JOIN entities e ON ep.entity_id = e.id
            LEFT JOIN products p ON ep.product_id = p.id
            LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = 'ar'
            WHERE ep.entity_id = :entity_id AND ep.product_id = :product_id AND ep.variant_id IS NULL
            LIMIT 1
        ");
        $stmt->execute([':entity_id' => $entityId, ':product_id' => $productId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Find by entity and variant (variant-level record)
     */
    public function findByEntityAndVariant(int $entityId, int $variantId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT ep.*,
                   e.store_name,
                   e.status as entity_status,
                   COALESCE(pt.name, '') as product_name,
                   p.sku as product_sku,
                   pv.sku as variant_sku,
                   pv.barcode as variant_barcode
            FROM entity_products ep
            LEFT JOIN entities e ON ep.entity_id = e.id
            LEFT JOIN products p ON ep.product_id = p.id
            LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = 'ar'
            LEFT JOIN product_variants pv ON ep.variant_id = pv.id
            WHERE ep.entity_id = :entity_id AND ep.variant_id = :variant_id
            LIMIT 1
        ");
        $stmt->execute([':entity_id' => $entityId, ':variant_id' => $variantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Get all products for an entity (product-level records, variant_id IS NULL, with pricing)
     */
    public function getEntityProducts(int $entityId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ep.*,
                   COALESCE(pt.name, '') as product_name,
                   p.sku as product_sku,
                   pp.id as pricing_id,
                   pp.price,
                   pp.compare_at_price,
                   pp.cost_price,
                   pp.currency_code,
                   pp.tax_rate
            FROM entity_products ep
            LEFT JOIN products p ON ep.product_id = p.id
            LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = 'ar'
            LEFT JOIN product_pricing pp ON pp.product_id = ep.product_id
                AND pp.entity_id = ep.entity_id
                AND pp.is_active = 1
            WHERE ep.entity_id = :entity_id AND ep.variant_id IS NULL
            ORDER BY ep.is_featured DESC, ep.id DESC
        ");
        $stmt->execute([':entity_id' => $entityId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all variants for an entity (variant-level records, variant_id IS NOT NULL)
     */
    public function getEntityVariants(int $entityId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ep.*,
                   COALESCE(pt.name, '') as product_name,
                   pv.sku as variant_sku,
                   pv.barcode as variant_barcode
            FROM entity_products ep
            LEFT JOIN products p ON ep.product_id = p.id
            LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = 'ar'
            LEFT JOIN product_variants pv ON ep.variant_id = pv.id
            WHERE ep.entity_id = :entity_id AND ep.variant_id IS NOT NULL
            ORDER BY ep.product_id, ep.id DESC
        ");
        $stmt->execute([':entity_id' => $entityId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get variants for a specific entity product
     */
    public function getEntityProductVariants(int $entityId, int $productId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ep.*,
                   COALESCE(pt.name, '') as product_name,
                   pv.sku as variant_sku,
                   pv.barcode as variant_barcode
            FROM entity_products ep
            LEFT JOIN products p ON ep.product_id = p.id
            LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = 'ar'
            LEFT JOIN product_variants pv ON ep.variant_id = pv.id
            WHERE ep.entity_id = :entity_id AND ep.product_id = :product_id AND ep.variant_id IS NOT NULL
            ORDER BY ep.id DESC
        ");
        $stmt->execute([':entity_id' => $entityId, ':product_id' => $productId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create or Update
     */
    public function save(array $data): int
    {
        $isUpdate = !empty($data['id']);

        $params = [];
        foreach (self::ENTITY_PRODUCT_COLUMNS as $col) {
            if (array_key_exists($col, $data)) {
                $val = $data[$col];
                $params[':' . $col] = ($val === '' || $val === null) ? null : $val;
            }
        }

        if (empty($params[':entity_id']) || empty($params[':product_id'])) {
            throw new InvalidArgumentException("entity_id and product_id are required");
        }

        $variantId = isset($params[':variant_id']) && $params[':variant_id'] !== null ? (int)$params[':variant_id'] : null;
        $this->validateReferences((int)$params[':entity_id'], (int)$params[':product_id'], $variantId);

        if ($isUpdate) {
            $params[':id'] = (int)$data['id'];

            $setClauses = [];
            foreach (self::ENTITY_PRODUCT_COLUMNS as $col) {
                if (array_key_exists(':' . $col, $params)) {
                    $setClauses[] = "{$col} = :{$col}";
                }
            }

            $stmt = $this->pdo->prepare(
                "UPDATE entity_products SET " . implode(', ', $setClauses) . " WHERE id = :id"
            );
            $stmt->execute($params);
            return (int)$data['id'];
        }

        $columns = [];
        $placeholders = [];
        foreach (self::ENTITY_PRODUCT_COLUMNS as $col) {
            if (array_key_exists(':' . $col, $params)) {
                $columns[] = $col;
                $placeholders[] = ':' . $col;
            }
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO entity_products (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")"
        );
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Bulk save products for an entity (with optional pricing)
     */
    public function saveEntityProducts(int $entityId, int $tenantId, array $products): array
    {
        $this->pdo->beginTransaction();
        try {
            $savedIds = [];

            foreach ($products as $productData) {
                $productData['entity_id'] = $entityId;
                $productData['tenant_id'] = $tenantId;

                // Product-level records have no variant_id
                if (!isset($productData['variant_id'])) {
                    $existing = $this->findByEntityAndProduct($entityId, (int)$productData['product_id']);
                } else {
                    $existing = $this->findByEntityAndVariant($entityId, (int)$productData['variant_id']);
                }

                if ($existing) {
                    $productData['id'] = $existing['id'];
                }

                $savedIds[] = $this->save($productData);

                // Save entity-specific pricing if price is provided (product-level only)
                if (!isset($productData['variant_id']) && isset($productData['price']) && $productData['price'] !== '' && $productData['price'] !== null) {
                    $this->saveEntityProductPricing($entityId, (int)$productData['product_id'], $productData);
                }
            }

            $this->pdo->commit();
            return $savedIds;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Bulk save variants for an entity
     */
    public function saveEntityVariants(int $entityId, int $tenantId, array $variants): array
    {
        $this->pdo->beginTransaction();
        try {
            $savedIds = [];

            foreach ($variants as $variantData) {
                $variantData['entity_id'] = $entityId;
                $variantData['tenant_id'] = $tenantId;

                $existing = $this->findByEntityAndVariant($entityId, (int)$variantData['variant_id']);

                if ($existing) {
                    $variantData['id'] = $existing['id'];
                }

                $savedIds[] = $this->save($variantData);
            }

            $this->pdo->commit();
            return $savedIds;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Save or update entity-specific pricing in product_pricing table
     */
    private function saveEntityProductPricing(int $entityId, int $productId, array $data): void
    {
        // Check if entity-specific pricing already exists
        $stmt = $this->pdo->prepare(
            "SELECT id FROM product_pricing WHERE product_id = :product_id AND entity_id = :entity_id LIMIT 1"
        );
        $stmt->execute([':product_id' => $productId, ':entity_id' => $entityId]);
        $existingPricing = $stmt->fetch(PDO::FETCH_ASSOC);

        $price = $data['price'] ?? 0;
        $compareAtPrice = (isset($data['compare_at_price']) && $data['compare_at_price'] !== '') ? $data['compare_at_price'] : null;
        $costPrice = (isset($data['cost_price']) && $data['cost_price'] !== '') ? $data['cost_price'] : null;
        $currencyCode = $data['currency_code'] ?? 'SAR';
        $taxRate = (isset($data['tax_rate']) && $data['tax_rate'] !== '') ? $data['tax_rate'] : null;

        if ($existingPricing) {
            $stmt = $this->pdo->prepare(
                "UPDATE product_pricing SET price = ?, compare_at_price = ?, cost_price = ?,
                 currency_code = ?, tax_rate = ?, is_active = 1, updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?"
            );
            $stmt->execute([$price, $compareAtPrice, $costPrice, $currencyCode, $taxRate, $existingPricing['id']]);
        } else {
            $stmt = $this->pdo->prepare(
                "INSERT INTO product_pricing (product_id, entity_id, price, compare_at_price, cost_price,
                 currency_code, tax_rate, pricing_type, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'fixed', 1)"
            );
            $stmt->execute([$productId, $entityId, $price, $compareAtPrice, $costPrice, $currencyCode, $taxRate]);
        }
    }

    /**
     * Delete
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM entity_products WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Delete all products for an entity (product-level only)
     */
    public function deleteEntityProducts(int $entityId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM entity_products WHERE entity_id = :entity_id AND variant_id IS NULL");
        return $stmt->execute([':entity_id' => $entityId]);
    }

    /**
     * Delete all variants for an entity
     */
    public function deleteEntityVariants(int $entityId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM entity_products WHERE entity_id = :entity_id AND variant_id IS NOT NULL");
        return $stmt->execute([':entity_id' => $entityId]);
    }

    /**
     * Delete all variants for a specific entity product
     */
    public function deleteEntityProductVariants(int $entityId, int $productId): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM entity_products WHERE entity_id = :entity_id AND product_id = :product_id AND variant_id IS NOT NULL"
        );
        return $stmt->execute([':entity_id' => $entityId, ':product_id' => $productId]);
    }

    /**
     * Delete all records (products + variants) for an entity
     */
    public function deleteAllForEntity(int $entityId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM entity_products WHERE entity_id = :entity_id");
        return $stmt->execute([':entity_id' => $entityId]);
    }

    /**
     * Get statistics
     */
    public function getStatistics(): array
    {
        $stats = [];

        $stmt = $this->pdo->query("SELECT COUNT(*) FROM entity_products");
        $stats['total_records'] = (int)$stmt->fetchColumn();

        $stmt = $this->pdo->query("SELECT COUNT(DISTINCT entity_id) FROM entity_products");
        $stats['entities_with_products'] = (int)$stmt->fetchColumn();

        $stmt = $this->pdo->query("SELECT COUNT(DISTINCT product_id) FROM entity_products WHERE variant_id IS NULL");
        $stats['unique_products'] = (int)$stmt->fetchColumn();

        $stmt = $this->pdo->query("SELECT COUNT(DISTINCT variant_id) FROM entity_products WHERE variant_id IS NOT NULL");
        $stats['unique_variants'] = (int)$stmt->fetchColumn();

        $stmt = $this->pdo->query("SELECT COUNT(*) FROM entity_products WHERE is_active = 1");
        $stats['active_records'] = (int)$stmt->fetchColumn();

        $stmt = $this->pdo->query("SELECT COUNT(*) FROM entity_products WHERE is_featured = 1");
        $stats['featured_records'] = (int)$stmt->fetchColumn();

        return $stats;
    }

    /**
     * Validate entity, product, and optionally variant exist
     */
    private function validateReferences(int $entityId, int $productId, ?int $variantId = null): void
    {
        $stmt = $this->pdo->prepare("SELECT id FROM entities WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $entityId]);
        if (!$stmt->fetch()) {
            throw new RuntimeException("Entity not found");
        }

        $stmt = $this->pdo->prepare("SELECT id FROM products WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $productId]);
        if (!$stmt->fetch()) {
            throw new RuntimeException("Product not found");
        }

        if ($variantId !== null) {
            $stmt = $this->pdo->prepare("SELECT id FROM product_variants WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $variantId]);
            if (!$stmt->fetch()) {
                throw new RuntimeException("Variant not found");
            }
        }
    }
}
