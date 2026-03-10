<?php
/**
 * TORO — v1/modules/Attributes/Services/AttributesService.php
 * كل منطق السمات — Controller لا يعرف PDO أبداً
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\{ValidationException, NotFoundException};
use Shared\Helpers\AuditLogger;

final class AttributesService
{
    public function __construct(
        private readonly AttributesRepositoryInterface $repo,
    ) {}

    // ══════════════════════════════════════════════════════════
    // LIST
    // ══════════════════════════════════════════════════════════
    public function list(array $filters = []): array
    {
        $items = $this->repo->findAll($filters);
        $total = $this->repo->countAll($filters);

        return [
            'items'  => $items,
            'total'  => $total,
            'limit'  => max(1, min((int)($filters['limit'] ?? 50), 200)),
            'offset' => max(0, (int)($filters['offset'] ?? 0)),
        ];
    }

    // ══════════════════════════════════════════════════════════
    // GET ONE BY ID
    // ══════════════════════════════════════════════════════════
    public function getById(int $id, ?string $lang = null): array
    {
        $attribute = $this->repo->findById($id, $lang);
        if (!$attribute) throw new NotFoundException("السمة #{$id} غير موجودة");
        return $attribute;
    }

    // ══════════════════════════════════════════════════════════
    // GET ONE BY SLUG
    // ══════════════════════════════════════════════════════════
    public function getBySlug(string $slug, ?string $lang = null): array
    {
        $attribute = $this->repo->findBySlug($slug, $lang);
        if (!$attribute) throw new NotFoundException("السمة '{$slug}' غير موجودة");
        return $attribute;
    }

    // ══════════════════════════════════════════════════════════
    // CREATE
    // ══════════════════════════════════════════════════════════
    public function create(CreateAttributeDTO $dto, int $actorId): array
    {
        // تحقق من عدم تكرار الـ slug
        if ($this->repo->findBySlug($dto->slug)) {
            throw new ValidationException(
                'هذا الـ slug مستخدم مسبقاً',
                ['slug' => 'يجب أن يكون فريداً']
            );
        }

        $attributeId = $this->repo->create([
            'slug'       => $dto->slug,
            'type'       => $dto->type,
            'sort_order' => $dto->sortOrder,
            'is_active'  => $dto->isActive,
        ]);

        // حفظ الترجمات
        foreach ($dto->translations as $t) {
            $langId = $this->repo->resolveLanguageId($t['lang']);
            if ($langId === null) continue;
            $this->repo->upsertTranslation($attributeId, $langId, $t);
        }

        AuditLogger::log('attribute_created', $actorId, 'attributes', $attributeId);

        return array_merge(
            $this->repo->findById($attributeId) ?? [],
            ['translations' => $this->repo->getTranslations($attributeId)]
        );
    }

    // ══════════════════════════════════════════════════════════
    // UPDATE
    // ══════════════════════════════════════════════════════════
    public function update(int $id, UpdateAttributeDTO $dto, int $actorId): array
    {
        $existing = $this->repo->findById($id);
        if (!$existing) throw new NotFoundException("السمة #{$id} غير موجودة");

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
            'type'       => $dto->type,
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

        AuditLogger::log('attribute_updated', $actorId, 'attributes', $id);

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
        if (!$existing) throw new NotFoundException("السمة #{$id} غير موجودة");

        $this->repo->delete($id);
        AuditLogger::log('attribute_deleted', $actorId, 'attributes', $id);
    }

    // ══════════════════════════════════════════════════════════
    // TRANSLATIONS
    // ══════════════════════════════════════════════════════════
    public function getTranslations(int $id): array
    {
        $existing = $this->repo->findById($id);
        if (!$existing) throw new NotFoundException("السمة #{$id} غير موجودة");

        return $this->repo->getTranslations($id);
    }

    // ══════════════════════════════════════════════════════════
    // VALUES
    // ══════════════════════════════════════════════════════════
    public function getValues(int $id, ?string $lang = null): array
    {
        $existing = $this->repo->findById($id);
        if (!$existing) throw new NotFoundException("السمة #{$id} غير موجودة");

        return $this->repo->getValues($id, $lang);
    }
}
