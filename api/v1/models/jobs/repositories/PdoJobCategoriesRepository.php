<?php
declare(strict_types=1);

final class PdoJobCategoriesRepository
{
    private PDO $pdo;

    // الأعمدة المسموح بها للفرز
    private const ALLOWED_ORDER_BY = [
        'id', 'tenant_id', 'parent_id', 'slug', 'sort_order', 'is_active', 'created_at'
    ];

    // الأعمدة القابلة للفلاتر
    private const FILTERABLE_COLUMNS = [
        'tenant_id', 'parent_id', 'is_active'
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ================================
    // List with dynamic filters, ordering, pagination
    // ================================
    public function all(
        int $tenantId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'sort_order',
        string $orderDir = 'ASC',
        string $lang = 'ar'
    ): array {
        $sql = "
            SELECT jc.*,
                   COALESCE(jct.name, '') AS name,
                   COALESCE(jct.description, '') AS description,
                   l.name AS language_name,
                   l.direction AS language_direction,
                   (SELECT COUNT(*) FROM job_categories WHERE parent_id = jc.id) AS children_count
            FROM job_categories jc
            LEFT JOIN job_category_translations jct
                ON jc.id = jct.category_id AND jct.language_code = :lang
            LEFT JOIN languages l
                ON jct.language_code = l.code
            WHERE jc.tenant_id = :tenant_id
        ";
        $params = [':tenant_id' => $tenantId, ':lang' => $lang];

        // تطبيق الفلاتر
        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                // parent_id يمكن أن يكون NULL
                if ($col === 'parent_id' && $filters[$col] === 'null') {
                    $sql .= " AND jc.{$col} IS NULL";
                } else {
                    $sql .= " AND jc.{$col} = :{$col}";
                    $params[":{$col}"] = $filters[$col];
                }
            }
        }

