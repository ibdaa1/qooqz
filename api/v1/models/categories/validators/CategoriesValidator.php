<?php
declare(strict_types=1);

/**
 * api/v1/models/categories/validators/CategoriesValidator.php
 */
final class CategoriesValidator
{
    public function validateCreate(array $data): array
    {
        $errors = [];
        if (empty($data['name']) && empty($data['slug'])) {
            $errors['name_or_slug'] = 'Name or slug is required';
        }
        return $errors;
    }

    public function validateUpdate(array $data): array
    {
        $errors = [];
        if (!isset($data['id'])) {
            $errors['id'] = 'ID is required for update';
        }
        return $errors;
    }
}
