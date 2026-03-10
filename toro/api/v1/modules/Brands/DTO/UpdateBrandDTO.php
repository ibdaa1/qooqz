<?php
/**
 * TORO — v1/modules/Brands/DTO/UpdateBrandDTO.php
 */
declare(strict_types=1);

final class UpdateBrandDTO
{
    public function __construct(
        public readonly ?string $slug       = null,
        public readonly ?string $website    = null,
        public readonly ?int    $sortOrder  = null,
        public readonly ?bool   $isActive   = null,
        /** @var array<array{lang: string, name: string, description?: string}>|null */
        public readonly ?array  $translations = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            slug:         isset($data['slug'])       ? trim(strtolower($data['slug'])) : null,
            website:      $data['website']           ?? null,
            sortOrder:    isset($data['sort_order']) ? (int)$data['sort_order']        : null,
            isActive:     isset($data['is_active'])  ? (bool)$data['is_active']        : null,
            translations: $data['translations']      ?? null,
        );
    }
}
