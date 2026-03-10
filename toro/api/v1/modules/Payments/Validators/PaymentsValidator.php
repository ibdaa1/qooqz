<?php
/**
 * TORO — v1/modules/Payments/Validators/PaymentsValidator.php
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\ValidationException;

final class PaymentsValidator
{
    private const METHODS = ['cash', 'card', 'apple_pay'];

    public static function create(array $data): void
    {
        $errors = [];

        if (empty($data['order_id']))                          $errors['order_id'] = 'معرف الطلب مطلوب';
        if (empty($data['method']))                            $errors['method']   = 'طريقة الدفع مطلوبة';
        if (!in_array($data['method'] ?? '', self::METHODS))   $errors['method']   = 'طريقة الدفع غير صالحة';
        if (!isset($data['amount']) || (float)$data['amount'] <= 0)
                                                               $errors['amount']   = 'المبلغ يجب أن يكون أكبر من صفر';

        if ($errors) throw new ValidationException($errors);
    }

    public static function refund(array $data): void
    {
        $errors = [];

        if (!isset($data['amount']) || (float)$data['amount'] <= 0) {
            $errors['amount'] = 'مبلغ الاسترداد يجب أن يكون أكبر من صفر';
        }

        if ($errors) throw new ValidationException($errors);
    }
}
