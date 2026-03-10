<?php
/**
 * TORO — v1/modules/Attributes/Validators/AttributeValuesValidator.php
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\ValidationException;

final class AttributeValuesValidator
{
    // Attribute value slugs allow both hyphens and underscores (e.g. rose-water, light_blue)
    private const SLUG_PATTERN      = '/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/';
    private const HEX_COLOR_PATTERN = '/^#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?$/';

    // ── Create ───────────────────────────────────────────────
    public static function create(array $data): void
    {
        $e = [];

        if (empty($data['attribute_id']) || (int)$data['attribute_id'] <= 0) {
            $e['attribute_id'] = 'معرف السمة مطلوب';
        }

        if (empty($data['slug'])) {
            $e['slug'] = 'الـ slug مطلوب';
        } elseif (!preg_match(self::SLUG_PATTERN, $data['slug'])) {
            $e['slug'] = 'الـ slug يجب أن يحتوي على أحرف صغيرة وأرقام وشرطات فقط';
        }

        if (!empty($data['color_hex']) && !preg_match(self::HEX_COLOR_PATTERN, $data['color_hex'])) {
            $e['color_hex'] = 'قيمة اللون يجب أن تكون بصيغة hex صحيحة مثل #fff أو #ffffff';
        }

        if (!empty($data['translations'])) {
            self::validateTranslations($data['translations'], $e);
        }

        if ($e) throw new ValidationException('بيانات قيمة السمة غير صحيحة', $e);
    }

    // ── Update ───────────────────────────────────────────────
    public static function update(array $data): void
    {
        $e = [];

        if (isset($data['slug'])) {
            if (empty($data['slug'])) {
                $e['slug'] = 'الـ slug لا يمكن أن يكون فارغاً';
            } elseif (!preg_match(self::SLUG_PATTERN, $data['slug'])) {
                $e['slug'] = 'الـ slug يجب أن يحتوي على أحرف صغيرة وأرقام وشرطات فقط';
            }
        }

        if (!empty($data['color_hex']) && !preg_match(self::HEX_COLOR_PATTERN, $data['color_hex'])) {
            $e['color_hex'] = 'قيمة اللون يجب أن تكون بصيغة hex صحيحة مثل #fff أو #ffffff';
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
                $e["translations.{$i}.name"] = 'اسم القيمة مطلوب';
            } elseif (mb_strlen(trim($t['name'])) < 1) {
                $e["translations.{$i}.name"] = 'اسم القيمة لا يمكن أن يكون فارغاً';
            }
        }
    }
}
