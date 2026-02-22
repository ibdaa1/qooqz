<?php
declare(strict_types=1);

final class CertificatesTemplatesValidator
{
    public static function validate(array $data, bool $isUpdate = false): array
    {
        $errors = [];

        if ($isUpdate && empty($data['id'])) {
            $errors['id'] = 'id is required for update';
        }

        // الحقول الإلزامية
        if (empty($data['code'])) {
            $errors['code'] = 'code is required';
        } elseif (strlen($data['code']) > 50) {
            $errors['code'] = 'code max length is 50';
        }

        if (empty($data['name'])) {
            $errors['name'] = 'name is required';
        } elseif (strlen($data['name']) > 150) {
            $errors['name'] = 'name max length is 150';
        }

        if (empty($data['language_code'])) {
            $errors['language_code'] = 'language_code is required';
        } elseif (strlen((string)$data['language_code']) > 8) {
            $errors['language_code'] = 'language_code max length is 8';
        }

        if (empty($data['html_template'])) {
            $errors['html_template'] = 'html_template is required';
        }

        // التحقق من القيم المسموحة
        if (isset($data['paper_size']) && !in_array($data['paper_size'], ['A4', 'A5'], true)) {
            $errors['paper_size'] = 'paper_size must be A4 or A5';
        }

        if (isset($data['orientation']) && !in_array($data['orientation'], ['portrait', 'landscape'], true)) {
            $errors['orientation'] = 'orientation must be portrait or landscape';
        }

        if (isset($data['is_active']) && !in_array((int)$data['is_active'], [0, 1], true)) {
            $errors['is_active'] = 'is_active must be 0 or 1';
        }

        // التحقق من الحقول الرقمية (إحداثيات وأبعاد)
        $numericFields = [
            'logo_x', 'logo_y', 'logo_width', 'logo_height',
            'stamp_x', 'stamp_y', 'stamp_width', 'stamp_height',
            'signature_x', 'signature_y', 'signature_width', 'signature_height',
            'qr_x', 'qr_y', 'qr_width', 'qr_height',
            'table_start_x', 'table_start_y', 'table_row_height', 'table_max_rows',
            'font_size'
        ];
        foreach ($numericFields as $field) {
            if (isset($data[$field]) && $data[$field] !== '') {
                if (!is_numeric($data[$field])) {
                    $errors[$field] = "$field must be numeric";
                }
                // تحقق إضافي: الأبعاد لا يمكن أن تكون سالبة (اختياري)
                if (in_array($field, ['logo_width', 'logo_height', 'stamp_width', 'stamp_height', 'signature_width', 'signature_height', 'qr_width', 'qr_height', 'table_row_height', 'font_size']) && (float)$data[$field] < 0) {
                    $errors[$field] = "$field must be non-negative";
                }
            }
        }

        // table_max_rows يجب أن يكون عدداً صحيحاً موجباً
        if (isset($data['table_max_rows']) && $data['table_max_rows'] !== '') {
            if (!is_numeric($data['table_max_rows']) || (int)$data['table_max_rows'] < 0) {
                $errors['table_max_rows'] = 'table_max_rows must be a non-negative integer';
            }
        }

        // background_image (اختياري) – حد أقصى 500
        if (isset($data['background_image']) && strlen($data['background_image']) > 500) {
            $errors['background_image'] = 'background_image must not exceed 500 characters';
        }

        // font_family – حد أقصى 100
        if (isset($data['font_family']) && strlen($data['font_family']) > 100) {
            $errors['font_family'] = 'font_family must not exceed 100 characters';
        }

        return $errors;
    }
}