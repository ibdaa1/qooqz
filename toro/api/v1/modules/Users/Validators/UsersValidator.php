<?php
/**
 * TORO — v1/modules/Users/Validators/UsersValidator.php
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\ValidationException;

final class UsersValidator
{
    public static function create(array $data): void
    {
        $errors = [];

        $firstName = trim($data['first_name'] ?? '');
        $lastName  = trim($data['last_name']  ?? '');
        $email     = trim($data['email']      ?? '');
        $password  = $data['password'] ?? null;

        if ($firstName === '') $errors['first_name'] = 'الاسم الأول مطلوب';
        if ($lastName  === '') $errors['last_name']  = 'الاسم الأخير مطلوب';
        if ($email     === '') {
            $errors['email'] = 'البريد الإلكتروني مطلوب';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'البريد الإلكتروني غير صالح';
        }
        if ($password !== null && strlen(trim($password)) < 8) {
            $errors['password'] = 'كلمة المرور يجب أن تكون 8 أحرف على الأقل';
        }

        if (!empty($errors)) throw new ValidationException($errors);
    }

    public static function update(array $data): void
    {
        $errors = [];

        if (array_key_exists('first_name', $data) && trim($data['first_name']) === '') {
            $errors['first_name'] = 'الاسم الأول لا يمكن أن يكون فارغاً';
        }
        if (array_key_exists('last_name', $data) && trim($data['last_name']) === '') {
            $errors['last_name'] = 'الاسم الأخير لا يمكن أن يكون فارغاً';
        }
        if (array_key_exists('email', $data)) {
            $email = trim($data['email']);
            if ($email === '') {
                $errors['email'] = 'البريد الإلكتروني لا يمكن أن يكون فارغاً';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'البريد الإلكتروني غير صالح';
            }
        }
        if (array_key_exists('password', $data) && strlen(trim($data['password'])) < 8) {
            $errors['password'] = 'كلمة المرور يجب أن تكون 8 أحرف على الأقل';
        }

        if (!empty($errors)) throw new ValidationException($errors);
    }
}