        // فلتر البحث في الاسم
        if (!empty($filters['search'])) {
            $sql .= " AND jct.name LIKE :search";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        // الفرز
        $orderBy = in_array($orderBy, self::ALLOWED_ORDER_BY, true) ? $orderBy : 'sort_order';
        $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';
        $sql .= " ORDER BY jc.{$orderBy} {$orderDir}";

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
    public function count(int $tenantId, array $filters = [], string $lang = 'ar'): int
    {
        $sql = "SELECT COUNT(DISTINCT jc.id) FROM job_categories jc";
        
        if (!empty($filters['search'])) {
            $sql .= " LEFT JOIN job_category_translations jct ON jc.id = jct.category_id AND jct.language_code = :lang";
        }
        
        $sql .= " WHERE jc.tenant_id = :tenant_id";
        $params = [':tenant_id' => $tenantId];

        if (!empty($filters['search'])) {
            $params[':lang'] = $lang;
        }

        foreach (self::FILTERABLE_COLUMNS as $col) {
            if (isset($filters[$col]) && $filters[$col] !== '') {
                if ($col === 'parent_id' && $filters[$col] === 'null') {
                    $sql .= " AND jc.{$col} IS NULL";
                } else {
                    $sql .= " AND jc.{$col} = :{$col}";
                    $params[":{$col}"] = $filters[$col];
                }
            }
        }

        if (!empty($filters['search'])) {
            $sql .= " AND jct.name LIKE :search";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    // ================================
    // Find by ID with translations
    // ================================
    public function find(int $tenantId, int $id, string $lang = 'ar'): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT jc.*,
                   COALESCE(jct.name, '') AS name,
                   COALESCE(jct.description, '') AS description,
                   l.name AS language_name,
                   l.direction AS language_direction,
                   (SELECT COUNT(*) FROM job_categories WHERE parent_id = jc.id) AS children_count
            FROM job_categories jc
            LEFT JOIN job_category_translations jct
                ON jc.id = jct.category_id AND jct.language_code = :lang
            LEFT JOIN languages l
                ON jct.language_code = l.code
            WHERE jc.tenant_id = :tenant_id AND jc.id = :id
            LIMIT 1
        ");
        $stmt->execute([':tenant_id' => $tenantId, ':id' => $id, ':lang' => $lang]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================================
    // Find by slug
    // ================================
    public function findBySlug(int $tenantId, string $slug, string $lang = 'ar'): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT jc.*,
                   COALESCE(jct.name, '') AS name,
                   COALESCE(jct.description, '') AS description,
                   l.name AS language_name,
                   l.direction AS language_direction,
                   (SELECT COUNT(*) FROM job_categories WHERE parent_id = jc.id) AS children_count
            FROM job_categories jc
            LEFT JOIN job_category_translations jct
                ON jc.id = jct.category_id AND jct.language_code = :lang
            LEFT JOIN languages l
                ON jct.language_code = l.code
            WHERE jc.tenant_id = :tenant_id AND jc.slug = :slug
            LIMIT 1
        ");
        $stmt->execute([':tenant_id' => $tenantId, ':slug' => $slug, ':lang' => $lang]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ================================
    // Get category tree (hierarchical structure)
    // ================================
    public function getTree(int $tenantId, ?int $parentId = null, string $lang = 'ar'): array
    {
        $sql = "
            SELECT jc.*,
                   COALESCE(jct.name, '') AS name,
                   COALESCE(jct.description, '') AS description
            FROM job_categories jc
            LEFT JOIN job_category_translations jct
                ON jc.id = jct.category_id AND jct.language_code = :lang
            WHERE jc.tenant_id = :tenant_id
        ";
        
        if ($parentId === null) {
            $sql .= " AND jc.parent_id IS NULL";
            $params = [':tenant_id' => $tenantId, ':lang' => $lang];
        } else {
            $sql .= " AND jc.parent_id = :parent_id";
            $params = [':tenant_id' => $tenantId, ':parent_id' => $parentId, ':lang' => $lang];
        }
        
        $sql .= " ORDER BY jc.sort_order ASC, jc.id ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // إضافة الأطفال لكل فئة
        foreach ($categories as &$category) {
            $category['children'] = $this->getTree($tenantId, (int)$category['id'], $lang);
        }

        return $categories;
    }

    // ================================
    // Get children of a category
    // ================================
    public function getChildren(int $tenantId, int $parentId, string $lang = 'ar'): array
    {
        $stmt = $this->pdo->prepare("
            SELECT jc.*,
                   COALESCE(jct.name, '') AS name,
                   COALESCE(jct.description, '') AS description,
                   (SELECT COUNT(*) FROM job_categories WHERE parent_id = jc.id) AS children_count
            FROM job_categories jc
            LEFT JOIN job_category_translations jct
                ON jc.id = jct.category_id AND jct.language_code = :lang
            WHERE jc.tenant_id = :tenant_id AND jc.parent_id = :parent_id
            ORDER BY jc.sort_order ASC, jc.id ASC
        ");
        $stmt->execute([':tenant_id' => $tenantId, ':parent_id' => $parentId, ':lang' => $lang]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================
    // Get root categories (no parent)
    // ================================
    public function getRootCategories(int $tenantId, string $lang = 'ar'): array
    {
        $stmt = $this->pdo->prepare("
            SELECT jc.*,
                   COALESCE(jct.name, '') AS name,
                   COALESCE(jct.description, '') AS description,
                   (SELECT COUNT(*) FROM job_categories WHERE parent_id = jc.id) AS children_count
            FROM job_categories jc
            LEFT JOIN job_category_translations jct
                ON jc.id = jct.category_id AND jct.language_code = :lang
            WHERE jc.tenant_id = :tenant_id AND jc.parent_id IS NULL
            ORDER BY jc.sort_order ASC, jc.id ASC
        ");
        $stmt->execute([':tenant_id' => $tenantId, ':lang' => $lang]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================
    // Get all translations for a category
    // ================================
    public function getTranslations(int $categoryId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT jct.*,
                   l.name AS language_name,
                   l.direction AS language_direction
            FROM job_category_translations jct
            JOIN languages l ON jct.language_code = l.code
            WHERE jct.category_id = :category_id
            ORDER BY jct.language_code
        ");
        $stmt->execute([':category_id' => $categoryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================================
    // Create / Update Category
    // ================================
    private const CATEGORY_COLUMNS = [
        'parent_id', 'slug', 'sort_order', 'is_active', 'image_url', 'icon_url'
    ];

    public function save(int $tenantId, array $data): int
    {
        $isUpdate = !empty($data['id']);

        $params = [];
        foreach (self::CATEGORY_COLUMNS as $col) {
            if (array_key_exists($col, $data)) {
                $val = $data[$col];
                $params[':' . $col] = ($val === '' || $val === null) ? null : $val;
            } else {
                $params[':' . $col] = null;
            }
        }

        // توليد slug تلقائياً إذا كان فارغاً
        if (empty($params[':slug']) || $params[':slug'] === null) {
            $name = $data['name'] ?? 'category';
            $params[':slug'] = $this->generateSlug($name);
        }

        // القيم الافتراضية
        if (!isset($params[':sort_order']) || $params[':sort_order'] === null) {
            $params[':sort_order'] = 0;
        }
        if (!isset($params[':is_active'])) {
            $params[':is_active'] = 1;
        }

        if ($isUpdate) {
            $params[':tenant_id'] = $tenantId;
            $params[':id'] = (int)$data['id'];

            $stmt = $this->pdo->prepare("
                UPDATE job_categories SET
                    parent_id = :parent_id,
                    slug = :slug,
                    sort_order = :sort_order,
                    is_active = :is_active,
                    image_url = :image_url,
                    icon_url = :icon_url
                WHERE tenant_id = :tenant_id AND id = :id
            ");
            $stmt->execute($params);
            return (int)$data['id'];
        }

        $params[':tenant_id'] = $tenantId;

        $stmt = $this->pdo->prepare("
            INSERT INTO job_categories (
                tenant_id, parent_id, slug, sort_order, is_active, image_url, icon_url
            ) VALUES (
                :tenant_id, :parent_id, :slug, :sort_order, :is_active, :image_url, :icon_url
            )
        ");
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    // ================================
    // Delete category
    // ================================
    public function delete(int $tenantId, int $id): bool
    {
        // التحقق من عدم وجود فئات فرعية
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM job_categories WHERE parent_id = :id"
        );
        $stmt->execute([':id' => $id]);
        $childrenCount = (int)$stmt->fetchColumn();

        if ($childrenCount > 0) {
            throw new RuntimeException('Cannot delete category with children. Delete or reassign children first.');
        }

        // Translations will be deleted automatically due to CASCADE
        $stmt = $this->pdo->prepare(
            "DELETE FROM job_categories WHERE tenant_id = :tenant_id AND id = :id"
        );
        return $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);
    }

    // ================================
    // Update sort order
    // ================================
    public function updateSortOrder(int $tenantId, int $id, int $sortOrder): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE job_categories 
            SET sort_order = :sort_order 
            WHERE tenant_id = :tenant_id AND id = :id
        ");
        return $stmt->execute([
            ':tenant_id' => $tenantId,
            ':id' => $id,
            ':sort_order' => $sortOrder
        ]);
    }

    // ================================
    // Move category to different parent
    // ================================
    public function moveToParent(int $tenantId, int $id, ?int $newParentId): bool
    {
        // التحقق من عدم نقل الفئة إلى نفسها أو إلى أحد أطفالها
        if ($newParentId !== null && $this->isDescendantOf($id, $newParentId)) {
            throw new RuntimeException('Cannot move category to its own descendant.');
        }

        $stmt = $this->pdo->prepare("
            UPDATE job_categories 
            SET parent_id = :parent_id 
            WHERE tenant_id = :tenant_id AND id = :id
        ");
        return $stmt->execute([
            ':tenant_id' => $tenantId,
            ':id' => $id,
            ':parent_id' => $newParentId
        ]);
    }

    // ================================
    // Check if category is descendant of another
    // ================================
    private function isDescendantOf(int $categoryId, int $potentialAncestorId): bool
    {
        if ($categoryId === $potentialAncestorId) {
            return true;
        }

        $stmt = $this->pdo->prepare("
            SELECT parent_id FROM job_categories WHERE id = :id
        ");
        $stmt->execute([':id' => $categoryId]);
        $parentId = $stmt->fetchColumn();

        if (!$parentId) {
            return false;
        }

        return $this->isDescendantOf((int)$parentId, $potentialAncestorId);
    }

    // ================================
    // Generate unique slug
    // ================================
    private function generateSlug(string $name): string
    {
        $slug = preg_replace('/[^a-z0-9\p{Arabic}\-]+/u', '-', mb_strtolower(trim($name)));
        $slug = trim($slug, '-');
        
        if (empty($slug)) {
            $slug = 'category';
        }

        // إضافة رقم عشوائي لضمان عدم التكرار
        $slug .= '-' . time() . '-' . mt_rand(100, 999);

        return $slug;
    }
}