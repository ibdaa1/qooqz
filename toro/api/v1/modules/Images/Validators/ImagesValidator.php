<?php
/**
 * TORO — v1/modules/Images/Validators/ImagesValidator.php
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\ValidationException;

final class ImagesValidator
{
    private const VALID_VISIBILITY = ['private', 'public'];
    private const ALLOWED_MIME     = [
        'image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml',
    ];
    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10 MB

    public static function upload(array $file): void
    {
        $e = [];

        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $e['file'] = 'الملف مطلوب';
        } else {
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $e['file'] = 'فشل رفع الملف (كود الخطأ: ' . $file['error'] . ')';
            } else {
                $mime = mime_content_type($file['tmp_name']) ?: '';
                if (!in_array($mime, self::ALLOWED_MIME, true)) {
                    $e['file'] = 'نوع الملف غير مدعوم. الأنواع المتاحة: JPEG, PNG, WebP, GIF, SVG';
                }

                if ($file['size'] > self::MAX_FILE_SIZE) {
                    $e['file'] = 'حجم الملف يتجاوز الحد الأقصى (10 ميجابايت)';
                }
            }
        }

        if ($e) throw new ValidationException('ملف الصورة غير صالح', $e);
    }

    public static function create(array $d): void
    {
        $e = [];

        if (empty($d['url']) && empty($d['filename'])) {
            $e['url'] = 'رابط الصورة أو اسم الملف مطلوب';
        }

        if (isset($d['visibility']) && !in_array($d['visibility'], self::VALID_VISIBILITY, true)) {
            $e['visibility'] = 'قيمة الظهور غير صحيحة. القيم المتاحة: private, public';
        }

        if ($e) throw new ValidationException('بيانات الصورة غير صحيحة', $e);
    }

    public static function update(array $d): void
    {
        $e = [];

        if (isset($d['visibility']) && !in_array($d['visibility'], self::VALID_VISIBILITY, true)) {
            $e['visibility'] = 'قيمة الظهور غير صحيحة. القيم المتاحة: private, public';
        }

        if ($e) throw new ValidationException('بيانات التحديث غير صحيحة', $e);
    }
}
