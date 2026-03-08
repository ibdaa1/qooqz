<?php
/**
 * TORO — v1/modules/Products/Repositories/PdoProductsRepository.php
 */
declare(strict_types=1);

final class PdoProductsRepository implements ProductsRepositoryInterface
{
    public function __construct(private readonly \PDO $pdo) {}

    // ── List ───────────────────────────────────────────────────
    public function findAll(array $filters = []): array
    {
        $lang       = $filters['lang']        ?? null;
        $brandId    = $filters['brand_id']    ?? null;
        $categoryId = $filters['category_id'] ?? null;
        $type       = $filters['type']        ?? null;
        $isActive   = $filters['is_active']   ?? null;
        $isFeatured = $filters['is_featured'] ?? null;
        $search     = $filters['search']      ?? null;
        $limit      = max(1, min((int)($filters['limit'] ?? 50), 200));
        $offset     = max(0, (int)($filters['offset'] ?? 0));

        $langId = $lang ? $this->resolveLanguageId($lang) : null;

        $sql = "
            SELECT
                p.id, p.sku, p.brand_id, p.category_id, p.type,
                p.base_price, p.sale_price, p.stock_qty, p.weight_grams,
                p.thumbnail, p.is_featured, p.is_active, p.sort_order, p.created_at, p.updated_at,
                pt.name, pt.short_desc, pt.description,
                l.code AS lang_code
            FROM products p
            LEFT JOIN product_translations pt
                ON pt.product_id = p.id
                AND pt.language_id = COALESCE(:lang_id, (SELECT id FROM languages WHERE is_default = 1 LIMIT 1))
            LEFT JOIN languages l ON l.id = pt.language_id
            WHERE p.deleted_at IS NULL
        ";

        $params = [':lang_id' => $langId];

        if ($brandId !== null) {
            $sql .= ' AND p.brand_id = :brand_id';
            $params[':brand_id'] = (int)$brandId;
        }

        if ($categoryId !== null) {
            $sql .= ' AND p.category_id = :category_id';
            $params[':category_id'] = (int)$categoryId;
        }

        if ($type !== null) {
            $sql .= ' AND p.type = :type';
            $params[':type'] = $type;
        }

        if ($isActive !== null) {
            $sql .= ' AND p.is_active = :is_active';
            $params[':is_active'] = (int)(bool)$isActive;
        }

        if ($isFeatured !== null) {
            $sql .= ' AND p.is_featured = :is_featured';
            $params[':is_featured'] = (int)(bool)$isFeatured;
        }

        if ($search !== null) {
            $sql .= ' AND (p.sku LIKE :search1 OR pt.name LIKE :search2)';
            $params[':search1'] = '%' . $search . '%';
            $params[':search2'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY p.sort_order ASC, p.id DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, is_null($val) ? \PDO::PARAM_NULL : (is_int($val) ? \PDO::PARAM_INT : \PDO::PARAM_STR));
        }
        $stmt->bindValue(':limit',  $limit,  \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    // ── Count ──────────────────────────────────────────────────
    public function countAll(array $filters = []): int
    {
        $brandId    = $filters['brand_id']    ?? null;
        $categoryId = $filters['category_id'] ?? null;
        $type       = $filters['type']        ?? null;
        $isActive   = $filters['is_active']   ?? null;
        $isFeatured = $filters['is_featured'] ?? null;
        $search     = $filters['search']      ?? null;
        $lang       = $filters['lang']        ?? null;
        $langId     = $lang ? $this->resolveLanguageId($lang) : null;

        // Always use LEFT JOIN so we can filter by translated name or other fields consistently
        $sql = "
            SELECT COUNT(DISTINCT p.id)
            FROM products p
            LEFT JOIN product_translations pt
                ON pt.product_id = p.id
                AND pt.language_id = COALESCE(:lang_id, (SELECT id FROM languages WHERE is_default = 1 LIMIT 1))
            WHERE p.deleted_at IS NULL
        ";
        $params = [':lang_id' => $langId];

        if ($brandId !== null) {
            $sql .= ' AND p.brand_id = :brand_id';
            $params[':brand_id'] = (int)$brandId;
        }

        if ($categoryId !== null) {
            $sql .= ' AND p.category_id = :category_id';
            $params[':category_id'] = (int)$categoryId;
        }

        if ($type !== null) {
            $sql .= ' AND p.type = :type';
            $params[':type'] = $type;
        }

        if ($isActive !== null) {
            $sql .= ' AND p.is_active = :is_active';
            $params[':is_active'] = (int)(bool)$isActive;
        }

        if ($isFeatured !== null) {
            $sql .= ' AND p.is_featured = :is_featured';
            $params[':is_featured'] = (int)(bool)$isFeatured;
        }

        if ($search !== null) {
            $sql .= ' AND (p.sku LIKE :search1 OR pt.name LIKE :search2)';
            $params[':search1'] = '%' . $search . '%';
            $params[':search2'] = '%' . $search . '%';
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, is_null($val) ? \PDO::PARAM_NULL : (is_int($val) ? \PDO::PARAM_INT : \PDO::PARAM_STR));
        }
        $stmt->execute();

        return (int)$stmt->fetchColumn();
    }

    // ── Single ─────────────────────────────────────────────────
    public function findById(int $id, ?string $lang = null): ?array
    {
        $langId = $lang ? $this->resolveLanguageId($lang) : null;

        $stmt = $this->pdo->prepare("
            SELECT
                p.id, p.sku, p.brand_id, p.category_id, p.type,
                p.base_price, p.sale_price, p.stock_qty, p.weight_grams,
                p.thumbnail, p.is_featured, p.is_active, p.sort_order, p.created_at, p.updated_at,
                pt.name, pt.short_desc, pt.description, pt.ingredients, pt.how_to_use,
                pt.meta_title, pt.meta_desc,
                l.code AS lang_code
            FROM products p
            LEFT JOIN product_translations pt
                ON pt.product_id = p.id
                AND pt.language_id = COALESCE(:lang_id, (SELECT id FROM languages WHERE is_default = 1 LIMIT 1))
            LEFT JOIN languages l ON l.id = pt.language_id
            WHERE p.id = :id AND p.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->bindValue(':lang_id', $langId, is_null($langId) ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch() ?: null;
    }

    // ── Single by SKU ──────────────────────────────────────────
    public function findBySku(string $sku, ?string $lang = null): ?array
    {
        $langId = $lang ? $this->resolveLanguageId($lang) : null;

        $stmt = $this->pdo->prepare("
            SELECT
                p.id, p.sku, p.brand_id, p.category_id, p.type,
                p.base_price, p.sale_price, p.stock_qty, p.weight_grams,
                p.thumbnail, p.is_featured, p.is_active, p.sort_order, p.created_at, p.updated_at,
                pt.name, pt.short_desc, pt.description, pt.ingredients, pt.how_to_use,
                pt.meta_title, pt.meta_desc,
                l.code AS lang_code
            FROM products p
            LEFT JOIN product_translations pt
                ON pt.product_id = p.id
                AND pt.language_id = COALESCE(:lang_id, (SELECT id FROM languages WHERE is_default = 1 LIMIT 1))
            LEFT JOIN languages l ON l.id = pt.language_id
            WHERE p.sku = :sku AND p.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->bindValue(':lang_id', $langId, is_null($langId) ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $stmt->bindValue(':sku', $sku, \PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch() ?: null;
    }

    // ── Create ─────────────────────────────────────────────────
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO products
                (sku, brand_id, category_id, type, base_price, sale_price, stock_qty, weight_grams, thumbnail, is_featured, is_active, sort_order)
            VALUES
                (:sku, :brand_id, :category_id, :type, :base_price, :sale_price, :stock_qty, :weight_grams, :thumbnail, :is_featured, :is_active, :sort_order)
        ");
        $stmt->execute([
            ':sku'          => $data['sku'],
            ':brand_id'     => $data['brand_id'],
            ':category_id'  => $data['category_id']  ?? null,
            ':type'         => $data['type']          ?? 'simple',
            ':base_price'   => $data['base_price']    ?? 0,
            ':sale_price'   => $data['sale_price']    ?? null,
            ':stock_qty'    => $data['stock_qty']     ?? 0,
            ':weight_grams' => $data['weight_grams']  ?? null,
            ':thumbnail'    => $data['thumbnail']     ?? null,
            ':is_featured'  => (int)($data['is_featured'] ?? 0),
            ':is_active'    => (int)($data['is_active']   ?? 1),
            ':sort_order'   => $data['sort_order']    ?? 0,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    // ── Update ─────────────────────────────────────────────────
    public function update(int $id, array $data): bool
    {
        if (empty($data)) return false;

        $allowed = ['sku', 'brand_id', 'category_id', 'type', 'base_price', 'sale_price', 'stock_qty', 'weight_grams', 'thumbnail', 'is_featured', 'is_active', 'sort_order'];
        $sets    = [];
        $params  = [':__id' => $id];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[]            = "`{$col}` = :{$col}";
                $params[":{$col}"] = $data[$col];
            }
        }

        if (empty($sets)) return false;

        return $this->pdo->prepare(
            'UPDATE products SET ' . implode(', ', $sets) . ' WHERE id = :__id AND deleted_at IS NULL'
        )->execute($params);
    }

    // ── Hard delete ────────────────────────────────────────────
    public function delete(int $id): bool
    {
        return $this->pdo->prepare('DELETE FROM products WHERE id = :id')
            ->execute([':id' => $id]);
    }

    // ── Soft delete ────────────────────────────────────────────
    public function softDelete(int $id): bool
    {
        return $this->pdo->prepare(
            'UPDATE products SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL'
        )->execute([':id' => $id]);
    }

    // ── Translations ───────────────────────────────────────────
    public function upsertTranslation(int $productId, int $languageId, array $data): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO product_translations
                (product_id, language_id, name, short_desc, description, ingredients, how_to_use, meta_title, meta_desc)
            VALUES
                (:product_id, :language_id, :name, :short_desc, :description, :ingredients, :how_to_use, :meta_title, :meta_desc)
            AS new_row
            ON DUPLICATE KEY UPDATE
                name        = new_row.name,
                short_desc  = new_row.short_desc,
                description = new_row.description,
                ingredients = new_row.ingredients,
                how_to_use  = new_row.how_to_use,
                meta_title  = new_row.meta_title,
                meta_desc   = new_row.meta_desc
        ");
        return $stmt->execute([
            ':product_id'  => $productId,
            ':language_id' => $languageId,
            ':name'        => $data['name'],
            ':short_desc'  => $data['short_desc']  ?? null,
            ':description' => $data['description'] ?? null,
            ':ingredients' => $data['ingredients'] ?? null,
            ':how_to_use'  => $data['how_to_use']  ?? null,
            ':meta_title'  => $data['meta_title']  ?? null,
            ':meta_desc'   => $data['meta_desc']   ?? null,
        ]);
    }

    public function getTranslations(int $productId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT pt.*, l.code AS lang_code, l.name AS lang_name
            FROM product_translations pt
            JOIN languages l ON l.id = pt.language_id
            WHERE pt.product_id = :product_id
            ORDER BY l.sort_order ASC
        ");
        $stmt->execute([':product_id' => $productId]);
        return $stmt->fetchAll();
    }

    // ── Images via unified images table ───────────────────────
    public function getImages(int $productId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT i.*, it.code AS image_type_code, it.name AS image_type_name
            FROM images i
            LEFT JOIN image_types it ON it.id = i.image_type_id
            WHERE i.owner_id = :product_id
            ORDER BY i.sort_order ASC, i.id ASC
        ");
        $stmt->execute([':product_id' => $productId]);
        return $stmt->fetchAll();
    }

    // ── Language helpers ───────────────────────────────────────
    public function resolveLanguageId(string $code): ?int
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM languages WHERE code = :code AND is_active = 1 LIMIT 1'
        );
        $stmt->execute([':code' => $code]);
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : null;
    }

    public function getDefaultLanguageId(): int
    {
        $id = $this->pdo->query('SELECT id FROM languages WHERE is_default = 1 LIMIT 1')->fetchColumn();
        return $id ? (int)$id : 1;
    }
}
