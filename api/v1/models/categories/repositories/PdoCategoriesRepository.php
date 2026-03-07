<?php
declare(strict_types=1);

/**
 * api/v1/models/categories/repositories/PdoCategoriesRepository.php
 * Category data access layer.
 * List only returns categories that have at least one active product for the tenant,
 * matching the frontend entity.php and public API behaviour.
 */
final class PdoCategoriesRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * List categories for a tenant.
     * When $filterByProducts is true (default), only returns categories that have
     * at least one active product for the tenant (mirrors entity.php behaviour).
     */
    public function list(
        int $tenantId,
        int $limit = 500,
        int $offset = 0,
        string $lang = 'ar',
        ?int $entityId = null,   // kept for API compat, currently unused (products have no entity_id)
        ?int $isActive = null
    ): array {
        $params = [':tenant_id' => $tenantId, ':lang' => $lang];
        $sql = "
            SELECT DISTINCT c.id, c.tenant_id, c.parent_id, c.slug, c.image_url,
                   c.is_active, c.is_featured, c.sort_order, c.created_at, c.updated_at,
                   COALESCE(ct.name, c.name, c.slug) AS name,
                   COALESCE(ct.description, c.description, '') AS description
              FROM categories c
         LEFT JOIN category_translations ct
                ON ct.category_id = c.id AND ct.language_code = :lang
             WHERE c.tenant_id = :tenant_id";

        if ($isActive !== null) {
            $sql .= ' AND c.is_active = :is_active';
            $params[':is_active'] = $isActive;
        }

        // Only return categories that have at least one active product for this tenant
        // (mirrors public.php entity categories query and entity.php frontend)
        $sql .= "
              AND EXISTS (
                  SELECT 1 FROM product_categories pc
                    JOIN products p ON p.id = pc.product_id
                   WHERE pc.category_id = c.id
                     AND p.tenant_id = :tenant_id
                     AND p.is_active = 1
              )";

        $sql .= ' ORDER BY c.sort_order ASC, c.id ASC';

        if ($limit > 0) {
            $sql .= ' LIMIT :limit OFFSET :offset';
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        if ($limit > 0) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(int $tenantId, ?int $isActive = null, ?int $entityId = null): int
    {
        $params = [':tenant_id' => $tenantId];
        $sql = "SELECT COUNT(DISTINCT c.id) FROM categories c WHERE c.tenant_id = :tenant_id";

        if ($isActive !== null) {
            $sql .= ' AND c.is_active = :is_active';
            $params[':is_active'] = $isActive;
        }

        // Count only categories that have at least one active product for this tenant
        $sql .= "
              AND EXISTS (
                  SELECT 1 FROM product_categories pc
                    JOIN products p ON p.id = pc.product_id
                   WHERE pc.category_id = c.id
                     AND p.tenant_id = :tenant_id
                     AND p.is_active = 1
              )";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    public function find(int $tenantId, int $id, string $lang = 'ar'): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.id, c.tenant_id, c.parent_id, c.slug, c.image_url,
                   c.is_active, c.is_featured, c.sort_order, c.created_at, c.updated_at,
                   COALESCE(ct.name, c.name, c.slug) AS name,
                   COALESCE(ct.description, c.description, '') AS description
              FROM categories c
         LEFT JOIN category_translations ct
                ON ct.category_id = c.id AND ct.language_code = :lang
             WHERE c.tenant_id = :tenant_id AND c.id = :id
             LIMIT 1");
        $stmt->execute([':tenant_id' => $tenantId, ':id' => $id, ':lang' => $lang]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findIdBySlug(int $tenantId, string $slug): ?int
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM categories WHERE tenant_id = :t AND slug = :s LIMIT 1'
        );
        $stmt->execute([':t' => $tenantId, ':s' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['id'] : null;
    }

    public function getActive(int $tenantId, string $lang = 'ar'): array
    {
        return $this->list($tenantId, 500, 0, $lang, null, 1);
    }

    public function getFeatured(int $tenantId, string $lang = 'ar'): array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.id, c.tenant_id, c.parent_id, c.slug, c.image_url,
                   c.is_active, c.is_featured, c.sort_order,
                   COALESCE(ct.name, c.name, c.slug) AS name
              FROM categories c
         LEFT JOIN category_translations ct
                ON ct.category_id = c.id AND ct.language_code = :lang
             WHERE c.tenant_id = :tenant_id AND c.is_active = 1 AND c.is_featured = 1
             ORDER BY c.sort_order ASC, c.id ASC LIMIT 50");
        $stmt->execute([':tenant_id' => $tenantId, ':lang' => $lang]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function tree(int $tenantId, string $lang = 'ar'): array
    {
        $flat = $this->list($tenantId, 500, 0, $lang);
        return $this->buildTree($flat);
    }

    /** Build hierarchical tree from flat list */
    public function buildTree(array $flat): array
    {
        $byId = [];
        foreach ($flat as $row) {
            $row['children'] = [];
            $byId[$row['id']] = $row;
        }
        $roots = [];
        foreach ($byId as $id => &$row) {
            $pid = (int)($row['parent_id'] ?? 0);
            if ($pid && isset($byId[$pid])) {
                $byId[$pid]['children'][] = &$row;
            } else {
                $roots[] = &$row;
            }
        }
        unset($row);
        return $roots;
    }

    public function create(int $tenantId, array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO categories (tenant_id, parent_id, slug, name, description, image_url, is_active, is_featured, sort_order)
            VALUES (:tenant_id, :parent_id, :slug, :name, :description, :image_url, :is_active, :is_featured, :sort_order)");
        $stmt->execute([
            ':tenant_id'  => $tenantId,
            ':parent_id'  => $data['parent_id'] ?? null,
            ':slug'       => $data['slug'] ?? '',
            ':name'       => $data['name'] ?? '',
            ':description'=> $data['description'] ?? '',
            ':image_url'  => $data['image_url'] ?? null,
            ':is_active'  => (int)($data['is_active'] ?? 1),
            ':is_featured'=> (int)($data['is_featured'] ?? 0),
            ':sort_order' => (int)($data['sort_order'] ?? 0),
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $tenantId, int $id, array $data): bool
    {
        $fields = [];
        $params = [':tenant_id' => $tenantId, ':id' => $id];
        foreach (['parent_id','slug','name','description','image_url','is_active','is_featured','sort_order'] as $col) {
            if (array_key_exists($col, $data)) {
                $fields[] = "{$col} = :{$col}";
                $params[":{$col}"] = $data[$col];
            }
        }
        if (!$fields) return false;
        $stmt = $this->pdo->prepare(
            'UPDATE categories SET ' . implode(', ', $fields) . ' WHERE tenant_id = :tenant_id AND id = :id'
        );
        return $stmt->execute($params);
    }

    public function delete(int $tenantId, int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM categories WHERE tenant_id = :tenant_id AND id = :id'
        );
        return $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
    }
}
