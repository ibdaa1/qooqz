<?php
declare(strict_types=1);

final class PdoCartsRepository
{
    private PDO $pdo;

    // الأعمدة المسموح بها للفرز
    private const ALLOWED_ORDER_BY = [
        'id','entity_id','user_id','session_id','total_items',
        'subtotal','total_amount','status','currency_code',
        'last_activity_at','expires_at','created_at','updated_at'
    ];

    // الأعمدة القابلة للفلاتر
    private const FILTERABLE_COLUMNS = [
        'user_id','session_id','device_id','status','entity_id','currency_code','coupon_code'
    ];

    // الأعمدة المسموحة في جدول carts
    private const CART_COLUMNS = [
        'entity_id','user_id','session_id','device_id','ip_address',
        'total_items','subtotal','tax_amount','shipping_cost',
        'discount_amount','total_amount','currency_code','coupon_code',
        'discount_id','items','loyalty_points_used','status',
        'last_activity_at','converted_to_order_id','expires_at'
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
            SELECT c.*,
                   e.store_name AS entity_name
            FROM carts c
            LEFT JOIN entities e ON c.entity_id = e.id
            WHERE e.tenant_id = :tenant_id
        ";
        $params = [':tenant_id' => $tenantId];

        // تطبيق كل الفلاتر بشكل ديناميكي
        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if (in_array($col, ['session_id','device_id','coupon_code'])) {
                    $sql .= " AND c.{$col} LIKE :{$col}";
                    $params[":{$col}"] = '%' . $filters[$col] . '%';
                } else {
                    $sql .= " AND c.{$col} = :{$col}";
                    $params[":{$col}"] = $filters[$col];
                }
            }
        }

        // الفرز
        $orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY c.{$orderBy} {$orderDir}";

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
            SELECT COUNT(*) FROM carts c
            LEFT JOIN entities e ON c.entity_id = e.id
            WHERE e.tenant_id = :tenant_id
        ";
        $params = [':tenant_id' => $tenantId];

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if (in_array($col, ['session_id','device_id','coupon_code'])) {
                    $sql .= " AND c.{$col} LIKE :{$col}";
                    $params[":{$col}"] = '%' . $filters[$col] . '%';
                } else {
                    $sql .= " AND c.{$col} = :{$col}";
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
            SELECT c.*,
                   e.store_name AS entity_name
            FROM carts c
            LEFT JOIN entities e ON c.entity_id = e.id
            WHERE e.tenant_id = :tenant_id AND c.id = :id
            LIMIT 1
        ");
        $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================================
    // Find by session_id and entity_id
    // ================================
    public function findBySession(int $tenantId, string $sessionId, int $entityId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.*,
                   e.store_name AS entity_name
            FROM carts c
            LEFT JOIN entities e ON c.entity_id = e.id
            WHERE e.tenant_id = :tenant_id
              AND c.session_id = :session_id
              AND c.entity_id = :entity_id
              AND c.status = 'active'
            ORDER BY c.last_activity_at DESC
            LIMIT 1
        ");
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':session_id' => $sessionId,
            ':entity_id' => $entityId
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================================
    // Find by user_id and entity_id
    // ================================
    public function findByUser(int $tenantId, int $userId, int $entityId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.*,
                   e.store_name AS entity_name
            FROM carts c
            LEFT JOIN entities e ON c.entity_id = e.id
            WHERE e.tenant_id = :tenant_id
              AND c.user_id = :user_id
              AND c.entity_id = :entity_id
              AND c.status = 'active'
            ORDER BY c.last_activity_at DESC
            LIMIT 1
        ");
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':user_id' => $userId,
            ':entity_id' => $entityId
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================================
    // Create / Update
    // ================================
    public function save(int $tenantId, array $data): int
    {
        $isUpdate = !empty($data['id']);

        // استخراج الأعمدة المسموح بها فقط من البيانات الواردة
        $params = [];
        foreach (self::CART_COLUMNS as $col) {
            if (array_key_exists($col, $data)) {
                $val = $data[$col];
                $params[':' . $col] = ($val === '' || $val === null) ? null : $val;
            } else {
                $params[':' . $col] = null;
            }
        }

        // entity_id مطلوب (NOT NULL) - تعيين قيمة افتراضية 1
        if (empty($params[':entity_id'])) {
            $params[':entity_id'] = 1;
        }

        // القيم الافتراضية
        if ($params[':currency_code'] === null) {
            $params[':currency_code'] = 'SAR';
        }
        if ($params[':status'] === null) {
            $params[':status'] = 'active';
        }
        if ($params[':total_items'] === null) {
            $params[':total_items'] = 0;
        }
        if ($params[':subtotal'] === null) {
            $params[':subtotal'] = '0.00';
        }
        if ($params[':tax_amount'] === null) {
            $params[':tax_amount'] = '0.00';
        }
        if ($params[':shipping_cost'] === null) {
            $params[':shipping_cost'] = '0.00';
        }
        if ($params[':discount_amount'] === null) {
            $params[':discount_amount'] = '0.00';
        }
        if ($params[':total_amount'] === null) {
            $params[':total_amount'] = '0.00';
        }
        if ($params[':loyalty_points_used'] === null) {
            $params[':loyalty_points_used'] = 0;
        }

        if ($isUpdate) {
            $params[':id'] = (int)$data['id'];

            $stmt = $this->pdo->prepare("
                UPDATE carts SET
                    entity_id = :entity_id,
                    user_id = :user_id,
                    session_id = :session_id,
                    device_id = :device_id,
                    ip_address = :ip_address,
                    total_items = :total_items,
                    subtotal = :subtotal,
                    tax_amount = :tax_amount,
                    shipping_cost = :shipping_cost,
                    discount_amount = :discount_amount,
                    total_amount = :total_amount,
                    currency_code = :currency_code,
                    coupon_code = :coupon_code,
                    discount_id = :discount_id,
                    items = :items,
                    loyalty_points_used = :loyalty_points_used,
                    status = :status,
                    last_activity_at = CURRENT_TIMESTAMP,
                    converted_to_order_id = :converted_to_order_id,
                    expires_at = :expires_at,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->execute($params);
            return (int)$data['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO carts (
                entity_id, user_id, session_id, device_id, ip_address,
                total_items, subtotal, tax_amount, shipping_cost,
                discount_amount, total_amount, currency_code, coupon_code,
                discount_id, items, loyalty_points_used, status,
                last_activity_at, converted_to_order_id, expires_at
            ) VALUES (
                :entity_id, :user_id, :session_id, :device_id, :ip_address,
                :total_items, :subtotal, :tax_amount, :shipping_cost,
                :discount_amount, :total_amount, :currency_code, :coupon_code,
                :discount_id, :items, :loyalty_points_used, :status,
                CURRENT_TIMESTAMP, :converted_to_order_id, :expires_at
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
            DELETE c FROM carts c
            INNER JOIN entities e ON c.entity_id = e.id
            WHERE e.tenant_id = :tenant_id AND c.id = :id
        ");
        return $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
    }
}
