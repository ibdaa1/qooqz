<?php
declare(strict_types=1);

final class CertificatesCorrectionsValidator
{
    private const ALLOWED_ERROR_SOURCES = ['entity', 'auditor'];
    private const ALLOWED_STATUSES = ['pending', 'approved', 'rejected', 'payment_required', 'completed'];

    public function validate(array $data, bool $isUpdate = false): void
    {
        if (!$isUpdate) {
            $required = ['request_id', 'requested_by', 'correction_reason', 'error_source'];
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

        // Numeric fields
        foreach (['request_id', 'requested_by', 'reviewed_by'] as $field) {
            if (isset($data[$field]) && $data[$field] !== '' && !is_numeric($data[$field])) {
                throw new InvalidArgumentException("Field '$field' must be numeric.");
            }
        }

        // error_source enum
        if (isset($data['error_source']) && !in_array($data['error_source'], self::ALLOWED_ERROR_SOURCES, true)) {
            throw new InvalidArgumentException("error_source must be one of: " . implode(', ', self::ALLOWED_ERROR_SOURCES));
        }

        // status enum
        if (isset($data['status']) && !in_array($data['status'], self::ALLOWED_STATUSES, true)) {
            throw new InvalidArgumentException("status must be one of: " . implode(', ', self::ALLOWED_STATUSES));
        }

        // payment_required, payment_paid should be 0 or 1
        foreach (['payment_required', 'payment_paid'] as $field) {
            if (isset($data[$field]) && $data[$field] !== '') {
                if (!in_array((int)$data[$field], [0, 1], true)) {
                    throw new InvalidArgumentException("$field must be 0 or 1.");
                }
            }
        }

        // reviewed_at format if provided (Y-m-d H:i:s)
        if (isset($data['reviewed_at']) && $data['reviewed_at'] !== '') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $data['reviewed_at'])) {
                throw new InvalidArgumentException("reviewed_at must be in format YYYY-MM-DD HH:MM:SS.");
            }
        }

        // correction_reason is text, no length limit defined, but we can check it's not empty (already required)
    }
}