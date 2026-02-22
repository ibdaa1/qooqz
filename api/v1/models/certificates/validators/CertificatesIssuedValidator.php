<?php
declare(strict_types=1);

final class CertificatesIssuedValidator
{
    /**
     * قيم مسموحة لـ language_code (يمكن توسيعها)
     */
    private const ALLOWED_LANGUAGES = ['ar', 'en', 'fr'];

    public function validate(array $data, bool $isUpdate = false): void
    {
        // الحقول المطلوبة للإدراج
        $required = [
            'version_id',
            'certificate_number',
            'issued_at',
            'printable_until',
            'verification_code',
            'pdf_path',
            'issued_by',
            'language_code'
        ];

        if (!$isUpdate) {
            foreach ($required as $field) {
                if (!isset($data[$field]) || $data[$field] === '') {
                    throw new InvalidArgumentException("الحقل '$field' مطلوب.");
                }
            }
        } else {
            if (empty($data['id'])) {
                throw new InvalidArgumentException("الحقل 'id' مطلوب للتحديث.");
            }
        }

        // التحقق من أنواع البيانات

        // version_id يجب أن يكون رقماً
        if (isset($data['version_id']) && !is_numeric($data['version_id'])) {
            throw new InvalidArgumentException("version_id يجب أن يكون رقماً.");
        }

        // issued_by يجب أن يكون رقماً
        if (isset($data['issued_by']) && !is_numeric($data['issued_by'])) {
            throw new InvalidArgumentException("issued_by يجب أن يكون رقماً.");
        }

        // التحقق من صحة التواريخ (YYYY-MM-DD HH:MM:SS أو YYYY-MM-DD)
        foreach (['issued_at', 'printable_until', 'cancelled_at'] as $dateField) {
            if (!empty($data[$dateField]) && !preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $data[$dateField])) {
                throw new InvalidArgumentException("الحقل '$dateField' يجب أن يكون تاريخاً صالحاً.");
            }
        }

        // language_code يجب أن يكون من القيم المسموحة
        if (isset($data['language_code']) && !in_array($data['language_code'], self::ALLOWED_LANGUAGES, true)) {
            throw new InvalidArgumentException("language_code يجب أن يكون أحد القيم: " . implode(', ', self::ALLOWED_LANGUAGES));
        }

        // is_cancelled يجب أن يكون 0 أو 1
        if (isset($data['is_cancelled']) && !in_array((int)$data['is_cancelled'], [0, 1], true)) {
            throw new InvalidArgumentException("is_cancelled يجب أن يكون 0 أو 1.");
        }

        // إذا كان is_cancelled = 1، يجب توفير cancelled_reason
        if (!empty($data['is_cancelled']) && empty($data['cancelled_reason'])) {
            throw new InvalidArgumentException("عند الإلغاء يجب توفير سبب الإلغاء (cancelled_reason).");
        }
    }
}