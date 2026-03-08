<?php
/**
 * TORO — v1/modules/Pages/Validators/PagesValidator.php
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\ValidationException;

final class PagesValidator
{
    public static function create(array $data): void
    {
        $e = [];

        if (empty($data['slug'])) {
            $e['slug'] = 'الـ slug مطلوب';
        } elseif (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $data['slug'])) {
            $e['slug'] = 'الـ slug يجب أن يحتوي على أحرف صغيرة وأرقام وشرطات فقط';
        }

        if (!empty($data['translations'])) {
            self::validateTranslations($data['translations'], $e);
        }

        if ($e) throw new ValidationException('بيانات الصفحة غير صحيحة', $e);
    }

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

        if (!empty($data['translations'])) {
            self::validateTranslations($data['translations'], $e);
        }

        if ($e) throw new ValidationException('بيانات التحديث غير صحيحة', $e);
    }

    private static function validateTranslations(array $translations, array &$e): void
    {
        foreach ($translations as $i => $t) {
            if (empty($t['lang'])) {
                $e["translations.{$i}.lang"] = 'رمز اللغة مطلوب';
            }
            if (empty($t['title'])) {
                $e["translations.{$i}.title"] = 'عنوان الصفحة مطلوب';
            }
            if (empty($t['content'])) {
                $e["translations.{$i}.content"] = 'محتوى الصفحة مطلوب';
            }
        }
    }
}
