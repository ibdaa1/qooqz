<?php
/**
 * TORO — v1/modules/Categories/Services/CategoriesService.php
 * كل منطق التصنيفات — Controller لا يعرف PDO أبداً
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\{ValidationException, NotFoundException};
use Shared\Helpers\AuditLogger;

final class CategoriesService
{
    public function __construct(
        private readonly CategoriesRepositoryInterface $repo,
    ) {}

    // ══════════════════════════════════════════════════════════
    // LIST
    // ══════════════════════════════════════════════════════════
    public function list(array $filters = []): array
    {
        $items = $this->repo->findAll($filters);
        $total = $this->repo->countAll($filters);

        return [
            'items' => $items,
            'total' => $total,
            'limit' => max(1, min((int)($filters['limit'] ?? 50), 200)),
            'offset'=> max(0, (int)($filters['offset'] ?? 0)),
        ];
    }

    // ══════════════════════════════════════════════════════════
    // GET ONE BY ID
    // ══════════════════════════════════════════════════════════
    public function getById(int $id, ?string $lang = null): array
    {
        $category = $this->repo->findById($id, $lang);
        if (!$category) throw new NotFoundException("التصنيف #{$id} غير موجود");
        return $category;
    }

    // ══════════════════════════════════════════════════════════
    // GET ONE BY SLUG
    // ══════════════════════════════════════════════════════════
    public function getBySlug(string $slug, ?string $lang = null): array
    {
        $category = $this->repo->findBySlug($slug, $lang);
        if (!$category) throw new NotFoundException("التصنيف '{$slug}' غير موجود");
        return $category;
    }

    // ══════════════════════════════════════════════════════════
    // CREATE
    // ══════════════════════════════════════════════════════════
    public function create(CreateCategoryDTO $dto, int $actorId): array
    {
        // تحقق من عدم تكرار الـ slug
        if ($this->repo->findBySlug($dto->slug)) {
            throw new ValidationException(
                'هذا الـ slug مستخدم مسبقاً',
                ['slug' => 'يجب أن يكون فريداً']
            );
        }

        $categoryId = $this->repo->create([
            'parent_id'  => $dto->parentId,
            'slug'       => $dto->slug,
            'image'      => $dto->image,
            'sort_order' => $dto->sortOrder,
            'is_active'  => $dto->isActive,
        ]);

        // حفظ الترجمات
        foreach ($dto->translations as $t) {
            $langId = $this->repo->resolveLanguageId($t['lang']);
            if ($langId === null) continue;
            $this->repo->upsertTranslation($categoryId, $langId, $t);
        }

        AuditLogger::log('category_created', $actorId, 'categories', $categoryId);

        return array_merge(
            $this->repo->findById($categoryId) ?? [],
            ['translations' => $this->repo->getTranslations($categoryId)]
        );
    }

    // ══════════════════════════════════════════════════════════
    // UPDATE
    // ══════════════════════════════════════════════════════════
    public function update(int $id, UpdateCategoryDTO $dto, int $actorId): array
    {
        $existing = $this->repo->findById($id);
        if (!$existing) throw new NotFoundException("التصنيف #{$id} غير موجود");

        // تحقق من uniqueness إذا تغيّر الـ slug
        if ($dto->slug !== null && $dto->slug !== $existing['slug']) {
            $conflict = $this->repo->findBySlug($dto->slug);
            if ($conflict && (int)$conflict['id'] !== $id) {
                throw new ValidationException(
                    'هذا الـ slug مستخدم مسبقاً',
                    ['slug' => 'يجب أن يكون فريداً']
                );
            }
        }

        $updateData = array_filter([
            'slug'       => $dto->slug,
            'parent_id'  => $dto->parentId,
            'image'      => $dto->image,
            'sort_order' => $dto->sortOrder,
            'is_active'  => $dto->isActive !== null ? (int)$dto->isActive : null,
        ], fn($v) => $v !== null);

        if ($updateData) $this->repo->update($id, $updateData);

        // تحديث الترجمات
        if ($dto->translations !== null) {
            foreach ($dto->translations as $t) {
                $langId = $this->repo->resolveLanguageId($t['lang']);
                if ($langId === null) continue;
                $this->repo->upsertTranslation($id, $langId, $t);
            }
        }

        AuditLogger::log('category_updated', $actorId, 'categories', $id);

        return array_merge(
            $this->repo->findById($id) ?? [],
            ['translations' => $this->repo->getTranslations($id)]
        );
    }

    // ══════════════════════════════════════════════════════════
    // DELETE
    // ══════════════════════════════════════════════════════════
    public function delete(int $id, int $actorId): void
    {
        $existing = $this->repo->findById($id);
        if (!$existing) throw new NotFoundException("التصنيف #{$id} غير موجود");

        $this->repo->delete($id);
        AuditLogger::log('category_deleted', $actorId, 'categories', $id);
    }

    // ══════════════════════════════════════════════════════════
    // TRANSLATIONS
    // ══════════════════════════════════════════════════════════
    public function getTranslations(int $id): array
    {
        $existing = $this->repo->findById($id);
        if (!$existing) throw new NotFoundException("التصنيف #{$id} غير موجود");

        return $this->repo->getTranslations($id);
    }
}
