<?php
/**
 * TORO — v1/modules/Attributes/Services/AttributeValuesService.php
 * كل منطق قيم السمات — Controller لا يعرف PDO أبداً
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\{NotFoundException};
use Shared\Helpers\AuditLogger;

final class AttributeValuesService
{
    public function __construct(
        private readonly AttributeValuesRepositoryInterface $repo,
        private readonly AttributesRepositoryInterface      $attributesRepo,
    ) {}

    // ══════════════════════════════════════════════════════════
    // GET ONE BY ID
    // ══════════════════════════════════════════════════════════
    public function getById(int $id, ?string $lang = null): array
    {
        $value = $this->repo->findById($id, $lang);
        if (!$value) throw new NotFoundException("قيمة السمة #{$id} غير موجودة");
        return $value;
    }

    // ══════════════════════════════════════════════════════════
    // CREATE
    // ══════════════════════════════════════════════════════════
    public function create(CreateAttributeValueDTO $dto, int $actorId): array
    {
        // تحقق من وجود السمة الأب
        $attribute = $this->attributesRepo->findById($dto->attributeId);
        if (!$attribute) {
            throw new NotFoundException("السمة #{$dto->attributeId} غير موجودة");
        }

        $valueId = $this->repo->create([
            'attribute_id' => $dto->attributeId,
            'slug'         => $dto->slug,
            'color_hex'    => $dto->colorHex,
            'sort_order'   => $dto->sortOrder,
        ]);

        // حفظ الترجمات
        foreach ($dto->translations as $t) {
            $langId = $this->repo->resolveLanguageId($t['lang']);
            if ($langId === null) continue;
            $this->repo->upsertTranslation($valueId, $langId, $t);
        }

        AuditLogger::log('attribute_value_created', $actorId, 'attribute_values', $valueId);

        return array_merge(
            $this->repo->findById($valueId) ?? [],
            ['translations' => $this->repo->getTranslations($valueId)]
        );
    }

    // ══════════════════════════════════════════════════════════
    // UPDATE
    // ══════════════════════════════════════════════════════════
    public function update(int $id, UpdateAttributeValueDTO $dto, int $actorId): array
    {
        $existing = $this->repo->findById($id);
        if (!$existing) throw new NotFoundException("قيمة السمة #{$id} غير موجودة");

        $updateData = array_filter([
            'slug'       => $dto->slug,
            'color_hex'  => $dto->colorHex,
            'sort_order' => $dto->sortOrder,
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

        AuditLogger::log('attribute_value_updated', $actorId, 'attribute_values', $id);

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
        if (!$existing) throw new NotFoundException("قيمة السمة #{$id} غير موجودة");

        $this->repo->delete($id);
        AuditLogger::log('attribute_value_deleted', $actorId, 'attribute_values', $id);
    }

    // ══════════════════════════════════════════════════════════
    // TRANSLATIONS
    // ══════════════════════════════════════════════════════════
    public function getTranslations(int $id): array
    {
        $existing = $this->repo->findById($id);
        if (!$existing) throw new NotFoundException("قيمة السمة #{$id} غير موجودة");

        return $this->repo->getTranslations($id);
    }
}
