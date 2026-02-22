<?php
declare(strict_types=1);

final class CertificatesProductsTranslationsValidator
{
    // الحقول المعروفة — brand ليس منها
    private const KNOWN_FIELDS = ['id', 'product_id', 'language_code', 'name'];

    public function validate(array $data, bool $isUpdate = false): void
    {
        // أي حقول غير معروفة (مثل brand) تُتجاهل — لا تُسبب خطأ
        if (!$isUpdate) {
            if (empty($data['product_id'])) {
                throw new InvalidArgumentException("product_id is required.");
            }
            if (empty($data['language_code'])) {
                throw new InvalidArgumentException("language_code is required.");
            }
            if (!isset($data['name']) || trim((string)$data['name']) === '') {
                throw new InvalidArgumentException("name is required.");
            }
        }

        if (isset($data['product_id']) && !is_numeric($data['product_id'])) {
            throw new InvalidArgumentException("product_id must be numeric.");
        }

        if (isset($data['language_code']) && strlen((string)$data['language_code']) > 8) {
            throw new InvalidArgumentException("language_code max 8 chars.");
        }

        if (isset($data['name'])) {
            if (trim((string)$data['name']) === '') {
                throw new InvalidArgumentException("name cannot be empty.");
            }
            if (strlen((string)$data['name']) > 255) {
                throw new InvalidArgumentException("name max 255 chars.");
            }
        }
    }
}