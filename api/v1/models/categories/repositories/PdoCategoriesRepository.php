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
     * Optional: $parentId filters by parent, $featuredOnly restricts to is_featured=1,
     * $search does a LIKE search on category name/slug.
     */
    public function list(
        int $tenantId,
        int $limit = 500,
        int $offset = 0,
        string $lang = 'ar',
        ?int $entityId = null,
        ?int $isActive = null,
        ?int $parentId = null,
        bool $featuredOnly = false,
        ?string $search = null
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

        if ($featuredOnly) {
            $sql .= ' AND c.is_featured = 1';
        }

        if ($parentId !== null) {
            $sql .= ' AND c.parent_id = :parent_id';
            $params[':parent_id'] = $parentId;
        }

        if ($search !== null && $search !== '') {
            $sql .= " AND (COALESCE(ct.name, c.name) LIKE :search OR c.slug LIKE :search)";
            $params[':search'] = '%' . $search . '%';
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

    public function count(
        int $tenantId,
        ?int $isActive = null,
        ?int $entityId = null,
        ?int $parentId = null,
        bool $featuredOnly = false,
        ?string $search = null,
        string $lang = 'ar'
    ): int {
        $params = [':tenant_id' => $tenantId];
        $sql = "SELECT COUNT(DISTINCT c.id)
                  FROM categories c
             LEFT JOIN category_translations ct
                    ON ct.category_id = c.id AND ct.language_code = :lang
                 WHERE c.tenant_id = :tenant_id";
        $params[':lang'] = $lang;

        if ($isActive !== null) {
            $sql .= ' AND c.is_active = :is_active';
            $params[':is_active'] = $isActive;
        }

        if ($featuredOnly) {
            $sql .= ' AND c.is_featured = 1';
        }

        if ($parentId !== null) {
            $sql .= ' AND c.parent_id = :parent_id';
            $params[':parent_id'] = $parentId;
        }

        if ($search !== null && $search !== '') {
            $sql .= " AND (COALESCE(ct.name, c.name) LIKE :search OR c.slug LIKE :search)";
            $params[':search'] = '%' . $search . '%';
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

    public function delete(int $tenantId, int $id, ?int $userId = null): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM categories WHERE tenant_id = :tenant_id AND id = :id'
        );
        return $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
    }

    // ──────────────────────────────────────────────────────────────
    // Alias / compat methods used by CategoriesService
    // ──────────────────────────────────────────────────────────────

    /** Alias: all() → list() (used by CategoriesService::list() and getCategoryTree()) */
    public function all(
        int $tenantId,
        ?int $parentId = null,
        bool $featuredOnly = false,
        string $lang = 'ar',
        ?string $search = null,
        $isActive = null,
        int $limit = 500,
        int $offset = 0
    ): array {
        $isActiveInt = $isActive !== null ? (int)(bool)$isActive : null;
        return $this->list($tenantId, $limit, $offset, $lang, null, $isActiveInt, $parentId, $featuredOnly, $search);
    }

    /** Alias: countAll() → count() (used by CategoriesService::list()) */
    public function countAll(int $tenantId, array $filters = []): int
    {
        $isActive    = isset($filters['is_active']) ? (int)(bool)$filters['is_active'] : null;
        $entityId    = isset($filters['entity_id']) && is_numeric($filters['entity_id']) ? (int)$filters['entity_id'] : null;
        $parentId    = isset($filters['parent_id']) && $filters['parent_id'] !== '' ? (int)$filters['parent_id'] : null;
        $featuredOnly = isset($filters['is_featured']) && in_array($filters['is_featured'], [1, '1', true, 'true'], true);
        $search      = isset($filters['search']) && $filters['search'] !== '' ? (string)$filters['search'] : null;
        $lang        = $filters['lang'] ?? 'ar';
        return $this->count($tenantId, $isActive, $entityId, $parentId, $featuredOnly, $search, $lang);
    }

    /** Alias: findById() → find() (used by CategoriesService) */
    public function findById(int $tenantId, int $id, string $lang = 'ar'): ?array
    {
        return $this->find($tenantId, $id, $lang);
    }

    /** Alias: getActiveCategories() → getActive() */
    public function getActiveCategories(int $tenantId, string $lang = 'ar'): array
    {
        return $this->getActive($tenantId, $lang);
    }

    /** Alias: getFeaturedCategories() → getFeatured() */
    public function getFeaturedCategories(int $tenantId, string $lang = 'ar'): array
    {
        return $this->getFeatured($tenantId, $lang);
    }

    /** Expose PDO for service-level bulk operations */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /** Check if a slug already exists for this tenant (optionally excluding a specific id) */
    public function slugExists(int $tenantId, string $slug, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $stmt = $this->pdo->prepare(
                'SELECT 1 FROM categories WHERE tenant_id = :t AND slug = :s AND id != :eid LIMIT 1'
            );
            $stmt->execute([':t' => $tenantId, ':s' => $slug, ':eid' => $excludeId]);
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT 1 FROM categories WHERE tenant_id = :t AND slug = :s LIMIT 1'
            );
            $stmt->execute([':t' => $tenantId, ':s' => $slug]);
        }
        return $stmt->fetchColumn() !== false;
    }

    /** Check if a category has child categories */
    public function hasChildren(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM categories WHERE parent_id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetchColumn() !== false;
    }

    /** Get translations for a category (from category_translations table if present) */
    public function getTranslations(int $id): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT language_code, name, description FROM category_translations WHERE category_id = :id'
            );
            $stmt->execute([':id' => $id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $result = [];
            foreach ($rows as $row) {
                $result[$row['language_code']] = [
                    'name'        => $row['name'] ?? '',
                    'description' => $row['description'] ?? '',
                ];
            }
            return $result;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Get main image for a category (stub – returns null if images table is not configured) */
    public function getMainImage(int $tenantId, int $id): ?array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT id, url, thumb_url FROM images
                  WHERE entity_type = 'category' AND entity_id = :id AND tenant_id = :t
                  ORDER BY is_primary DESC, id ASC LIMIT 1"
            );
            $stmt->execute([':id' => $id, ':t' => $tenantId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Find a category with all its translations (used by service save/delete) */
    public function findByIdWithTranslations(int $tenantId, int $id, string $lang = 'ar'): ?array
    {
        $row = $this->find($tenantId, $id, $lang);
        if (!$row) return null;
        $row['translations'] = $this->getTranslations($id);
        return $row;
    }

    /**
     * Save (create or update) a category.
     * If $data contains 'id', performs an UPDATE; otherwise INSERT.
     * Returns the saved category id.
     */
    public function save(int $tenantId, array $data, ?int $userId = null): int
    {
        if (!empty($data['id'])) {
            $id = (int)$data['id'];
            $this->update($tenantId, $id, $data);
            // Save/upsert translations
            if (!empty($data['translations']) && is_array($data['translations'])) {
                foreach ($data['translations'] as $langCode => $trans) {
                    $this->upsertTranslation($id, (string)$langCode, $trans);
                }
            }
            return $id;
        } else {
            $id = $this->create($tenantId, $data);
            if (!empty($data['translations']) && is_array($data['translations'])) {
                foreach ($data['translations'] as $langCode => $trans) {
                    $this->upsertTranslation($id, (string)$langCode, $trans);
                }
            }
            return $id;
        }
    }

    /** Upsert a single translation row */
    private function upsertTranslation(int $categoryId, string $languageCode, array $data): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO category_translations (category_id, language_code, name, description)
                VALUES (:cid, :lang, :name, :desc)
                ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description)
            ");
            $stmt->execute([
                ':cid'  => $categoryId,
                ':lang' => $languageCode,
                ':name' => $data['name'] ?? '',
                ':desc' => $data['description'] ?? '',
            ]);
        } catch (\Throwable $e) {
            // Translation save failure is non-fatal
        }
    }

    /** Delete a single translation */
    public function deleteTranslation(int $categoryId, string $languageCode): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                'DELETE FROM category_translations WHERE category_id = :cid AND language_code = :lang'
            );
            return $stmt->execute([':cid' => $categoryId, ':lang' => $languageCode]);
        } catch (\Throwable $e) {
            return false;
        }
    }
}