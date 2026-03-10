<?php
/**
 * TORO — v1/modules/Roles/DTO/CreatePermissionDTO.php
 */
declare(strict_types=1);

final class CreatePermissionDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $slug,
        public readonly ?string $group = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name:  trim($data['name'] ?? ''),
            slug:  trim(strtolower($data['slug'] ?? '')),
            group: isset($data['group']) ? trim($data['group']) : null,
        );
    }
}