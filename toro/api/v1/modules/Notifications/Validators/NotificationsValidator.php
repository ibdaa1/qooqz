<?php
/**
 * TORO — v1/modules/Notifications/Validators/NotificationsValidator.php
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\ValidationException;

final class NotificationsValidator
{
    private const CHANNELS = ['email', 'sms', 'push'];

    public static function template(array $data): void
    {
        $errors = [];

        if (empty($data['slug']))    $errors['slug']    = 'رمز القالب مطلوب';
        if (empty($data['channel'])) $errors['channel'] = 'القناة مطلوبة';

        if (!empty($data['channel']) && !in_array($data['channel'], self::CHANNELS)) {
            $errors['channel'] = 'القناة غير صالحة (email, sms, push)';
        }

        if ($errors) throw new ValidationException($errors);
    }

    public static function templateTranslation(array $data): void
    {
        $errors = [];

        if (!isset($data['language_id'])) $errors['language_id'] = 'معرف اللغة مطلوب';
        if (empty($data['body']))          $errors['body']        = 'نص الرسالة مطلوب';

        if ($errors) throw new ValidationException($errors);
    }

    public static function log(array $data): void
    {
        $errors = [];

        if (empty($data['channel']))    $errors['channel']   = 'القناة مطلوبة';
        if (empty($data['recipient']))  $errors['recipient'] = 'المستلم مطلوب';

        if (!empty($data['channel']) && !in_array($data['channel'], self::CHANNELS)) {
            $errors['channel'] = 'القناة غير صالحة';
        }

        if ($errors) throw new ValidationException($errors);
    }
}
