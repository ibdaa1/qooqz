<?php
/**
 * TORO — v1/modules/Attributes/Validators/AttributesValidator.php
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\ValidationException;

final class AttributesValidator
{
    private const VALID_TYPES   = ['select', 'multiselect', 'color', 'size', 'boolean'];
    private const SLUG_PATTERN  = '/^[a-z0-9]+(?:_[a-z0-9]+)*$/';

    // ── Create ───────────────────────────────────────────────
    public static function create(array $data): void
    {
        $e = [];

        if (empty($data['slug'])) {
            $e['slug'] = 'الـ slug مطلوب';
        } elseif (!preg_match(self::SLUG_PATTERN, $data['slug'])) {
            $e['slug'] = 'الـ slug يجب أن يحتوي على أحرف صغيرة وأرقام وشرطات سفلية فقط';
        }

        if (isset($data['type']) && !in_array($data['type'], self::VALID_TYPES, true)) {
            $e['type'] = 'نوع السمة غير صحيح. القيم المتاحة: ' . implode(', ', self::VALID_TYPES);
        }

        if (!empty($data['translations'])) {
            self::validateTranslations($data['translations'], $e);
        }

        if ($e) throw new ValidationException('بيانات السمة غير صحيحة', $e);
    }

    // ── Update ───────────────────────────────────────────────
    public static function update(array $data): void
    {
        $e = [];

        if (isset($data['slug'])) {
            if (empty($data['slug'])) {
                $e['slug'] = 'الـ slug لا يمكن أن يكون فارغاً';
            } elseif (!preg_match(self::SLUG_PATTERN, $data['slug'])) {
                $e['slug'] = 'الـ slug يجب أن يحتوي على أحرف صغيرة وأرقام وشرطات سفلية فقط';
            }
        }

        if (isset($data['type']) && !in_array($data['type'], self::VALID_TYPES, true)) {
            $e['type'] = 'نوع السمة غير صحيح. القيم المتاحة: ' . implode(', ', self::VALID_TYPES);
        }

        if (!empty($data['translations'])) {
            self::validateTranslations($data['translations'], $e);
        }

        if ($e) throw new ValidationException('بيانات التحديث غير صحيحة', $e);
    }

    // ── Private helpers ────────────────────────────────────────
    private static function validateTranslations(array $translations, array &$e): void
    {
        foreach ($translations as $i => $t) {
            if (empty($t['lang'])) {
                $e["translations.{$i}.lang"] = 'رمز اللغة مطلوب';
            }
            if (empty($t['name'])) {
                $e["translations.{$i}.name"] = 'اسم السمة مطلوب';
            } elseif (mb_strlen(trim($t['name'])) < 2) {
                $e["translations.{$i}.name"] = 'اسم السمة يجب أن يكون حرفان على الأقل';
            }
        }
    }
}
