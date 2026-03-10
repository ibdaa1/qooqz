<?php
/**
 * TORO — v1/modules/Orders/Validators/OrdersValidator.php
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\ValidationException;

final class OrdersValidator
{
    private static array $VALID_STATUSES = [
        'pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'
    ];

    public static function create(array $data): void
    {
        $e = [];

        if (empty($data['items']) || !is_array($data['items'])) {
            $e['items'] = 'يجب إضافة منتج واحد على الأقل';
        } else {
            foreach ($data['items'] as $i => $item) {
                if (empty($item['product_id'])) {
                    $e["items.{$i}.product_id"] = 'معرّف المنتج مطلوب';
                }
                if (empty($item['product_name'])) {
                    $e["items.{$i}.product_name"] = 'اسم المنتج مطلوب';
                }
                if (empty($item['sku'])) {
                    $e["items.{$i}.sku"] = 'الـ SKU مطلوب';
                }
                if (!isset($item['unit_price']) || (float)$item['unit_price'] < 0) {
                    $e["items.{$i}.unit_price"] = 'سعر الوحدة غير صحيح';
                }
            }
        }

        if ($e) throw new ValidationException('بيانات الطلب غير صحيحة', $e);
    }

    public static function validateStatus(string $status): void
    {
        if (!in_array($status, self::$VALID_STATUSES, true)) {
            throw new ValidationException(
                'حالة الطلب غير صحيحة',
                ['status' => 'يجب أن تكون: ' . implode(', ', self::$VALID_STATUSES)]
            );
        }
    }
}
