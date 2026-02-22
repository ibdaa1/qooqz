<?php
declare(strict_types=1);

use InvalidArgumentException;

final class CertificatesVersionsValidator
{
    private const ALLOWED_REASONS = ['entity_error', 'auditor_error', 'system_update'];

    public function validate(array $data, bool $isUpdate = false): void
    {
        $required = ['request_id', 'version_number', 'version_reason'];
        
        if (!$isUpdate) {
            foreach ($required as $field) {
                if (!isset($data[$field]) || $data[$field'] === '') {
                    throw new InvalidArgumentException("Field '$field' is required.");
                }
            }
        } else {
             if (empty($data['id'])) {
                throw new InvalidArgumentException("Field 'id' is required for update.");
            }
        }

        // Validate Enum
        if (isset($data['version_reason']) && !in_array($data['version_reason'], self::ALLOWED_REASONS, true)) {
            throw new InvalidArgumentException("Field 'version_reason' must be one of: " . implode(', ', self::ALLOWED_REASONS));
        }

        // Validate IDs (Integers)
        foreach (['request_id', 'replaced_version_id', 'auditor_user_id', 'municipality_official_id', 'approved_by'] as $idField) {
            if (isset($data[$idField]) && $data[$idField] !== '' && !is_numeric($data[$idField])) {
                throw new InvalidArgumentException("Field '$idField' must be an integer.");
            }
        }

        // Validate Boolean/TinyInt
        if (isset($data['is_active']) && !in_array((int)$data['is_active'], [0, 1], true)) {
            throw new InvalidArgumentException("Field 'is_active' must be 0 or 1.");
        }

        // Validate Dates (YYYY-MM-DD or YYYY-MM-DD HH:MM:SS)
        foreach (['approved_at'] as $d) {
            if (!empty($data[$d]) && !preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $data[$d])) {
                throw new InvalidArgumentException("Field '$d' must be a valid datetime.");
            }
        }
    }
}