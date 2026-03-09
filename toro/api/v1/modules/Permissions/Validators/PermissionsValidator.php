<?php
/**
 * TORO — v1/modules/Permissions/Validators/PermissionsValidator.php
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\ValidationException;

final class PermissionsValidator
{
    private const SLUG_PATTERN = '/^[a-z0-9]+(?:[-_:][a-z0-9]+)*$/'; // يسمح بنقط وشرطات سفلية (مثل users.create)

    // ── Create ───────────────────────────────────────────────
    public static function create(array $data): void
    {
        $e = [];

        if (empty($data['name'])) {
            $e['name'] = 'اسم الصلاحية مطلوب';
        } elseif (mb_strlen(trim($data['name'])) < 2) {
            $e['name'] = 'اسم الصلاحية يجب أن يكون حرفين على الأقل';
        }

        if (empty($data['slug'])) {
            $e['slug'] = 'الـ slug مطلوب';
        } elseif (!preg_match(self::SLUG_PATTERN, $data['slug'])) {
            $e['slug'] = 'الـ slug يجب أن يحتوي على أحرف صغيرة وأرقام وشرطات ونقاط فقط';
        }

        if ($e) throw new ValidationException('بيانات الصلاحية غير صحيحة', $e);
    }

    // ── Update ───────────────────────────────────────────────
    public static function update(array $data): void
    {
        $e = [];

        if (isset($data['name']) && mb_strlen(trim($data['name'])) < 2) {
            $e['name'] = 'اسم الصلاحية يجب أن يكون حرفين على الأقل';
        }

        if (isset($data['slug'])) {
            if (empty($data['slug'])) {
                $e['slug'] = 'الـ slug لا يمكن أن يكون فارغاً';
            } elseif (!preg_match(self::SLUG_PATTERN, $data['slug'])) {
                $e['slug'] = 'الـ slug يجب أن يحتوي على أحرف صغيرة وأرقام وشرطات ونقاط فقط';
            }
        }

        if ($e) throw new ValidationException('بيانات التحديث غير صحيحة', $e);
    }
}