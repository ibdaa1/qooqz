<?php
/**
 * TORO — v1/modules/Theme/Validators/ThemeValidator.php
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\ValidationException;

final class ThemeValidator
{
    // ── Create ─────────────────────────────────────────────────
    public static function create(array $data): void
    {
        $e = [];

        if (empty($data['variable'])) {
            $e['variable'] = 'اسم متغير CSS مطلوب';
        } elseif (!preg_match('/^--[a-zA-Z][a-zA-Z0-9-]*$/', $data['variable'])) {
            $e['variable'] = 'اسم المتغير يجب أن يبدأ بـ -- ويحتوي على أحرف وأرقام وشرطات فقط';
        }

        if (!array_key_exists('value', $data) || $data['value'] === '') {
            $e['value'] = 'قيمة CSS مطلوبة';
        }

        if ($e) throw new ValidationException('بيانات الثيم غير صحيحة', $e);
    }

    // ── Update ─────────────────────────────────────────────────
    public static function update(array $data): void
    {
        $e = [];

        if (isset($data['variable'])) {
            if (empty($data['variable'])) {
                $e['variable'] = 'اسم المتغير لا يمكن أن يكون فارغاً';
            } elseif (!preg_match('/^--[a-zA-Z][a-zA-Z0-9-]*$/', $data['variable'])) {
                $e['variable'] = 'اسم المتغير يجب أن يبدأ بـ -- ويحتوي على أحرف وأرقام وشرطات فقط';
            }
        }

        if (isset($data['value']) && $data['value'] === '') {
            $e['value'] = 'قيمة CSS لا يمكن أن تكون فارغة';
        }

        if ($e) throw new ValidationException('بيانات التحديث غير صحيحة', $e);
    }
}
