<?php
/**
 * TORO — v1/modules/Products/Validators/ProductsValidator.php
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\ValidationException;

final class ProductsValidator
{
    private const VALID_TYPES   = ['simple', 'variable', 'bundle'];
    private const SKU_PATTERN   = '/^[A-Z0-9][-A-Z0-9_]*$/';

    public static function create(array $d): void
    {
        $e = [];

        if (empty($d['sku'])) {
            $e['sku'] = 'الـ SKU مطلوب';
        } elseif (!preg_match(self::SKU_PATTERN, strtoupper(trim($d['sku'])))) {
            $e['sku'] = 'الـ SKU يجب أن يحتوي على أحرف كبيرة وأرقام وشرطات فقط';
        }

        if (empty($d['brand_id']) || (int)$d['brand_id'] <= 0) {
            $e['brand_id'] = 'معرف الماركة مطلوب';
        }

        if (!empty($d['type']) && !in_array($d['type'], self::VALID_TYPES, true)) {
            $e['type'] = 'نوع المنتج غير صحيح. القيم المتاحة: ' . implode(', ', self::VALID_TYPES);
        }

        if (isset($d['base_price']) && (float)$d['base_price'] < 0) {
            $e['base_price'] = 'السعر الأساسي يجب أن يكون صفراً أو أكثر';
        }

        if (isset($d['sale_price']) && (float)$d['sale_price'] < 0) {
            $e['sale_price'] = 'سعر التخفيض يجب أن يكون صفراً أو أكثر';
        }

        if (!empty($d['translations'])) {
            self::validateTranslations($d['translations'], $e);
        }

        if ($e) throw new ValidationException('بيانات المنتج غير صحيحة', $e);
    }

    public static function update(array $d): void
    {
        $e = [];

        if (isset($d['sku'])) {
            if (empty($d['sku'])) {
                $e['sku'] = 'الـ SKU لا يمكن أن يكون فارغاً';
            } elseif (!preg_match(self::SKU_PATTERN, strtoupper(trim($d['sku'])))) {
                $e['sku'] = 'الـ SKU يجب أن يحتوي على أحرف كبيرة وأرقام وشرطات فقط';
            }
        }

        if (!empty($d['type']) && !in_array($d['type'], self::VALID_TYPES, true)) {
            $e['type'] = 'نوع المنتج غير صحيح. القيم المتاحة: ' . implode(', ', self::VALID_TYPES);
        }

        if (isset($d['base_price']) && (float)$d['base_price'] < 0) {
            $e['base_price'] = 'السعر الأساسي يجب أن يكون صفراً أو أكثر';
        }

        if (isset($d['sale_price']) && (float)$d['sale_price'] < 0) {
            $e['sale_price'] = 'سعر التخفيض يجب أن يكون صفراً أو أكثر';
        }

        if (!empty($d['translations'])) {
            self::validateTranslations($d['translations'], $e);
        }

        if ($e) throw new ValidationException('بيانات التحديث غير صحيحة', $e);
    }

    private static function validateTranslations(array $translations, array &$e): void
    {
        foreach ($translations as $i => $t) {
            if (empty($t['lang'])) {
                $e["translations.{$i}.lang"] = 'رمز اللغة مطلوب';
            }
            if (empty($t['name'])) {
                $e["translations.{$i}.name"] = 'اسم المنتج مطلوب';
            } elseif (mb_strlen(trim($t['name'])) < 2) {
                $e["translations.{$i}.name"] = 'اسم المنتج يجب أن يكون حرفان على الأقل';
            }
        }
    }
}
