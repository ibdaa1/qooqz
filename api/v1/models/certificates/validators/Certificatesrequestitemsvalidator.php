<?php
declare(strict_types=1);

final class CertificatesRequestItemsValidator
{
    public static function validate(array $data, bool $isUpdate = false): array
    {
        $errors = [];

        if ($isUpdate && empty($data['id'])) {
            $errors['id'] = 'id is required for update.';
        }

        if (empty($data['request_id']) || !is_numeric($data['request_id'])) {
            $errors['request_id'] = 'request_id is required and must be numeric.';
        }

        if (empty($data['product_id']) || !is_numeric($data['product_id'])) {
            $errors['product_id'] = 'product_id is required and must be numeric.';
        }

        if (!isset($data['quantity']) || $data['quantity'] === '' || !is_numeric($data['quantity'])) {
            $errors['quantity'] = 'quantity is required and must be numeric.';
        } elseif ((float)$data['quantity'] <= 0) {
            $errors['quantity'] = 'quantity must be greater than zero.';
        }

        if (isset($data['net_weight']) && $data['net_weight'] !== '' && !is_numeric($data['net_weight'])) {
            $errors['net_weight'] = 'net_weight must be numeric.';
        }

        if (isset($data['weight_unit_id']) && $data['weight_unit_id'] !== '' && !is_numeric($data['weight_unit_id'])) {
            $errors['weight_unit_id'] = 'weight_unit_id must be numeric.';
        }

        foreach (['production_date', 'expiry_date'] as $dateField) {
            if (!empty($data[$dateField]) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data[$dateField])) {
                $errors[$dateField] = "Invalid date format for {$dateField}, expected YYYY-MM-DD.";
            }
        }

        // تحقق منطقي: تاريخ الإنتاج قبل تاريخ الانتهاء
        if (!empty($data['production_date']) && !empty($data['expiry_date'])) {
            if ($data['production_date'] >= $data['expiry_date']) {
                $errors['expiry_date'] = 'expiry_date must be after production_date.';
            }
        }

        return $errors;
    }
}