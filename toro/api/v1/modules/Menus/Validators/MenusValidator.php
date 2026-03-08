<?php
/**
 * TORO — v1/modules/Menus/Validators/MenusValidator.php
 */
declare(strict_types=1);

final class MenusValidator
{
    private const SLUG_PATTERN = '/^[a-z0-9_]{2,80}$/';
    private const VALID_TYPES   = ['link', 'category', 'product', 'page', 'custom'];
    private const VALID_TARGETS = ['_self', '_blank'];

    public function validateCreateMenu(array $data): array
    {
        $errors = [];
        $slug   = trim(strtolower($data['slug'] ?? ''));
        if (!preg_match(self::SLUG_PATTERN, $slug)) {
            $errors[] = 'slug is required and must match ' . self::SLUG_PATTERN;
        }
        return $errors;
    }

    public function validateCreateItem(array $data): array
    {
        $errors = [];

        if (empty($data['menu_id']) || (int)$data['menu_id'] < 1) {
            $errors[] = 'menu_id is required';
        }
        if (isset($data['type']) && !in_array($data['type'], self::VALID_TYPES, true)) {
            $errors[] = 'type must be one of: ' . implode(', ', self::VALID_TYPES);
        }
        if (isset($data['target']) && !in_array($data['target'], self::VALID_TARGETS, true)) {
            $errors[] = 'target must be _self or _blank';
        }

        return $errors;
    }

    public function validateUpdateItem(array $data): array
    {
        $errors = [];

        if (isset($data['type']) && !in_array($data['type'], self::VALID_TYPES, true)) {
            $errors[] = 'type must be one of: ' . implode(', ', self::VALID_TYPES);
        }
        if (isset($data['target']) && !in_array($data['target'], self::VALID_TARGETS, true)) {
            $errors[] = 'target must be _self or _blank';
        }

        return $errors;
    }
}
