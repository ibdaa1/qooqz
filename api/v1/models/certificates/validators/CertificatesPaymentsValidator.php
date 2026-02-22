<?php
declare(strict_types=1);

final class CertificatesPaymentsValidator
{
    private const ALLOWED_PAYMENT_TYPES = ['initial', 'correction', 'reissue'];
    private const ALLOWED_VERIFICATION_STATUSES = ['waiting_verification', 'verified', 'rejected'];
    private const ALLOWED_CURRENCIES = ['AED', 'USD', 'SAR']; // expand as needed

    public function validate(array $data, bool $isUpdate = false): void
    {
        if (!$isUpdate) {
            $required = ['request_id', 'entity_id', 'payment_reference', 'payment_date', 'amount'];
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

        // request_id (numeric)
        if (isset($data['request_id']) && !is_numeric($data['request_id'])) {
            throw new InvalidArgumentException("request_id must be numeric.");
        }

        // entity_id (numeric)
        if (isset($data['entity_id']) && !is_numeric($data['entity_id'])) {
            throw new InvalidArgumentException("entity_id must be numeric.");
        }

        // payment_type (enum)
        if (isset($data['payment_type']) && !in_array($data['payment_type'], self::ALLOWED_PAYMENT_TYPES, true)) {
            throw new InvalidArgumentException("payment_type must be one of: " . implode(', ', self::ALLOWED_PAYMENT_TYPES));
        }

        // payment_reference (required, max 150)
        if (isset($data['payment_reference'])) {
            if (strlen($data['payment_reference']) > 150) {
                throw new InvalidArgumentException("payment_reference must not exceed 150 characters.");
            }
        }

        // payment_date format (Y-m-d H:i:s)
        if (isset($data['payment_date']) && !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $data['payment_date'])) {
            throw new InvalidArgumentException("payment_date must be in format YYYY-MM-DD HH:MM:SS.");
        }

        // amount numeric positive
        if (isset($data['amount'])) {
            if (!is_numeric($data['amount']) || (float)$data['amount'] < 0) {
                throw new InvalidArgumentException("amount must be a non-negative number.");
            }
        }

        // currency
        if (isset($data['currency']) && $data['currency'] !== '') {
            if (strlen($data['currency']) !== 3) {
                throw new InvalidArgumentException("currency must be a 3-character code.");
            }
            if (!in_array(strtoupper($data['currency']), self::ALLOWED_CURRENCIES, true)) {
                throw new InvalidArgumentException("currency must be one of: " . implode(', ', self::ALLOWED_CURRENCIES));
            }
        }

        // verification_status enum
        if (isset($data['verification_status']) && !in_array($data['verification_status'], self::ALLOWED_VERIFICATION_STATUSES, true)) {
            throw new InvalidArgumentException("verification_status must be one of: " . implode(', ', self::ALLOWED_VERIFICATION_STATUSES));
        }

        // verified_by numeric
        if (isset($data['verified_by']) && $data['verified_by'] !== '' && !is_numeric($data['verified_by'])) {
            throw new InvalidArgumentException("verified_by must be numeric.");
        }

        // verified_at format (Y-m-d H:i:s) if provided
        if (isset($data['verified_at']) && $data['verified_at'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $data['verified_at'])) {
            throw new InvalidArgumentException("verified_at must be in format YYYY-MM-DD HH:MM:SS.");
        }

        // receipt_file max 500
        if (isset($data['receipt_file']) && strlen($data['receipt_file']) > 500) {
            throw new InvalidArgumentException("receipt_file must not exceed 500 characters.");
        }
    }
}