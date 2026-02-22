<?php
declare(strict_types=1);

final class CertificatesProductsValidator
{
    private const VALID_WEIGHT_UNITS    = ['kg', 'g', 'ml', 'ton', 'pcs'];
    private const VALID_SAMPLE_STATUSES = ['normal', 'tested', 'rejected'];
    private const VALID_CONDITIONS      = ['chilled', 'frozen', 'dry'];

    public function validate(array $data, bool $isUpdate = false): void
    {
        if (!$isUpdate) {
            $required = ['tenant_id', 'entity_id', 'origin_country_id'];
            foreach ($required as $field) {
                if (empty($data[$field]) && $data[$field] !== 0) {
                    throw new InvalidArgumentException("Field '$field' is required.");
                }
            }
        } else {
            if (empty($data['id'])) {
                throw new InvalidArgumentException('ID is required for update.');
            }
            // في حالة التحديث، origin_country_id مطلوب أيضاً (لأننا نعتمد على إرسال الكائن الكامل)
            if (!isset($data['origin_country_id']) || !is_numeric($data['origin_country_id'])) {
                throw new InvalidArgumentException('origin_country_id is required for update and must be numeric.');
            }
        }

        // التحقق من النوع الرقمي للحقول الرقمية
        if (isset($data['tenant_id']) && !is_numeric($data['tenant_id'])) {
            throw new InvalidArgumentException("tenant_id must be numeric.");
        }
        if (isset($data['entity_id']) && !is_numeric($data['entity_id'])) {
            throw new InvalidArgumentException("entity_id must be numeric.");
        }
        if (isset($data['brand_id']) && $data['brand_id'] !== null && $data['brand_id'] !== '' && !is_numeric($data['brand_id'])) {
            throw new InvalidArgumentException("brand_id must be numeric.");
        }
        if (isset($data['origin_country_id']) && !is_numeric($data['origin_country_id'])) {
            throw new InvalidArgumentException("origin_country_id must be numeric.");
        }

        // طول الكود
        if (isset($data['entity_product_code']) && strlen($data['entity_product_code']) > 100) {
            throw new InvalidArgumentException("entity_product_code must be at most 100 chars.");
        }

        // الوزن رقماً
        if (isset($data['net_weight']) && !is_numeric($data['net_weight'])) {
            throw new InvalidArgumentException("net_weight must be numeric.");
        }

        // وحدات الوزن
        if (isset($data['weight_unit']) && !in_array($data['weight_unit'], self::VALID_WEIGHT_UNITS, true)) {
            throw new InvalidArgumentException(
                "weight_unit must be one of: " . implode(', ', self::VALID_WEIGHT_UNITS)
            );
        }

        // حالة العينة
        if (isset($data['sample_status']) && !in_array($data['sample_status'], self::VALID_SAMPLE_STATUSES, true)) {
            throw new InvalidArgumentException(
                "sample_status must be one of: " . implode(', ', self::VALID_SAMPLE_STATUSES)
            );
        }

        // حالة المنتج
        if (isset($data['product_condition']) && !in_array($data['product_condition'], self::VALID_CONDITIONS, true)) {
            throw new InvalidArgumentException(
                "product_condition must be one of: " . implode(', ', self::VALID_CONDITIONS)
            );
        }
    }
}