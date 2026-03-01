<?php
declare(strict_types=1);

final class PdoCartItemsRepository
{
    private PDO $pdo;

    // الأعمدة المسموح بها للفرز
    private const ALLOWED_ORDER_BY = [
        'id','cart_id','product_id','product_variant_id','entity_id',
        'product_name','sku','quantity','unit_price','sale_price',
        'subtotal','total','added_at','updated_at'
    ];

    // الأعمدة القابلة للفلاتر
    private const FILTERABLE_COLUMNS = [
        'cart_id','product_id','product_variant_id','entity_id','sku'
    ];

    // الأعمدة المسموحة في جدول cart_items
    private const CART_ITEM_COLUMNS = [
        'cart_id','product_id','product_variant_id','entity_id',
        'product_name','sku','quantity','unit_price','sale_price',
        'discount_amount','tax_rate','tax_amount','subtotal','total',
        'currency_code','selected_attributes','special_instructions',
        'is_gift','gift_message'
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
        string $orderDir = 'DESC',
        string $lang = 'ar'
    ): array {
        $sql = "
            SELECT ci.*
            FROM cart_items ci
            INNER JOIN entities e ON ci.entity_id = e.id
            WHERE e.tenant_id = :tenant_id
        ";
        $params = [':tenant_id' => $tenantId];

        // تطبيق كل الفلاتر بشكل ديناميكي
        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if (in_array($col, ['sku'])) {
                    $sql .= " AND ci.{$col} LIKE :{$col}";
                    $params[":{$col}"] = '%' . $filters[$col] . '%';
                } else {
                    $sql .= " AND ci.{$col} = :{$col}";
                    $params[":{$col}"] = $filters[$col];
                }
            }
        }

        // الفرز
        $orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY ci.{$orderBy} {$orderDir}";

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
            SELECT COUNT(*) FROM cart_items ci
            INNER JOIN entities e ON ci.entity_id = e.id
            WHERE e.tenant_id = :tenant_id
        ";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if (in_array($col, ['sku'])) {
                    $sql .= " AND ci.{$col} LIKE :{$col}";
                    $params[":{$col}"] = '%' . $filters[$col] . '%';
                } else {
                    $sql .= " AND ci.{$col} = :{$col}";
                    $params[":{$col}"] = $filters[$col];
                }
            }
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    // ================================
    // Find by ID
    // ================================
    public function find(int $tenantId, int $id, string $lang = 'ar'): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT ci.*
            FROM cart_items ci
            INNER JOIN entities e ON ci.entity_id = e.id
            WHERE e.tenant_id = :tenant_id AND ci.id = :id
            LIMIT 1
        ");
        $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================================
    // Find by cart_id
    // ================================
    public function findByCart(int $tenantId, int $cartId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ci.*
            FROM cart_items ci
            INNER JOIN entities e ON ci.entity_id = e.id
            WHERE e.tenant_id = :tenant_id AND ci.cart_id = :cart_id
            ORDER BY ci.added_at DESC
        ");
        $stmt->execute([':tenant_id' => $tenantId, ':cart_id' => $cartId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================
    // Create / Update
    // ================================
    public function save(int $tenantId, array $data): int
    {
        $isUpdate = !empty($data['id']);

        // استخراج الأعمدة المسموح بها فقط من البيانات الواردة
        $params = [];
        foreach (self::CART_ITEM_COLUMNS as $col) {
            if (array_key_exists($col, $data)) {
                $val = $data[$col];
                $params[':' . $col] = ($val === '' || $val === null) ? null : $val;
            } else {
                $params[':' . $col] = null;
            }
        }

        // cart_id مطلوب (NOT NULL)
        if (empty($params[':cart_id'])) {
            throw new \InvalidArgumentException('cart_id is required');
        }

        // product_id مطلوب (NOT NULL)
        if (empty($params[':product_id'])) {
            throw new \InvalidArgumentException('product_id is required');
        }

        // entity_id مطلوب (NOT NULL)
        if (empty($params[':entity_id'])) {
            throw new \InvalidArgumentException('entity_id is required');
        }

        // product_name مطلوب (NOT NULL)
        if (empty($params[':product_name'])) {
            $params[':product_name'] = 'Unknown Product';
        }

        // sku مطلوب (NOT NULL)
        if (empty($params[':sku'])) {
            $params[':sku'] = 'SKU-' . strtoupper(bin2hex(random_bytes(4)));
        }

        // القيم الافتراضية
        if ($params[':quantity'] === null) {
            $params[':quantity'] = 1;
        }
        if ($params[':unit_price'] === null) {
            $params[':unit_price'] = '0.00';
        }
        if ($params[':discount_amount'] === null) {
            $params[':discount_amount'] = '0.00';
        }
        if ($params[':tax_rate'] === null) {
            $params[':tax_rate'] = '0.00';
        }
        if ($params[':tax_amount'] === null) {
            $params[':tax_amount'] = '0.00';
        }
        if ($params[':subtotal'] === null) {
            $params[':subtotal'] = '0.00';
        }
        if ($params[':total'] === null) {
            $params[':total'] = '0.00';
        }
        if ($params[':currency_code'] === null) {
            $params[':currency_code'] = 'SAR';
        }
        if ($params[':is_gift'] === null) {
            $params[':is_gift'] = 0;
        }

        if ($isUpdate) {
            $params[':id'] = (int)$data['id'];

            $stmt = $this->pdo->prepare("
                UPDATE cart_items SET
                    cart_id = :cart_id,
                    product_id = :product_id,
                    product_variant_id = :product_variant_id,
                    entity_id = :entity_id,
                    product_name = :product_name,
                    sku = :sku,
                    quantity = :quantity,
                    unit_price = :unit_price,
                    sale_price = :sale_price,
                    discount_amount = :discount_amount,
                    tax_rate = :tax_rate,
                    tax_amount = :tax_amount,
                    subtotal = :subtotal,
                    total = :total,
                    currency_code = :currency_code,
                    selected_attributes = :selected_attributes,
                    special_instructions = :special_instructions,
                    is_gift = :is_gift,
                    gift_message = :gift_message,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->execute($params);
            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO cart_items (
                cart_id, product_id, product_variant_id, entity_id,
                product_name, sku, quantity, unit_price, sale_price,
                discount_amount, tax_rate, tax_amount, subtotal, total,
                currency_code, selected_attributes, special_instructions,
                is_gift, gift_message
            ) VALUES (
                :cart_id, :product_id, :product_variant_id, :entity_id,
                :product_name, :sku, :quantity, :unit_price, :sale_price,
                :discount_amount, :tax_rate, :tax_amount, :subtotal, :total,
                :currency_code, :selected_attributes, :special_instructions,
                :is_gift, :gift_message
            )
        ");
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    // ================================
    // Delete
    // ================================
    public function delete(int $tenantId, int $id): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE ci FROM cart_items ci
            INNER JOIN entities e ON ci.entity_id = e.id
            WHERE e.tenant_id = :tenant_id AND ci.id = :id
        ");
        return $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
    }
}
