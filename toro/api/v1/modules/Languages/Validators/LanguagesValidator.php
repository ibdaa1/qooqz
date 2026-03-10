<?php
/**
 * TORO — v1/modules/Languages/Validators/LanguagesValidator.php
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\ValidationException;

final class LanguagesValidator
{
    private const ALLOWED_DIRECTIONS = ['ltr', 'rtl'];
    private const CODE_PATTERN = '/^[a-z]{2,10}$/'; // مثلاً en, ar, fr, de, zh-CN (نقبل شرطة لو أردنا)

    // ── Create ───────────────────────────────────────────────
    public static function create(array $data): void
    {
        $e = [];

        // الكود
        if (empty($data['code'])) {
            $e['code'] = 'كود اللغة مطلوب';
        } elseif (!preg_match(self::CODE_PATTERN, $data['code'])) {
            $e['code'] = 'كود اللغة يجب أن يكون حروفاً صغيرة فقط (2-10 أحرف)';
        }

        // الاسم
        if (empty($data['name'])) {
            $e['name'] = 'اسم اللغة مطلوب';
        } elseif (mb_strlen(trim($data['name'])) < 2) {
            $e['name'] = 'اسم اللغة يجب أن يكون حرفين على الأقل';
        }

        // الاسم الأصلي
        if (empty($data['native'])) {
            $e['native'] = 'الاسم الأصلي للغة مطلوب';
        } elseif (mb_strlen(trim($data['native'])) < 2) {
            $e['native'] = 'الاسم الأصلي يجب أن يكون حرفين على الأقل';
        }

        // الاتجاه
        if (!empty($data['direction']) && !in_array($data['direction'], self::ALLOWED_DIRECTIONS)) {
            $e['direction'] = 'الاتجاه يجب أن يكون ltr أو rtl';
        }

        if ($e) throw new ValidationException('بيانات اللغة غير صحيحة', $e);
    }

    // ── Update ───────────────────────────────────────────────
    public static function update(array $data): void
    {
        $e = [];

        if (isset($data['code'])) {
            if (empty($data['code'])) {
                $e['code'] = 'كود اللغة لا يمكن أن يكون فارغاً';
            } elseif (!preg_match(self::CODE_PATTERN, $data['code'])) {
                $e['code'] = 'كود اللغة يجب أن يكون حروفاً صغيرة فقط (2-10 أحرف)';
            }
        }

        if (isset($data['name']) && mb_strlen(trim($data['name'])) < 2) {
            $e['name'] = 'اسم اللغة يجب أن يكون حرفين على الأقل';
        }

        if (isset($data['native']) && mb_strlen(trim($data['native'])) < 2) {
            $e['native'] = 'الاسم الأصلي يجب أن يكون حرفين على الأقل';
        }

        if (isset($data['direction']) && !in_array($data['direction'], self::ALLOWED_DIRECTIONS)) {
            $e['direction'] = 'الاتجاه يجب أن يكون ltr أو rtl';
        }

        if ($e) throw new ValidationException('بيانات التحديث غير صحيحة', $e);
    }
}