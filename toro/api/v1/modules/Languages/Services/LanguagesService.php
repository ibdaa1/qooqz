<?php
/**
 * TORO — v1/modules/Languages/Services/LanguagesService.php
 * كل منطق اللغات
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\{ValidationException, NotFoundException};
use Shared\Helpers\AuditLogger;

final class LanguagesService
{
    public function __construct(
        private readonly LanguagesRepositoryInterface $repo,
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
    public function getById(int $id): array
    {
        $lang = $this->repo->findById($id);
        if (!$lang) throw new NotFoundException("اللغة #{$id} غير موجودة");
        return $lang;
    }

    // ══════════════════════════════════════════════════════════
    // GET ONE BY CODE
    // ══════════════════════════════════════════════════════════
    public function getByCode(string $code): array
    {
        $lang = $this->repo->findByCode($code);
        if (!$lang) throw new NotFoundException("اللغة '{$code}' غير موجودة");
        return $lang;
    }

    // ══════════════════════════════════════════════════════════
    // GET DEFAULT
    // ══════════════════════════════════════════════════════════
    public function getDefault(): array
    {
        $lang = $this->repo->getDefault();
        if (!$lang) throw new NotFoundException("لا توجد لغة افتراضية محددة");
        return $lang;
    }

    // ══════════════════════════════════════════════════════════
    // CREATE
    // ══════════════════════════════════════════════════════════
    public function create(CreateLanguageDTO $dto, int $actorId): array
    {
        // التحقق من uniqueness
        if (!$this->repo->isCodeUnique($dto->code)) {
            throw new ValidationException(
                'كود اللغة مستخدم مسبقاً',
                ['code' => 'يجب أن يكون فريداً']
            );
        }

        // إذا كانت هذه اللغة هي الافتراضية، تأكد من عدم وجود افتراضية أخرى
        if ($dto->isDefault) {
            $this->ensureNoOtherDefault();
        }

        $id = $this->repo->create([
            'code'       => $dto->code,
            'name'       => $dto->name,
            'native'     => $dto->native,
            'direction'  => $dto->direction,
            'flag_icon'  => $dto->flagIcon,
            'is_active'  => $dto->isActive,
            'is_default' => $dto->isDefault,
            'sort_order' => $dto->sortOrder,
        ]);

        AuditLogger::log('language_created', $actorId, 'languages', $id);

        return $this->repo->findById($id) ?? [];
    }

    // ══════════════════════════════════════════════════════════
    // UPDATE
    // ══════════════════════════════════════════════════════════
    public function update(int $id, UpdateLanguageDTO $dto, int $actorId): array
    {
        $existing = $this->repo->findById($id);
        if (!$existing) throw new NotFoundException("اللغة #{$id} غير موجودة");

        // التحقق من uniqueness إذا تغير الكود
        if ($dto->code !== null && $dto->code !== $existing['code']) {
            if (!$this->repo->isCodeUnique($dto->code, $id)) {
                throw new ValidationException(
                    'كود اللغة مستخدم مسبقاً',
                    ['code' => 'يجب أن يكون فريداً']
                );
            }
        }

        // إذا تم تعيين is_default = true، تأكد من عدم وجود افتراضية أخرى
        if ($dto->isDefault === true && !$existing['is_default']) {
            $this->ensureNoOtherDefault($id);
        }

        // لا يمكن إلغاء الافتراضية إذا كانت هي الوحيدة؟ نمنع ذلك
        if ($dto->isDefault === false && $existing['is_default']) {
            // يمكن أن يكون هناك لغة افتراضية أخرى؟ نتحقق من وجود بديل
            $defaultCount = $this->repo->countAll(['is_default' => true]);
            if ($defaultCount <= 1) {
                throw new ValidationException(
                    'لا يمكن إلغاء اللغة الافتراضية الوحيدة، يجب تعيين لغة افتراضية أخرى أولاً',
                    ['is_default' => 'مطلوب لغة افتراضية واحدة على الأقل']
                );
            }
        }

        $updateData = array_filter([
            'code'       => $dto->code,
            'name'       => $dto->name,
            'native'     => $dto->native,
            'direction'  => $dto->direction,
            'flag_icon'  => $dto->flagIcon,
            'is_active'  => $dto->isActive !== null ? (int)$dto->isActive : null,
            'is_default' => $dto->isDefault !== null ? (int)$dto->isDefault : null,
            'sort_order' => $dto->sortOrder,
        ], fn($v) => $v !== null);

        if (!empty($updateData)) {
            $this->repo->update($id, $updateData);
        }

        AuditLogger::log('language_updated', $actorId, 'languages', $id);

        return $this->repo->findById($id) ?? [];
    }

    // ══════════════════════════════════════════════════════════
    // DELETE (soft)
    // ══════════════════════════════════════════════════════════
    public function delete(int $id, int $actorId): void
    {
        $existing = $this->repo->findById($id);
        if (!$existing) throw new NotFoundException("اللغة #{$id} غير موجودة");

        // منع حذف اللغة الافتراضية
        if ($existing['is_default']) {
            throw new ValidationException('لا يمكن حذف اللغة الافتراضية');
        }

        $this->repo->delete($id);
        AuditLogger::log('language_deleted', $actorId, 'languages', $id);
    }

    // ── Private helpers ────────────────────────────────────────
    private function ensureNoOtherDefault(?int $exceptId = null): void
    {
        $filters = ['is_default' => true];
        if ($exceptId) {
            // نحتاج إلى استثناء هذا id من البحث
            // لا يوجد دعم استثناء في findAll حالياً، لذا نعدل يدوياً
            $all = $this->repo->findAll(['is_default' => true]);
            $defaults = array_filter($all, fn($l) => $l['is_default'] && $l['id'] != $exceptId);
            if (!empty($defaults)) {
                throw new ValidationException(
                    'يوجد بالفعل لغة افتراضية أخرى',
                    ['is_default' => 'يمكن تعيين لغة واحدة فقط كافتراضية']
                );
            }
        } else {
            $count = $this->repo->countAll(['is_default' => true]);
            if ($count > 0) {
                throw new ValidationException(
                    'يوجد بالفعل لغة افتراضية',
                    ['is_default' => 'يمكن تعيين لغة واحدة فقط كافتراضية']
                );
            }
        }
    }
}