<?php
declare(strict_types=1);

use Shared\Domain\Exceptions\ValidationException;

class SettingsValidator
{
    // ── Create ─────────────────────────────────────────────────
    public static function create(array $data): void
    {
        $e = [];

        if (empty($data['key'])) {
            $e['key'] = 'المفتاح (key) مطلوب';
        } elseif (!preg_match('/^[a-z0-9_]+$/', $data['key'])) {
            $e['key'] = 'المفتاح يجب أن يحتوي على أحرف صغيرة وأرقام وشرطات سفلية فقط';
        }

        if (!empty($data['type'])) {
            $validTypes = ['string', 'number', 'boolean', 'json'];
            if (!in_array($data['type'], $validTypes, true)) {
                $e['type'] = 'النوع يجب أن يكون: ' . implode(' | ', $validTypes);
            }
        }

        if (isset($data['type']) && $data['type'] === 'json' && !empty($data['value'])) {
            json_decode($data['value']);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $e['value'] = 'صيغة JSON غير صالحة';
            }
        }

        if ($e) throw new ValidationException('بيانات الإعداد غير صحيحة', $e);
    }

    // ── Update ─────────────────────────────────────────────────
    public static function update(array $data, string $type = 'string'): void
    {
        $e = [];

        if (!array_key_exists('value', $data)) {
            $e['value'] = 'حقل القيمة (value) مطلوب';
        } elseif ($type === 'number' && !is_numeric($data['value'])) {
            $e['value'] = 'يجب أن تكون القيمة رقماً';
        } elseif ($type === 'boolean' && !in_array($data['value'], [0, 1, '0', '1', true, false], true)) {
            $e['value'] = 'يجب أن تكون القيمة منطقية (0 أو 1)';
        } elseif ($type === 'json' && !empty($data['value'])) {
            json_decode($data['value']);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $e['value'] = 'صيغة JSON غير صالحة';
            }
        }

        if ($e) throw new ValidationException('بيانات التحديث غير صحيحة', $e);
    }
}
