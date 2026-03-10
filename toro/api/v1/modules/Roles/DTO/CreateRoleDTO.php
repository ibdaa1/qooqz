<?php
/**
 * TORO — v1/modules/Roles/DTO/CreateRoleDTO.php
 */
declare(strict_types=1);

final class CreateRoleDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $slug,
        public readonly ?string $description = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name:        trim($data['name'] ?? ''),
            slug:        trim(strtolower($data['slug'] ?? '')),
            description: isset($data['description']) ? trim($data['description']) : null,
        );
    }
}