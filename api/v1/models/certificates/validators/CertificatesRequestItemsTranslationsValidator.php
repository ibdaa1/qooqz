<?php
declare(strict_types=1);

use InvalidArgumentException;

final class CertificatesRequestItemsTranslationsValidator
{
    public function validate(array $data, bool $isUpdate = false): void
    {
        $required = ['item_id', 'language_code'];
        
        if (!$isUpdate) {
            foreach ($required as $field) {
                if (!isset($data[$field]) || $data[$field] === '') {
                    throw new InvalidArgumentException("Field '$field' is required.");
                }
            }
        } else {
             if (empty($data['id'])) {
                throw new InvalidArgumentException("Field 'id' is required for update.");
            }
        }

        // Validate item_id
        if (isset($data['item_id']) && !is_numeric($data['item_id'])) {
            throw new InvalidArgumentException("Field 'item_id' must be an integer.");
        }

        // Validate language_code length
        if (isset($data['language_code']) && strlen($data['language_code']) > 8) {
            throw new InvalidArgumentException("Field 'language_code' must be at most 8 characters.");
        }

        // Validate name length
        if (isset($data['name']) && strlen((string)$data['name']) > 255) {
            throw new InvalidArgumentException("Field 'name' must be at most 255 characters.");
        }
    }
}