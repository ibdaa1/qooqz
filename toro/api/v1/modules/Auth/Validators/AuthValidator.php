<?php
/**
 * TORO — v1/modules/Auth/Validators/AuthValidator.php
 */
declare(strict_types=1);
namespace V1\Modules\Auth\Validators;

use Shared\Domain\Exceptions\ValidationException;

final class AuthValidator
{
    // ── Login ────────────────────────────────────────────────
    public static function login(array $data): void
    {
        $e = [];
        if (empty($data['email']))    $e['email']    = 'البريد الإلكتروني مطلوب';
        elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL))
                                      $e['email']    = 'البريد الإلكتروني غير صالح';
        if (empty($data['password'])) $e['password'] = 'كلمة المرور مطلوبة';
        if ($e) throw new ValidationException('بيانات الدخول غير صحيحة', $e);
    }

    // ── Register ─────────────────────────────────────────────
    public static function register(array $data): void
    {
        $e = [];
        if (empty($data['first_name']) || strlen(trim($data['first_name'])) < 2)
            $e['first_name'] = 'الاسم الأول مطلوب (حرفان على الأقل)';

        if (empty($data['last_name']) || strlen(trim($data['last_name'])) < 2)
            $e['last_name'] = 'اسم العائلة مطلوب (حرفان على الأقل)';

        if (empty($data['email']))
            $e['email'] = 'البريد الإلكتروني مطلوب';
        elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL))
            $e['email'] = 'البريد الإلكتروني غير صالح';

        if (empty($data['password']))
            $e['password'] = 'كلمة المرور مطلوبة';
        elseif (strlen($data['password']) < 8)
            $e['password'] = 'كلمة المرور يجب أن تكون 8 أحرف على الأقل';
        elseif (!preg_match('/[A-Z]/', $data['password']))
            $e['password'] = 'كلمة المرور يجب أن تحتوي على حرف كبير';
        elseif (!preg_match('/[0-9]/', $data['password']))
            $e['password'] = 'كلمة المرور يجب أن تحتوي على رقم';

        if (!empty($data['phone']) && !preg_match('/^\+?[0-9]{8,15}$/', $data['phone']))
            $e['phone'] = 'رقم الهاتف غير صالح';

        if ($e) throw new ValidationException('بيانات التسجيل غير صحيحة', $e);
    }

    // ── Change Password ──────────────────────────────────────
    public static function changePassword(array $data): void
    {
        $e = [];
        if (empty($data['current_password'])) $e['current_password'] = 'كلمة المرور الحالية مطلوبة';
        if (empty($data['new_password']))      $e['new_password']     = 'كلمة المرور الجديدة مطلوبة';
        elseif (strlen($data['new_password']) < 8)
            $e['new_password'] = 'كلمة المرور الجديدة يجب أن تكون 8 أحرف على الأقل';
        if ($e) throw new ValidationException('بيانات غير صحيحة', $e);
    }

    // ── Forgot Password ──────────────────────────────────────
    public static function forgotPassword(array $data): void
    {
        $e = [];
        if (empty($data['email']))
            $e['email'] = 'البريد الإلكتروني مطلوب';
        elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL))
            $e['email'] = 'البريد الإلكتروني غير صالح';
        if ($e) throw new ValidationException('بيانات غير صحيحة', $e);
    }

    // ── OAuth ────────────────────────────────────────────────
    public static function oauth(array $data): void
    {
        $e = [];
        if (empty($data['token']) && empty($data['access_token']))
            $e['token'] = 'رمز OAuth مطلوب';
        if ($e) throw new ValidationException('بيانات OAuth غير صحيحة', $e);
    }
}
