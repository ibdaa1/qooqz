<?php
/**
 * TORO — v1/modules/Images/Validators/ImageTypesValidator.php
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\ValidationException;

final class ImageTypesValidator
{
    private const VALID_CROPS    = ['fit', 'fill', 'cover'];
    private const VALID_FORMATS  = ['jpg', 'png', 'webp'];
    private const CODE_PATTERN   = '/^[a-z0-9]+(?:_[a-z0-9]+)*$/';

    public static function create(array $d): void
    {
        $e = [];

        if (empty($d['code'])) {
            $e['code'] = 'الـ code مطلوب';
        } elseif (!preg_match(self::CODE_PATTERN, strtolower($d['code']))) {
            $e['code'] = 'الـ code يجب أن يكون أحرفاً صغيرة وأرقاماً وشرطات سفلية فقط';
        }

        if (empty($d['name'])) {
            $e['name'] = 'الاسم مطلوب';
        }

        if (empty($d['width']) || (int)$d['width'] <= 0) {
            $e['width'] = 'العرض مطلوب ويجب أن يكون أكبر من صفر';
        }

        if (empty($d['height']) || (int)$d['height'] <= 0) {
            $e['height'] = 'الارتفاع مطلوب ويجب أن يكون أكبر من صفر';
        }

        if (isset($d['crop']) && !in_array($d['crop'], self::VALID_CROPS, true)) {
            $e['crop'] = 'قيمة crop غير صحيحة. القيم المتاحة: ' . implode(', ', self::VALID_CROPS);
        }

        if (isset($d['format']) && !in_array($d['format'], self::VALID_FORMATS, true)) {
            $e['format'] = 'صيغة الصورة غير صحيحة. القيم المتاحة: ' . implode(', ', self::VALID_FORMATS);
        }

        if (isset($d['quality'])) {
            $q = (int)$d['quality'];
            if ($q < 1 || $q > 100) {
                $e['quality'] = 'جودة الصورة يجب أن تكون بين 1 و 100';
            }
        }

        if ($e) throw new ValidationException('بيانات نوع الصورة غير صحيحة', $e);
    }

    public static function update(array $d): void
    {
        $e = [];

        if (isset($d['code']) && !preg_match(self::CODE_PATTERN, strtolower($d['code']))) {
            $e['code'] = 'الـ code يجب أن يكون أحرفاً صغيرة وأرقاماً وشرطات سفلية فقط';
        }

        if (isset($d['width']) && (int)$d['width'] <= 0) {
            $e['width'] = 'العرض يجب أن يكون أكبر من صفر';
        }

        if (isset($d['height']) && (int)$d['height'] <= 0) {
            $e['height'] = 'الارتفاع يجب أن يكون أكبر من صفر';
        }

        if (isset($d['crop']) && !in_array($d['crop'], self::VALID_CROPS, true)) {
            $e['crop'] = 'قيمة crop غير صحيحة. القيم المتاحة: ' . implode(', ', self::VALID_CROPS);
        }

        if (isset($d['format']) && !in_array($d['format'], self::VALID_FORMATS, true)) {
            $e['format'] = 'صيغة الصورة غير صحيحة. القيم المتاحة: ' . implode(', ', self::VALID_FORMATS);
        }

        if (isset($d['quality'])) {
            $q = (int)$d['quality'];
            if ($q < 1 || $q > 100) {
                $e['quality'] = 'جودة الصورة يجب أن تكون بين 1 و 100';
            }
        }

        if ($e) throw new ValidationException('بيانات التحديث غير صحيحة', $e);
    }
}
