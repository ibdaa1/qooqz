<?php
/**
 * TORO — v1/modules/Menus/DTO/CreateMenuDTO.php
 */
declare(strict_types=1);

final class CreateMenuDTO
{
    public function __construct(
        public readonly string $slug,
        public readonly bool   $isActive = true,
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(
            slug:     trim(strtolower($d['slug'] ?? '')),
            isActive: (bool)($d['is_active'] ?? true),
        );
    }
}
