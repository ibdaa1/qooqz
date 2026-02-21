<?php
declare(strict_types=1);

final class NotificationsValidator
{
    public function validate(array $data, bool $isUpdate = false): void
    {
        if (!$isUpdate) {
            if (empty($data['user_id'])) {
                throw new InvalidArgumentException('Field "user_id" is required.');
            }
            if (empty($data['title'])) {
                throw new InvalidArgumentException('Field "title" is required.');
            }
            if (empty($data['message'])) {
                throw new InvalidArgumentException('Field "message" is required.');
            }
        } else {
            if (empty($data['id'])) {
                throw new InvalidArgumentException('Field "id" is required for update.');
            }
        }

        // Validate numeric fields
        if (isset($data['user_id']) && !is_numeric($data['user_id'])) {
            throw new InvalidArgumentException('Field "user_id" must be numeric.');
        }
        if (isset($data['entity_id']) && $data['entity_id'] !== '' && !is_numeric($data['entity_id'])) {
            throw new InvalidArgumentException('Field "entity_id" must be numeric.');
        }
        if (isset($data['notification_type_id']) && $data['notification_type_id'] !== '' && !is_numeric($data['notification_type_id'])) {
            throw new InvalidArgumentException('Field "notification_type_id" must be numeric.');
        }

        // is_read must be 0 or 1 if provided
        if (isset($data['is_read']) && !in_array((int)$data['is_read'], [0, 1], true)) {
            throw new InvalidArgumentException('Field "is_read" must be 0 or 1.');
        }

        // Title max 500
        if (isset($data['title']) && strlen($data['title']) > 500) {
            throw new InvalidArgumentException('Field "title" must not exceed 500 characters.');
        }

        // message and data are mediumtext/longtext, no length checks.
    }
}