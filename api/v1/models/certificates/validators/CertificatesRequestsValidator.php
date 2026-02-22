<?php
declare(strict_types=1);

final class CertificatesRequestsValidator
{
    private const VALID_SHIPMENT_CONDITIONS_INT   = [1, 2, 3];
    private const VALID_SHIPMENT_CONDITIONS_LABEL = ['chilled', 'dry', 'frozen'];
    private const VALID_PAYMENT_STATUSES = ['unpaid', 'waiting', 'paid', 'rejected'];

    public static function validate(array $data, bool $isUpdate = false): array
    {
        $errors = [];

        if ($isUpdate && empty($data['id'])) {
            $errors['id'] = 'id is required for update.';
        }

        // الحقول المطلوبة عند الإدراج
        if (!$isUpdate) {
            $required = ['entity_id', 'importer_name', 'importer_address', 'certificate_id'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    $errors[$field] = "$field is required.";
                }
            }
        }

        // التحقق من الحقول الرقمية
        $numericFields = [
            'entity_id', 'importer_country_id', 'issued_id',
            'certificate_id', 'certificate_edition_id', 'auditor_user_id'
        ];
        foreach ($numericFields as $field) {
            if (isset($data[$field]) && $data[$field] !== '' && !is_numeric($data[$field])) {
                $errors[$field] = "$field must be numeric.";
            }
        }

        // القيم المسموحة للحقول النصية
        if (isset($data['certificate_type']) && !in_array($data['certificate_type'], ['gcc', 'non_gcc'], true)) {
            $errors['certificate_type'] = 'Invalid certificate_type. Allowed: gcc, non_gcc.';
        }

        if (isset($data['operation_type']) && !in_array($data['operation_type'], ['export', 're_export'], true)) {
            $errors['operation_type'] = 'Invalid operation_type. Allowed: export, re_export.';
        }

        if (isset($data['transport_method']) && !in_array($data['transport_method'], ['sea', 'land', 'air'], true)) {
            $errors['transport_method'] = 'Invalid transport_method. Allowed: sea, land, air.';
        }

        if (isset($data['status']) && !in_array($data['status'], ['draft','under_review','payment_pending','approved','rejected','issued'], true)) {
            $errors['status'] = 'Invalid status.';
        }

        if (isset($data['payment_status']) && !in_array($data['payment_status'], self::VALID_PAYMENT_STATUSES, true)) {
            $errors['payment_status'] = 'Invalid payment_status. Allowed: ' . implode(', ', self::VALID_PAYMENT_STATUSES);
        }

        // shipment_condition
        if (isset($data['shipment_condition']) && $data['shipment_condition'] !== '' && $data['shipment_condition'] !== null) {
            $val = $data['shipment_condition'];
            $validInt   = is_numeric($val) && in_array((int)$val, self::VALID_SHIPMENT_CONDITIONS_INT, true);
            $validLabel = in_array(strtolower((string)$val), self::VALID_SHIPMENT_CONDITIONS_LABEL, true);
            if (!$validInt && !$validLabel) {
                $errors['shipment_condition'] = 'Invalid shipment_condition. Allowed: 1 (Chilled), 2 (Dry), 3 (Frozen) or labels.';
            }
        }

        // تاريخ الإصدار
        if (!empty($data['issue_date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['issue_date'])) {
            $errors['issue_date'] = 'Invalid date format for issue_date, expected YYYY-MM-DD.';
        }

        // أطوال الحقول النصية
        if (isset($data['importer_name']) && strlen($data['importer_name']) > 255) {
            $errors['importer_name'] = 'importer_name must not exceed 255 characters.';
        }

        return $errors;
    }
}