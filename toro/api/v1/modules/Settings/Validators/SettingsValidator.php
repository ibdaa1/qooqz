<?php

class SettingsValidator
{
    public static function validateUpdate(array $data, string $type)
    {
        $errors = [];

        if (!array_key_exists('value', $data)) {
            $errors['value'] = 'حقل القيمة (value) مطلوب.';
            return $errors;
        }

        $value = $data['value'];

        switch ($type) {
            case 'number':
                if (!is_numeric($value)) {
                    $errors['value'] = 'يجب أن تكون القيمة رقماً.';
                }
                break;

            case 'boolean':
                $validBooleans = [0, 1, '0', '1', true, false];
                if (!in_array($value, $validBooleans, true)) {
                    $errors['value'] = 'يجب أن تكون القيمة منطقية (0 أو 1).';
                }
                break;

            case 'json':
                if (!empty($value)) {
                    json_decode($value);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $errors['value'] = 'صيغة JSON غير صالحة.';
                    }
                }
                break;
        }

        return empty($errors) ? true : $errors;
    }
}