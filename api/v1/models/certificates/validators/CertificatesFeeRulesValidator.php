<?php
declare(strict_types=1);

final class CertificatesFeeRulesValidator
{
    private const ALLOWED_FEE_TYPES = ['issue_certificate']; // يمكن إضافة المزيد لاحقاً

    public function validate(array $data, bool $isUpdate = false): void
    {
        if (!$isUpdate) {
            $required = ['fee_type', 'amount'];
            foreach ($required as $field) {
                if (!isset($data[$field]) || $data[$field] === '') {
                    throw new InvalidArgumentException("Field '$field' is required.");
                }
            }
        } else {
            if (empty($data['id'])) {
                throw new InvalidArgumentException('Field "id" is required for update.');
            }
        }

        // Validate fee_type
        if (isset($data['fee_type']) && !in_array($data['fee_type'], self::ALLOWED_FEE_TYPES, true)) {
            throw new InvalidArgumentException("fee_type must be one of: " . implode(', ', self::ALLOWED_FEE_TYPES));
        }

        // Validate amount: must be numeric and >= 0
        if (isset($data['amount'])) {
            if (!is_numeric($data['amount'])) {
                throw new InvalidArgumentException('amount must be numeric.');
            }
            if ((float)$data['amount'] < 0) {
                throw new InvalidArgumentException('amount must be >= 0.');
            }
        }

        // Validate max_items: must be integer >= 1 or null (optional)
        if (array_key_exists('max_items', $data) && $data['max_items'] !== null && $data['max_items'] !== '') {
            if (!is_numeric($data['max_items']) || (int)$data['max_items'] != $data['max_items']) {
                throw new InvalidArgumentException('max_items must be an integer.');
            }
            if ((int)$data['max_items'] < 1) {
                throw new InvalidArgumentException('max_items must be >= 1 or null.');
            }
        }

        // Validate currency: 3 letters
        if (isset($data['currency']) && !preg_match('/^[A-Z]{3}$/', $data['currency'])) {
            throw new InvalidArgumentException('currency must be a 3-letter uppercase code (e.g., AED).');
        }

        // Validate is_active: must be 0 or 1 if present
        if (isset($data['is_active']) && !in_array((int)$data['is_active'], [0, 1], true)) {
            throw new InvalidArgumentException('is_active must be 0 or 1.');
        }
    }
}