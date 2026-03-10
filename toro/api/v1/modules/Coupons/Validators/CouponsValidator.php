<?php
/**
 * TORO — v1/modules/Coupons/Validators/CouponsValidator.php
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\ValidationException;

final class CouponsValidator
{
    private static array $VALID_TYPES = ['percent', 'fixed', 'free_shipping'];

    public static function create(array $data): void
    {
        $e = [];

        if (empty($data['code'])) {
            $e['code'] = 'كود الخصم مطلوب';
        } elseif (!preg_match('/^[A-Z0-9_-]{2,50}$/i', $data['code'])) {
            $e['code'] = 'كود الخصم يجب أن يحتوي على أحرف وأرقام وشرطات فقط (2-50 محرف)';
        }

        if (isset($data['type']) && !in_array($data['type'], self::$VALID_TYPES, true)) {
            $e['type'] = 'نوع الخصم يجب أن يكون: ' . implode(', ', self::$VALID_TYPES);
        }

        if (isset($data['value']) && (float)$data['value'] < 0) {
            $e['value'] = 'قيمة الخصم لا يمكن أن تكون سالبة';
        }

        if ($e) throw new ValidationException('بيانات الكوبون غير صحيحة', $e);
    }

    public static function update(array $data): void
    {
        $e = [];

        if (isset($data['code'])) {
            if (empty($data['code'])) {
                $e['code'] = 'كود الخصم لا يمكن أن يكون فارغاً';
            } elseif (!preg_match('/^[A-Z0-9_-]{2,50}$/i', $data['code'])) {
                $e['code'] = 'كود الخصم يجب أن يحتوي على أحرف وأرقام وشرطات فقط (2-50 محرف)';
            }
        }

        if (isset($data['type']) && !in_array($data['type'], self::$VALID_TYPES, true)) {
            $e['type'] = 'نوع الخصم يجب أن يكون: ' . implode(', ', self::$VALID_TYPES);
        }

        if (isset($data['value']) && (float)$data['value'] < 0) {
            $e['value'] = 'قيمة الخصم لا يمكن أن تكون سالبة';
        }

        if ($e) throw new ValidationException('بيانات التحديث غير صحيحة', $e);
    }
}
