<?php
/**
 * TORO — v1/modules/Translations/Services/TranslationsService.php
 * كل منطق الترجمات
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\{ValidationException, NotFoundException};
use Shared\Helpers\AuditLogger;

final class TranslationsService
{
    public function __construct(
        private readonly TranslationsRepositoryInterface $repo,
    ) {}

    // ══════════════════════════════════════════════════════════
    // KEYS
    // ══════════════════════════════════════════════════════════
    public function listKeys(array $filters = []): array
    {
        $items = $this->repo->findAllKeys($filters);
        $total = $this->repo->countAllKeys($filters);

        return [
            'items' => $items,
            'total' => $total,
            'limit' => max(1, min((int)($filters['limit'] ?? 50), 200)),
            'offset'=> max(0, (int)($filters['offset'] ?? 0)),
        ];
    }

    public function getKeyById(int $id): array
    {
        $key = $this->repo->findKeyById($id);
        if (!$key) throw new NotFoundException("مفتاح الترجمة #{$id} غير موجود");
        return $key;
    }

    public function getKeyByName(string $keyName): array
    {
        $key = $this->repo->findKeyByName($keyName);
        if (!$key) throw new NotFoundException("مفتاح الترجمة '{$keyName}' غير موجود");
        return $key;
    }

    public function createKey(CreateTranslationKeyDTO $dto, int $actorId): array
    {
        // التحقق من عدم التكرار
        $existing = $this->repo->findKeyByName($dto->keyName);
        if ($existing) {
            throw new ValidationException(
                'اسم المفتاح مستخدم مسبقاً',
                ['key_name' => 'يجب أن يكون فريداً']
            );
        }

        $id = $this->repo->createKey([
            'key_name' => $dto->keyName,
            'context'  => $dto->context,
        ]);

        AuditLogger::log('translation_key_created', $actorId, 'translation_keys', $id);

        return $this->repo->findKeyById($id) ?? [];
    }

    public function updateKey(int $id, UpdateTranslationKeyDTO $dto, int $actorId): array
    {
        $existing = $this->repo->findKeyById($id);
        if (!$existing) throw new NotFoundException("مفتاح الترجمة #{$id} غير موجود");

        // التحقق من uniqueness إذا تغير الاسم
        if ($dto->keyName !== null && $dto->keyName !== $existing['key_name']) {
            $conflict = $this->repo->findKeyByName($dto->keyName);
            if ($conflict && (int)$conflict['id'] !== $id) {
                throw new ValidationException(
                    'اسم المفتاح مستخدم مسبقاً',
                    ['key_name' => 'يجب أن يكون فريداً']
                );
            }
        }

        $updateData = array_filter([
            'key_name' => $dto->keyName,
            'context'  => $dto->context,
        ], fn($v) => $v !== null);

        if (!empty($updateData)) {
            $this->repo->updateKey($id, $updateData);
        }

        AuditLogger::log('translation_key_updated', $actorId, 'translation_keys', $id);

        return $this->repo->findKeyById($id) ?? [];
    }

    public function deleteKey(int $id, int $actorId): void
    {
        $existing = $this->repo->findKeyById($id);
        if (!$existing) throw new NotFoundException("مفتاح الترجمة #{$id} غير موجود");

        $this->repo->deleteKey($id);
        AuditLogger::log('translation_key_deleted', $actorId, 'translation_keys', $id);
    }

    // ══════════════════════════════════════════════════════════
    // VALUES
    // ══════════════════════════════════════════════════════════
    public function getValue(int $keyId, int $languageId): ?string
    {
        return $this->repo->getValue($keyId, $languageId);
    }

    public function getValuesByKey(int $keyId): array
    {
        // تحقق من وجود المفتاح
        $this->getKeyById($keyId);
        return $this->repo->getValuesByKeyId($keyId);
    }

    public function getValuesByLanguage(int $languageId): array
    {
        return $this->repo->getValuesByLanguageId($languageId);
    }

    public function upsertValue(UpsertTranslationValueDTO $dto, int $actorId): array
    {
        // تحقق من وجود المفتاح
        $key = $this->repo->findKeyById($dto->keyId);
        if (!$key) throw new NotFoundException("مفتاح الترجمة #{$dto->keyId} غير موجود");

        // اللغة يجب أن تكون موجودة (يمكن التحقق عبر استدعاء service اللغات، لكننا نكتفي بالـ repo هنا)
        // نفترض أن الـ languageId صالح

        $success = $this->repo->upsertValue($dto->keyId, $dto->languageId, $dto->value);
        if (!$success) {
            throw new \RuntimeException('فشل في حفظ الترجمة');
        }

        AuditLogger::log('translation_value_upserted', $actorId, 'translation_values', null, [
            'key_id' => $dto->keyId,
            'language_id' => $dto->languageId,
        ]);

        return [
            'key_id'      => $dto->keyId,
            'language_id' => $dto->languageId,
            'value'       => $dto->value,
        ];
    }

    public function deleteValue(int $keyId, int $languageId, int $actorId): void
    {
        // تحقق من وجود المفتاح
        $this->getKeyById($keyId);

        $deleted = $this->repo->deleteValue($keyId, $languageId);
        if (!$deleted) {
            throw new NotFoundException('قيمة الترجمة غير موجودة لهذا المفتاح واللغة');
        }

        AuditLogger::log('translation_value_deleted', $actorId, 'translation_values', null, [
            'key_id' => $keyId,
            'language_id' => $languageId,
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // BULK / PUBLIC
    // ══════════════════════════════════════════════════════════
    public function getTranslationsByKeys(array $keys, string $langCode): array
    {
        $langId = $this->repo->resolveLanguageId($langCode);
        if (!$langId) {
            throw new NotFoundException("اللغة '{$langCode}' غير موجودة أو غير نشطة");
        }

        return $this->repo->getTranslationsByKeys($keys, $langId);
    }

    public function getLanguagePack(string $langCode): array
    {
        $langId = $this->repo->resolveLanguageId($langCode);
        if (!$langId) {
            throw new NotFoundException("اللغة '{$langCode}' غير موجودة أو غير نشطة");
        }

        return $this->repo->getAllTranslationsForLanguage($langId);
    }

    // ══════════════════════════════════════════════════════════
    // IMPORT / EXPORT
    // ══════════════════════════════════════════════════════════
    public function import(string $langCode, array $translations, int $actorId): array
    {
        $langId = $this->repo->resolveLanguageId($langCode);
        if (!$langId) {
            throw new NotFoundException("اللغة '{$langCode}' غير موجودة أو غير نشطة");
        }

        $stats = ['created' => 0, 'updated' => 0, 'failed' => 0];

        foreach ($translations as $keyName => $value) {
            try {
                // البحث عن المفتاح أو إنشاؤه
                $key = $this->repo->findKeyByName($keyName);
                if (!$key) {
                    // إنشاء مفتاح جديد
                    $keyId = $this->repo->createKey(['key_name' => $keyName, 'context' => 'imported']);
                    $stats['created']++;
                } else {
                    $keyId = (int)$key['id'];
                }

                // حفظ القيمة
                $this->repo->upsertValue($keyId, $langId, (string)$value);
                $stats['updated']++;
            } catch (\Throwable $e) {
                $stats['failed']++;
                // يمكن تسجيل الخطأ في log
            }
        }

        AuditLogger::log('translations_imported', $actorId, 'translations', null, [
            'language_code' => $langCode,
            'stats' => $stats,
        ]);

        return $stats;
    }

    public function export(string $langCode): array
    {
        $langId = $this->repo->resolveLanguageId($langCode);
        if (!$langId) {
            throw new NotFoundException("اللغة '{$langCode}' غير موجودة أو غير نشطة");
        }

        return $this->repo->getAllTranslationsForLanguage($langId);
    }
}