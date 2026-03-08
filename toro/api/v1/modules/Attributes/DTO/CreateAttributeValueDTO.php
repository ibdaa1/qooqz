<?php
/**
 * TORO — v1/modules/Attributes/DTO/CreateAttributeValueDTO.php
 */
declare(strict_types=1);

final class CreateAttributeValueDTO
{
    public function __construct(
        public readonly int     $attributeId,
        public readonly string  $slug,
        public readonly ?string $colorHex   = null,
        public readonly int     $sortOrder  = 0,
        /** @var array<array{lang: string, name: string}> */
        public readonly array   $translations = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            attributeId:  (int)($data['attribute_id'] ?? 0),
            slug:         trim(strtolower($data['slug'] ?? '')),
            colorHex:     $data['color_hex']  ?? null,
            sortOrder:    (int)($data['sort_order'] ?? 0),
            translations: $data['translations'] ?? [],
        );
    }
}
