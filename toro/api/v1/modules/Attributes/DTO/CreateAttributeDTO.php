<?php
/**
 * TORO — v1/modules/Attributes/DTO/CreateAttributeDTO.php
 */
declare(strict_types=1);

final class CreateAttributeDTO
{
    public function __construct(
        public readonly string $slug,
        public readonly string $type       = 'select',
        public readonly int    $sortOrder  = 0,
        public readonly bool   $isActive   = true,
        /** @var array<array{lang: string, name: string}> */
        public readonly array  $translations = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            slug:         trim(strtolower($data['slug'] ?? '')),
            type:         $data['type']       ?? 'select',
            sortOrder:    (int)($data['sort_order'] ?? 0),
            isActive:     (bool)($data['is_active'] ?? true),
            translations: $data['translations'] ?? [],
        );
    }
}
