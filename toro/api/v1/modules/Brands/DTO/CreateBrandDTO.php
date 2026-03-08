<?php
/**
 * TORO — v1/modules/Brands/DTO/CreateBrandDTO.php
 */
declare(strict_types=1);

final class CreateBrandDTO
{
    public function __construct(
        public readonly string  $slug,
        public readonly ?string $logo       = null,
        public readonly ?string $website    = null,
        public readonly int     $sortOrder  = 0,
        public readonly bool    $isActive   = true,
        /** @var array<array{lang: string, name: string, description?: string}> */
        public readonly array   $translations = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            slug:         trim(strtolower($data['slug'] ?? '')),
            logo:         $data['logo']       ?? null,
            website:      $data['website']    ?? null,
            sortOrder:    (int)($data['sort_order'] ?? 0),
            isActive:     (bool)($data['is_active'] ?? true),
            translations: $data['translations'] ?? [],
        );
    }
}
