<?php
/**
 * TORO — v1/modules/Roles/DTO/UpdatePermissionDTO.php
 */
declare(strict_types=1);

final class UpdatePermissionDTO
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $slug = null,
        public readonly ?string $group = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name:  isset($data['name'])  ? trim($data['name'])             : null,
            slug:  isset($data['slug'])  ? trim(strtolower($data['slug'])) : null,
            group: isset($data['group']) ? trim($data['group'])            : null,
        );
    }
}