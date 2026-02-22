<?php
declare(strict_types=1);

final class MunicipalityOfficialsValidator
{
    public function validate(array $data, bool $isUpdate = false): void
    {
        if (!$isUpdate) {
            // name is required
            if (empty($data['name'])) {
                throw new InvalidArgumentException('Field "name" is required.');
            }
        } else {
            if (empty($data['id'])) {
                throw new InvalidArgumentException('Field "id" is required for update.');
            }
        }

        // Validate name length (max 255)
        if (isset($data['name']) && strlen($data['name']) > 255) {
            throw new InvalidArgumentException('Field "name" must not exceed 255 characters.');
        }

        // Validate position length (max 255) if provided
        if (isset($data['position']) && strlen($data['position']) > 255) {
            throw new InvalidArgumentException('Field "position" must not exceed 255 characters.');
        }
    }
}