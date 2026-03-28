<?php
declare(strict_types=1);

namespace App\Models\Carts\Validators;

use InvalidArgumentException;

final class CartsValidator
{
    public function validate(array $data, bool $isUpdate = false): void
    {
        // entity_id مطلوب عند الإنشاء
        if (!$isUpdate) {
            if (!isset($data['entity_id']) || !is_numeric($data['entity_id'])) {
                throw new InvalidArgumentException("Field 'entity_id' is required and must be numeric.");
            }
        }

        // التحقق من session_id
        if (isset($data['session_id']) && strlen((string)$data['session_id']) > 255) {
            throw new InvalidArgumentException("session_id must be at most 255 chars.");
        }

        // التحقق من device_id
        if (isset($data['device_id']) && strlen((string)$data['device_id']) > 255) {
            throw new InvalidArgumentException("device_id must be at most 255 chars.");
        }

        // التحقق من ip_address
        if (isset($data['ip_address']) && strlen((string)$data['ip_address']) > 45) {
            throw new InvalidArgumentException("ip_address must be at most 45 chars.");
        }

        // التحقق من currency_code
        if (isset($data['currency_code']) && strlen((string)$data['currency_code']) > 8) {
            throw new InvalidArgumentException("currency_code must be at most 8 chars.");
        }

        // التحقق من coupon_code
        if (isset($data['coupon_code']) && strlen((string)$data['coupon_code']) > 100) {
            throw new InvalidArgumentException("coupon_code must be at most 100 chars.");
        }

        // التحقق من status
        $validStatuses = ['active', 'abandoned', 'converted', 'expired'];
        if (isset($data['status']) && !in_array($data['status'], $validStatuses, true)) {
            throw new InvalidArgumentException("status must be one of: " . implode(', ', $validStatuses));
        }

        // التحقق من القيم الرقمية
        foreach (['total_items', 'loyalty_points_used'] as $intField) {
            if (isset($data[$intField]) && !is_numeric($data[$intField])) {
                throw new InvalidArgumentException("$intField must be numeric.");
            }
        }

        // التحقق من القيم العشرية
        foreach (['subtotal', 'tax_amount', 'shipping_cost', 'discount_amount', 'total_amount'] as $decField) {
            if (isset($data[$decField]) && !is_numeric($data[$decField])) {
                throw new InvalidArgumentException("$decField must be numeric.");
            }
        }
    }
}
