<?php
declare(strict_types=1);

final class CertificateEditionsValidator
{
    private const ALLOWED_SCOPES = ['gcc', 'non_gcc'];
    private const ALLOWED_VERSIONS = ['ar_gcc', 'ar_non_gcc', 'en_gcc', 'en_non_gcc'];
    private const ALLOWED_LANGUAGES = ['ar', 'en']; // extend as needed

    public function validate(array $data, bool $isUpdate = false): void
    {
        if (!$isUpdate) {
            $required = ['certificate_id', 'language_code', 'scope', 'certificate_version'];
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

        // certificate_id must be numeric
        if (isset($data['certificate_id']) && !is_numeric($data['certificate_id'])) {
            throw new InvalidArgumentException('certificate_id must be numeric.');
        }

        // code: optional, but if provided, check length and format (alphanumeric + underscore)
        if (isset($data['code']) && $data['code'] !== '') {
            if (strlen($data['code']) > 50) {
                throw new InvalidArgumentException('code must not exceed 50 characters.');
            }
            if (!preg_match('/^[A-Za-z0-9_]+$/', $data['code'])) {
                throw new InvalidArgumentException('code may only contain letters, numbers, and underscores.');
            }
        }

        // language_code: required, length <=8, and in allowed list? We'll just check format.
        if (isset($data['language_code'])) {
            if (strlen($data['language_code']) > 8) {
                throw new InvalidArgumentException('language_code must not exceed 8 characters.');
            }
            // optional: check against a predefined list, but we can be flexible.
            // if (!in_array($data['language_code'], self::ALLOWED_LANGUAGES, true)) {
            //     throw new InvalidArgumentException('language_code must be one of: ' . implode(', ', self::ALLOWED_LANGUAGES));
            // }
        }

        // scope enum
        if (isset($data['scope']) && !in_array($data['scope'], self::ALLOWED_SCOPES, true)) {
            throw new InvalidArgumentException('scope must be one of: ' . implode(', ', self::ALLOWED_SCOPES));
        }

        // certificate_version enum
        if (isset($data['certificate_version']) && !in_array($data['certificate_version'], self::ALLOWED_VERSIONS, true)) {
            throw new InvalidArgumentException('certificate_version must be one of: ' . implode(', ', self::ALLOWED_VERSIONS));
        }

        // is_active: must be 0 or 1 if provided
        if (isset($data['is_active']) && $data['is_active'] !== '') {
            if (!in_array((int)$data['is_active'], [0, 1], true)) {
                throw new InvalidArgumentException('is_active must be 0 or 1.');
            }
        }
    }
}