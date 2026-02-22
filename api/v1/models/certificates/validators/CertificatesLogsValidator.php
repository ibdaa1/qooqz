<?php name=api/v1/models/certificates/validators/CertificatesLogsValidator.php
declare(strict_types=1);

final class CertificatesLogsValidator
{
    public static function validate(array $data): array
    {
        $errors = [];
        if (empty($data['request_id']) || !is_numeric($data['request_id'])) $errors['request_id'] = 'request_id required';
        if (empty($data['user_id']) || !is_numeric($data['user_id'])) $errors['user_id'] = 'user_id required';
        $allowed = ['create','update','approve','audit','payment_sent','issue','reject'];
        if (empty($data['action_type']) || !in_array($data['action_type'], $allowed, true)) $errors['action_type'] = 'Invalid action_type';
        return $errors;
    }
}