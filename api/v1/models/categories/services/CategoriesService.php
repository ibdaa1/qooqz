<?php
declare(strict_types=1);

final class CategoriesService
{
    private PdoCategoriesRepository $repo;
    private CategoriesValidator $validator;

    public function __construct(
        PdoCategoriesRepository $repo,
        CategoriesValidator $validator
    ) {
        $this->repo = $repo;
        $this->validator = $validator;
    }

    /* ============================================================
     * LIST CATEGORIES WITH PAGINATION AND FILTERS
     * ============================================================ */
    public function list(
        int $tenantId,
        array $filters = [],
        string $lang = 'ar'
    ): array {
        $parentId    = isset($filters['parent_id']) && $filters['parent_id'] !== '' ? (int)$filters['parent_id'] : null;
        $featuredOnly = isset($filters['is_featured']) && in_array($filters['is_featured'], [1, '1', true, 'true'], true);
        $isActive    = isset($filters['is_active']) ? (int)(bool)filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN) : null;
        $search      = isset($filters['search']) && $filters['search'] !== '' ? (string)$filters['search'] : null;
        $entityId    = isset($filters['entity_id']) && is_numeric($filters['entity_id']) ? (int)$filters['entity_id'] : null;
        $page        = max(1, (int)($filters['page'] ?? 1));
        $limit       = min(1000, max(1, (int)($filters['limit'] ?? 25)));
        $offset      = ($page - 1) * $limit;

        $items = $this->repo->list(
            $tenantId,
            $limit,
            $offset,
            $lang,
            $entityId,
            $isActive,
            $parentId,
            $featuredOnly,
            $search
        );

        $total = $this->repo->count($tenantId, $isActive, $entityId, $parentId, $featuredOnly, $search, $lang);

