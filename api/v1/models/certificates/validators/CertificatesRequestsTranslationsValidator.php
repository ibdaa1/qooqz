<?php
declare(strict_types=1);

use InvalidArgumentException;

final class CertificatesRequestsTranslationsValidator
{
    public function validate(array $data, bool $isUpdate = false): void
    {
        $required = ['request_id', 'language_code'];
        
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

        // Validate request_id
        if (isset($data['request_id']) && !is_numeric($data['request_id'])) {
            throw new InvalidArgumentException("Field 'request_id' must be an integer.");
        }

        // Validate language_code length
        if (isset($data['language_code']) && strlen($data['language_code']) > 8) {
            throw new InvalidArgumentException("Field 'language_code' must be at most 8 characters.");
        }
    }
}