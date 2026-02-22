<?php
declare(strict_types=1);

final class CertificatesAuditsValidator
{
    private const VALID_STATUSES = ['pending', 'approved', 'rejected'];

    public function validate(array $data, bool $isUpdate = false): void
    {
        if (!$isUpdate) {
            $required = ['request_id', 'auditor_id', 'audit_date', 'status'];
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

        // Validate numeric fields (request_id, assigned_by)
        foreach (['request_id', 'assigned_by'] as $field) {
            if (isset($data[$field]) && $data[$field] !== '' && !is_numeric($data[$field])) {
                throw new InvalidArgumentException("Field '$field' must be numeric.");
            }
        }

        // Special validation for auditor_id: must be a positive integer
        if (isset($data['auditor_id']) && $data['auditor_id'] !== '') {
            if (!is_numeric($data['auditor_id']) || (int)$data['auditor_id'] <= 0) {
                throw new InvalidArgumentException("Field 'auditor_id' must be a positive integer (greater than 0).");
            }
        } elseif (!$isUpdate) {
            // If auditor_id is missing in insert, it's already caught by required check above.
            // This is just a safety net.
        }

        // Validate status
        if (isset($data['status']) && !in_array($data['status'], self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException(
                "Field 'status' must be one of: " . implode(', ', self::VALID_STATUSES)
            );
        }

        // Validate audit_date format (Y-m-d H:i:s)
        if (isset($data['audit_date']) && !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $data['audit_date'])) {
            throw new InvalidArgumentException("Field 'audit_date' must be in format YYYY-MM-DD HH:MM:SS.");
        }

        // Validate assigned_at if provided
        if (isset($data['assigned_at']) && !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $data['assigned_at'])) {
            throw new InvalidArgumentException("Field 'assigned_at' must be in format YYYY-MM-DD HH:MM:SS.");
        }
    }
}