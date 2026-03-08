<?php
/**
 * TORO — v1/modules/Brands/Validators/BrandsValidator.php
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\ValidationException;

final class BrandsValidator
{
    // ── Create ───────────────────────────────────────────────
    public static function create(array $data): void
    {
        $e = [];

        if (empty($data['slug'])) {
            $e['slug'] = 'الـ slug مطلوب';
        } elseif (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $data['slug'])) {
            $e['slug'] = 'الـ slug يجب أن يحتوي على أحرف صغيرة وأرقام وشرطات فقط';
        }

        if (!empty($data['website']) && !filter_var($data['website'], FILTER_VALIDATE_URL)) {
            $e['website'] = 'رابط الموقع غير صحيح';
        }

        if (!empty($data['translations'])) {
            self::validateTranslations($data['translations'], $e);
        }

        if ($e) throw new ValidationException('بيانات الماركة غير صحيحة', $e);
    }

    // ── Update ───────────────────────────────────────────────
    public static function update(array $data): void
    {
        $e = [];

        if (isset($data['slug'])) {
            if (empty($data['slug'])) {
                $e['slug'] = 'الـ slug لا يمكن أن يكون فارغاً';
            } elseif (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $data['slug'])) {
                $e['slug'] = 'الـ slug يجب أن يحتوي على أحرف صغيرة وأرقام وشرطات فقط';
            }
        }

        if (!empty($data['website']) && !filter_var($data['website'], FILTER_VALIDATE_URL)) {
            $e['website'] = 'رابط الموقع غير صحيح';
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
                $e["translations.{$i}.name"] = 'اسم الماركة مطلوب';
            } elseif (mb_strlen(trim($t['name'])) < 2) {
                $e["translations.{$i}.name"] = 'اسم الماركة يجب أن يكون حرفان على الأقل';
            }
        }
    }
}
