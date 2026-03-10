<?php
/**
 * TORO — v1/modules/ProductVariants/Validators/ProductVariantsValidator.php
 */
declare(strict_types=1);

final class ProductVariantsValidator
{
    private const SKU_PATTERN = '/^[A-Z0-9_\-]{2,80}$/';

    public function validateCreate(array $data): array
    {
        $errors = [];

        if (empty($data['product_id']) || (int)$data['product_id'] < 1) {
            $errors[] = 'product_id is required and must be a positive integer';
        }
        if (empty($data['size_id']) || (int)$data['size_id'] < 1) {
            $errors[] = 'size_id is required and must be a positive integer';
        }
        $sku = strtoupper(trim($data['sku'] ?? ''));
        if ($sku === '' || !preg_match(self::SKU_PATTERN, $sku)) {
            $errors[] = 'sku is required and must match ' . self::SKU_PATTERN;
        }
        if (!isset($data['price']) || (float)$data['price'] < 0) {
            $errors[] = 'price is required and must be >= 0';
        }

        return $errors;
    }

    public function validateUpdate(array $data): array
    {
        $errors = [];

        if (isset($data['sku'])) {
            $sku = strtoupper(trim($data['sku']));
            if (!preg_match(self::SKU_PATTERN, $sku)) {
                $errors[] = 'sku must match ' . self::SKU_PATTERN;
            }
        }
        if (isset($data['price']) && (float)$data['price'] < 0) {
            $errors[] = 'price must be >= 0';
        }

        return $errors;
    }
}
