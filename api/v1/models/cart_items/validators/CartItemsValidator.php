<?php
declare(strict_types=1);

namespace App\Models\CartItems\Validators;

use InvalidArgumentException;

final class CartItemsValidator
{
    public function validate(array $data, bool $isUpdate = false): void
    {
        // الحقول المطلوبة عند الإنشاء
        if (!$isUpdate) {
            if (!isset($data['cart_id']) || !is_numeric($data['cart_id'])) {
                throw new InvalidArgumentException("Field 'cart_id' is required and must be numeric.");
            }
            if (!isset($data['product_id']) || !is_numeric($data['product_id'])) {
                throw new InvalidArgumentException("Field 'product_id' is required and must be numeric.");
            }
            if (!isset($data['entity_id']) || !is_numeric($data['entity_id'])) {
                throw new InvalidArgumentException("Field 'entity_id' is required and must be numeric.");
            }
            if (empty($data['product_name'])) {
                throw new InvalidArgumentException("Field 'product_name' is required.");
            }
            if (empty($data['sku'])) {
                throw new InvalidArgumentException("Field 'sku' is required.");
            }
            if (!isset($data['unit_price']) || !is_numeric($data['unit_price'])) {
                throw new InvalidArgumentException("Field 'unit_price' is required and must be numeric.");
            }
            if (!isset($data['subtotal']) || !is_numeric($data['subtotal'])) {
                throw new InvalidArgumentException("Field 'subtotal' is required and must be numeric.");
            }
            if (!isset($data['total']) || !is_numeric($data['total'])) {
                throw new InvalidArgumentException("Field 'total' is required and must be numeric.");
            }
        }

        // التحقق من product_name
        if (isset($data['product_name']) && strlen((string)$data['product_name']) > 500) {
            throw new InvalidArgumentException("product_name must be at most 500 chars.");
        }

        // التحقق من sku
        if (isset($data['sku']) && strlen((string)$data['sku']) > 100) {
            throw new InvalidArgumentException("sku must be at most 100 chars.");
        }

        // التحقق من currency_code
        if (isset($data['currency_code']) && strlen((string)$data['currency_code']) > 8) {
            throw new InvalidArgumentException("currency_code must be at most 8 chars.");
        }

        // التحقق من quantity
        if (isset($data['quantity'])) {
            if (!is_numeric($data['quantity']) || (int)$data['quantity'] < 1) {
                throw new InvalidArgumentException("quantity must be a positive integer.");
            }
        }

        // التحقق من is_gift
        if (isset($data['is_gift']) && !in_array((int)$data['is_gift'], [0, 1], true)) {
            throw new InvalidArgumentException("is_gift must be 0 or 1.");
        }

        // التحقق من القيم العشرية
        foreach (['unit_price', 'sale_price', 'discount_amount', 'tax_rate', 'tax_amount', 'subtotal', 'total'] as $decField) {
            if (isset($data[$decField]) && !is_numeric($data[$decField])) {
                throw new InvalidArgumentException("$decField must be numeric.");
            }
        }
    }
}
