<?php
/**
 * TORO — v1/modules/UserAddresses/Validators/UserAddressesValidator.php
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\ValidationException;

final class UserAddressesValidator
{
    public static function create(array $data): void
    {
        $errors = [];

        if (empty($data['full_name']))   $errors['full_name']   = 'الاسم الكامل مطلوب';
        if (empty($data['phone']))       $errors['phone']       = 'رقم الهاتف مطلوب';
        if (empty($data['country_id']))  $errors['country_id']  = 'الدولة مطلوبة';
        if (empty($data['city_id']))     $errors['city_id']     = 'المدينة مطلوبة';
        if (empty($data['address_line1'])) $errors['address_line1'] = 'العنوان الأول مطلوب';

        if (!empty($errors)) throw new ValidationException($errors);
    }

    public static function update(array $data): void
    {
        $errors = [];

        if (array_key_exists('full_name', $data)     && trim($data['full_name']) === '')     $errors['full_name']   = 'الاسم الكامل لا يمكن أن يكون فارغاً';
        if (array_key_exists('phone', $data)         && trim($data['phone']) === '')         $errors['phone']       = 'رقم الهاتف لا يمكن أن يكون فارغاً';
        if (array_key_exists('country_id', $data)    && (int)$data['country_id'] < 1)       $errors['country_id']  = 'الدولة غير صالحة';
        if (array_key_exists('city_id', $data)       && (int)$data['city_id'] < 1)          $errors['city_id']     = 'المدينة غير صالحة';
        if (array_key_exists('address_line1', $data) && trim($data['address_line1']) === '') $errors['address_line1'] = 'العنوان الأول لا يمكن أن يكون فارغاً';

        if (!empty($errors)) throw new ValidationException($errors);
    }
}
