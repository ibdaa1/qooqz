<?php
declare(strict_types=1);

final class AdPlacementsValidator
{
    private array $errors = [];

    public function validate(array $data, string $scenario = 'create'): bool
    {
        $this->errors = [];

        if ($scenario === 'update') {
            if (empty($data['id']) || !is_numeric($data['id'])) {
                $this->errors[] = 'ID is required for update';
            }
        }

        if ($scenario === 'create') {
            if (empty($data['name']) || trim((string)$data['name']) === '') {
                $this->errors[] = "Field 'name' is required";
            }
            if (empty($data['placement_key']) || trim((string)$data['placement_key']) === '') {
                $this->errors[] = "Field 'placement_key' is required";
            }
        }

        if (!empty($data['name']) && mb_strlen((string)$data['name']) > 255) {
            $this->errors[] = 'Name must not exceed 255 characters';
        }

        if (!empty($data['placement_key'])) {
            if (mb_strlen((string)$data['placement_key']) > 100) {
                $this->errors[] = 'Placement key must not exceed 100 characters';
            }
            if (!preg_match('/^[a-z0-9_\-]+$/i', (string)$data['placement_key'])) {
                $this->errors[] = 'Placement key may only contain letters, numbers, underscores and hyphens';
            }
        }

        if (!empty($data['status']) && !in_array($data['status'], ['active', 'inactive'], true)) {
            $this->errors[] = "Status must be 'active' or 'inactive'";
        }

        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
