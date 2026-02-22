<?php
declare(strict_types=1);

final class CertificatesValidator
{
    /**
     * Validate certificate data.
     *
     * @param array $data
     * @param bool $isUpdate Whether this is an update operation
     * @throws InvalidArgumentException
     */
    public function validate(array $data, bool $isUpdate = false): void
    {
        // For insert: code is required
        if (!$isUpdate) {
            if (empty($data['code'])) {
                throw new InvalidArgumentException('Field "code" is required.');
            }
        } else {
            // For update, id is required
            if (empty($data['id'])) {
                throw new InvalidArgumentException('Field "id" is required for update.');
            }
            // If code is present in update, it must not be empty
            if (isset($data['code']) && trim((string)$data['code']) === '') {
                throw new InvalidArgumentException('Field "code" cannot be empty.');
            }
        }

        // Validate code format: alphanumeric and underscores, max 50 chars
        if (isset($data['code'])) {
            $code = trim((string)$data['code']);
            if (strlen($code) > 50) {
                throw new InvalidArgumentException('Field "code" must not exceed 50 characters.');
            }
            if (!preg_match('/^[A-Za-z0-9_]+$/', $code)) {
                throw new InvalidArgumentException('Field "code" may only contain letters, numbers, and underscores.');
            }
        }

        // Description: optional, max 255 chars
        if (isset($data['description']) && strlen($data['description']) > 255) {
            throw new InvalidArgumentException('Field "description" must not exceed 255 characters.');
        }

        // is_active: must be 0 or 1 if provided
        if (isset($data['is_active']) && !in_array((int)$data['is_active'], [0, 1], true)) {
            throw new InvalidArgumentException('Field "is_active" must be 0 or 1.');
        }
    }
}