        return [
            'items' => $items,
            'meta' => [
                'total'       => $total,
                'page'        => $page,
                'per_page'    => $limit,
                'total_pages' => $total > 0 ? (int)ceil($total / $limit) : 0,
                'from'        => $total > 0 ? $offset + 1 : 0,
                'to'          => $total > 0 ? min($offset + $limit, $total) : 0
            ]
        ];
    }

    /* ============================================================
     * GET BY ID WITH TRANSLATIONS
     * ============================================================ */
    public function getById(
        int $tenantId,
        int $id,
        string $lang = 'ar',
        bool $allTranslations = false
    ): array {
        $row = $this->repo->findById($tenantId, $id);
        
        if (!$row) {
            throw new RuntimeException('Category not found');
        }

        if ($allTranslations) {
            $row['translations'] = $this->repo->getTranslations($id);
        } else {
            // دمج البيانات المترجمة
            $translations = $this->repo->getTranslations($id);
            if (isset($translations[$lang])) {
                $row = array_merge($row, $translations[$lang]);
            }
        }

        // إضافة صورة الفئة باستخدام getMainImage العامة
        $image = $this->repo->getMainImage($tenantId, $id);
        if ($image) {
            $row['image_id'] = $image['id'];
            $row['image_url'] = $image['url'];
            $row['image_thumb_url'] = $image['thumb_url'];
        }

        // إضافة اسم الفئة الأم
        if ($row['parent_id']) {
            $parent = $this->repo->findById($tenantId, (int)$row['parent_id']);
            $row['parent_name'] = $parent['name'] ?? null;
        }

        return $row;
    }

    /* ============================================================
     * CREATE / UPDATE CATEGORY
     * ============================================================ */
    public function save(
        int $tenantId,
        array $data,
        ?int $userId = null
    ): array {
        // التحقق من الصحة
        $errors = $this->validator->validate($data);
        if (!empty($errors)) {
            throw new InvalidArgumentException(
                json_encode($errors, JSON_UNESCAPED_UNICODE)
            );
        }

        // التحقق من عدم تكرار slug
        $isUpdate = !empty($data['id']);
        $excludeId = $isUpdate ? (int)$data['id'] : null;
        
        if (isset($data['slug'])) {
            if ($this->repo->slugExists($tenantId, $data['slug'], $excludeId)) {
                throw new InvalidArgumentException(json_encode([
                    'slug' => 'Slug already exists'
                ]));
            }
        }

        // التحقق من صحة parent_id
        if (isset($data['parent_id']) && $data['parent_id']) {
            $parent = $this->repo->findById($tenantId, (int)$data['parent_id']);
            if (!$parent) {
                throw new InvalidArgumentException(json_encode([
                    'parent_id' => 'Parent category not found'
                ]));
            }

            // منع الحلقات (فئة تكون والد نفسها)
            if ($isUpdate && $data['id'] == $data['parent_id']) {
                throw new InvalidArgumentException(json_encode([
                    'parent_id' => 'Category cannot be its own parent'
                ]));
            }
        }

        // الحفظ
        $id = $this->repo->save($tenantId, $data, $userId);

        // جلب البيانات المحفوظة
        $row = $this->repo->findByIdWithTranslations($tenantId, $id);
        if (!$row) {
            throw new RuntimeException('Failed to load saved category');
        }

        return $row;
    }

    /* ============================================================
     * DELETE CATEGORY
     * ============================================================ */
    public function deleteById(
        int $tenantId,
        int $id,
        ?int $userId = null
    ): void {
        // التحقق من وجود الفئة
        $category = $this->repo->findByIdWithTranslations($tenantId, $id);
        if (!$category) {
            throw new RuntimeException('Category not found');
        }

        // التحقق من وجود فئات فرعية
        if ($this->repo->hasChildren($id)) {
            throw new RuntimeException('Cannot delete category with subcategories');
        }

        // الحذف
        if (!$this->repo->delete($tenantId, $id, $userId)) {
            throw new RuntimeException('Failed to delete category');
        }
    }

    /* ============================================================
     * DELETE CATEGORY BY SLUG
     * ============================================================ */
    public function deleteBySlug(
        int $tenantId,
        string $slug,
        ?int $userId = null
    ): void {
        $categoryId = $this->repo->findIdBySlug($tenantId, $slug);
        if (!$categoryId) {
            throw new RuntimeException('Category not found');
        }

        $this->deleteById($tenantId, $categoryId, $userId);
    }

    /* ============================================================
     * DELETE SINGLE TRANSLATION
     * ============================================================ */
    public function deleteTranslation(
        int $tenantId,
        int $categoryId,
        string $languageCode,
        ?int $userId = null
    ): void {
        // التحقق من وجود الفئة
        $category = $this->repo->findByIdWithTranslations($tenantId, $categoryId);
        if (!$category) {
            throw new RuntimeException('Category not found');
        }

        // التحقق من وجود الترجمة
        if (empty($category['translations'][$languageCode])) {
            throw new RuntimeException('Translation not found');
        }

        // حذف الترجمة
        $deleted = $this->repo->deleteTranslation($categoryId, $languageCode);
        if (!$deleted) {
            throw new RuntimeException('Failed to delete translation');
        }
    }

    /* ============================================================
     * GET ACTIVE CATEGORIES
     * ============================================================ */
    public function getActiveCategories(
        int $tenantId,
        string $lang = 'ar'
    ): array {
        return $this->repo->getActive($tenantId, $lang);
    }

    /* ============================================================
     * GET FEATURED CATEGORIES
     * ============================================================ */
    public function getFeaturedCategories(
        int $tenantId,
        string $lang = 'ar'
    ): array {
        return $this->repo->getFeatured($tenantId, $lang);
    }

    /* ============================================================
     * BULK OPERATIONS
     * ============================================================ */
    public function bulkUpdateStatus(
        int $tenantId,
        array $ids,
        bool $isActive,
        ?int $userId = null
    ): int {
        if (empty($ids)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$isActive ? 1 : 0], $ids);
        array_unshift($params, $tenantId);

        $sql = "UPDATE categories SET is_active = ?, updated_at = NOW() 
                WHERE tenant_id = ? AND id IN ($placeholders)";

        $pdo = $this->repo->getPdo();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    public function bulkDelete(
        int $tenantId,
        array $ids,
        ?int $userId = null
    ): int {
        if (empty($ids)) {
            return 0;
        }

        $deletedCount = 0;
        foreach ($ids as $id) {
            try {
                $this->deleteById($tenantId, (int)$id, $userId);
                $deletedCount++;
            } catch (Exception $e) {
                // تسجيل الخطأ ومتابعة الحذف للباقي
                error_log("Failed to delete category {$id}: " . $e->getMessage());
            }
        }

        return $deletedCount;
    }

    /* ============================================================
     * VALIDATION HELPERS
     * ============================================================ */
    public function validateSlug(int $tenantId, string $slug, ?int $excludeId = null): bool
    {
        return !$this->repo->slugExists($tenantId, $slug, $excludeId);
    }

    public function getCategoryTree(int $tenantId, string $lang = 'ar'): array
    {
        return $this->repo->tree($tenantId, $lang);
    }
}