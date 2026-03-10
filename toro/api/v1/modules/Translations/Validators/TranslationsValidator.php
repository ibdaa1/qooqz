<?php
/**
 * TORO — v1/modules/Translations/Validators/TranslationsValidator.php
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\ValidationException;

final class TranslationsValidator
{
    private const KEY_PATTERN = '/^[a-zA-Z0-9_.-]+$/'; // يسمح بالنقاط والشرطات السفلية

    // ── Create Key ───────────────────────────────────────────
    public static function createKey(array $data): void
    {
        $e = [];

        if (empty($data['key_name']) && empty($data['key'])) {
            $e['key_name'] = 'اسم المفتاح مطلوب';
        } else {
            $key = $data['key_name'] ?? $data['key'];
            if (!preg_match(self::KEY_PATTERN, $key)) {
                $e['key_name'] = 'المفتاح يمكن أن يحتوي فقط على أحرف وأرقام ونقاط وشرطات سفلية';
            }
        }

        if (isset($data['context']) && strlen($data['context']) > 100) {
            $e['context'] = 'السياق يجب أن لا يتجاوز 100 حرف';
        }

        if ($e) throw new ValidationException('بيانات المفتاح غير صحيحة', $e);
    }

    // ── Update Key ───────────────────────────────────────────
    public static function updateKey(array $data): void
    {
        $e = [];

        if (isset($data['key_name'])) {
            if (empty($data['key_name'])) {
                $e['key_name'] = 'اسم المفتاح لا يمكن أن يكون فارغاً';
            } elseif (!preg_match(self::KEY_PATTERN, $data['key_name'])) {
                $e['key_name'] = 'المفتاح يمكن أن يحتوي فقط على أحرف وأرقام ونقاط وشرطات سفلية';
            }
        }

        if (isset($data['context']) && strlen($data['context']) > 100) {
            $e['context'] = 'السياق يجب أن لا يتجاوز 100 حرف';
        }

        if ($e) throw new ValidationException('بيانات التحديث غير صحيحة', $e);
    }

    // ── Upsert Value ─────────────────────────────────────────
    public static function upsertValue(array $data): void
    {
        $e = [];

        if (empty($data['key_id'])) {
            $e['key_id'] = 'معرف المفتاح مطلوب';
        }

        if (empty($data['language_id'])) {
            $e['language_id'] = 'معرف اللغة مطلوب';
        }

        if (!isset($data['value']) || trim($data['value']) === '') {
            $e['value'] = 'قيمة الترجمة مطلوبة';
        }

        if ($e) throw new ValidationException('بيانات القيمة غير صحيحة', $e);
    }

    // ── Import ───────────────────────────────────────────────
    public static function validateImport(array $data): void
    {
        if (empty($data['language_code'])) {
            throw new ValidationException('كود اللغة مطلوب', ['language_code' => 'مطلوب']);
        }
        if (!isset($data['translations']) || !is_array($data['translations'])) {
            throw new ValidationException('الترجمات يجب أن تكون مصفوفة', ['translations' => 'مصفوفة مطلوبة']);
        }
    }
}