<?php
declare(strict_types=1);

final class CertificateReceiptAllocationsValidator
{
    public function validate(array $data, bool $isUpdate = false): void
    {
        if (!$isUpdate) {
            $required = ['receipt_id', 'certificate_id', 'fee_id', 'allocated_amount'];
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

        // Validate numeric fields
        $numericFields = ['receipt_id', 'certificate_id', 'fee_id', 'allocated_amount'];
        foreach ($numericFields as $field) {
            if (isset($data[$field]) && $data[$field] !== '' && !is_numeric($data[$field])) {
                throw new InvalidArgumentException("Field '$field' must be numeric.");
            }
        }

        // allocated_amount must be non-negative
        if (isset($data['allocated_amount']) && (float)$data['allocated_amount'] < 0) {
            throw new InvalidArgumentException("allocated_amount must be a non-negative number.");
        }
    }
}