<?php
declare(strict_types=1);

/**
 * api/v1/models/categories/repositories/PdoCategoriesRepository.php
 * Category data access layer.
 * List only returns categories that have at least one active product for the tenant,
 * matching the frontend entity.php and public API behaviour.
 * When $entityId is provided and the entity has rows in entity_categories, only
 * those categories are returned. If the entity has no rows in entity_categories
 * the full tenant category list is returned (graceful fallback).
 */
final class PdoCategoriesRepository
{
    private PDO $pdo;
    private array $tenantCategoriesCache = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Check whether the entity_categories table exists (graceful fallback when
     * the migration has not been run yet).
     */
    private function entityCategoriesTableExists(): bool
    {
        try {
            $this->pdo->query('SELECT 1 FROM entity_categories LIMIT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Check whether the tenant_categories table exists and has active rows for the tenant.
     * Returns true when the table is present and the tenant has at least one active assignment.
     * Result is cached per tenant_id for the lifetime of this repository instance.
     */
    private function tenantCategoriesHasRows(int $tenantId): bool
    {
        if (array_key_exists($tenantId, $this->tenantCategoriesCache)) {
            return $this->tenantCategoriesCache[$tenantId];
        }
        try {
            $stmt = $this->pdo->prepare(
                'SELECT 1 FROM tenant_categories WHERE tenant_id = :tid AND is_active = 1 LIMIT 1'
            );
            $stmt->execute([':tid' => $tenantId]);
            $result = $stmt->fetchColumn() !== false;
        } catch (\Throwable $e) {
            $result = false;
        }
        $this->tenantCategoriesCache[$tenantId] = $result;
        return $result;
    }

    /**
     * List categories for a tenant.
     * Only returns categories that have at least one active product for the tenant.
     * When tenant_categories has active rows for the tenant, only those categories
     * are returned and ordered by tenant_categories.sort_order.
     * When $entityId is provided and the entity has category assignments in
     * entity_categories, only those categories are returned (narrowing further).
     */
    public function list(
        int $tenantId,
        int $limit = 500,
        int $offset = 0,
        string $lang = 'ar',
        ?int $entityId = null,
        ?int $isActive = null
    ): array {
        $params = [':tenant_id' => $tenantId, ':lang' => $lang];

        // Determine whether to use tenant_categories for filtering and ordering.
        $useTenantCategories = $this->tenantCategoriesHasRows($tenantId);

        if ($useTenantCategories) {
            // JOIN tenant_categories so we can filter and order by its sort_order.
            $sql = "
                SELECT DISTINCT c.id, c.tenant_id, c.parent_id, c.slug, c.image_url,
                       c.is_active, c.is_featured, c.sort_order, c.created_at, c.updated_at,
                       COALESCE(ct.name, c.name, c.slug) AS name,
                       COALESCE(ct.description, c.description, '') AS description,
                       COALESCE(tc.sort_order, c.sort_order) AS tc_sort_order
                  FROM categories c
             LEFT JOIN category_translations ct
                    ON ct.category_id = c.id AND ct.language_code = :lang
             INNER JOIN tenant_categories tc
                    ON tc.category_id = c.id AND tc.tenant_id = :tenant_id AND tc.is_active = 1
                 WHERE c.tenant_id = :tenant_id";
        } else {
            $sql = "
                SELECT DISTINCT c.id, c.tenant_id, c.parent_id, c.slug, c.image_url,
                       c.is_active, c.is_featured, c.sort_order, c.created_at, c.updated_at,
                       COALESCE(ct.name, c.name, c.slug) AS name,
                       COALESCE(ct.description, c.description, '') AS description
                  FROM categories c
             LEFT JOIN category_translations ct
                    ON ct.category_id = c.id AND ct.language_code = :lang
                 WHERE c.tenant_id = :tenant_id";
        }

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

        // Filter by entity when entity_categories assignments exist for this entity.
        // Falls back to showing all tenant categories when no assignments are configured.
        if ($entityId !== null && $this->entityCategoriesTableExists()) {
            $countStmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM entity_categories WHERE entity_id = :eid AND is_active = 1'
            );
            $countStmt->execute([':eid' => $entityId]);
            if ((int)$countStmt->fetchColumn() > 0) {
                $sql .= '
              AND EXISTS (
                  SELECT 1 FROM entity_categories ec
                   WHERE ec.category_id = c.id
                     AND ec.entity_id = :entity_id
                     AND ec.is_active = 1
              )';
                $params[':entity_id'] = $entityId;
            }
        }

        // Order by tenant_categories.sort_order when available, else by categories.sort_order
        if ($useTenantCategories) {
            $sql .= ' ORDER BY tc_sort_order ASC, c.id ASC';
        } else {
            $sql .= ' ORDER BY c.sort_order ASC, c.id ASC';
        }

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