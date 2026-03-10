<?php
/**
 * TORO — v1/modules/Banners/Validators/BannersValidator.php
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\ValidationException;

final class BannersValidator
{
    private static array $VALID_POSITIONS = ['hero', 'promo', 'sidebar'];

    public static function create(array $data): void
    {
        $e = [];

        if (empty($data['position'])) {
            $e['position'] = 'موضع البانر مطلوب';
        } elseif (!in_array($data['position'], self::$VALID_POSITIONS, true)) {
            $e['position'] = 'موضع البانر يجب أن يكون: ' . implode(', ', self::$VALID_POSITIONS);
        }

        if (!empty($data['link_url']) && !filter_var($data['link_url'], FILTER_VALIDATE_URL)) {
            $e['link_url'] = 'رابط البانر غير صحيح';
        }

        if (!empty($data['translations'])) {
            self::validateTranslations($data['translations'], $e);
        }

        if ($e) throw new ValidationException('بيانات البانر غير صحيحة', $e);
    }

    public static function update(array $data): void
    {
        $e = [];

        if (isset($data['position'])) {
            if (empty($data['position'])) {
                $e['position'] = 'الموضع لا يمكن أن يكون فارغاً';
            } elseif (!in_array($data['position'], self::$VALID_POSITIONS, true)) {
                $e['position'] = 'موضع البانر يجب أن يكون: ' . implode(', ', self::$VALID_POSITIONS);
            }
        }

        if (!empty($data['link_url']) && !filter_var($data['link_url'], FILTER_VALIDATE_URL)) {
            $e['link_url'] = 'رابط البانر غير صحيح';
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
        }
    }
}
