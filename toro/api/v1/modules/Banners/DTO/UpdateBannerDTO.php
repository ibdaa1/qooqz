<?php
/**
 * TORO — v1/modules/Banners/DTO/UpdateBannerDTO.php
 */
declare(strict_types=1);

final class UpdateBannerDTO
{
    public function __construct(
        public readonly ?string $position    = null,
        public readonly ?string $linkUrl     = null,
        public readonly ?int    $sortOrder   = null,
        public readonly ?string $startsAt    = null,
        public readonly ?string $endsAt      = null,
        public readonly ?bool   $isActive    = null,
        public readonly ?array  $translations = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            position:     isset($data['position'])   ? trim($data['position'])  : null,
            linkUrl:      $data['link_url']  ?? null,
            sortOrder:    isset($data['sort_order'])  ? (int)$data['sort_order'] : null,
            startsAt:     $data['starts_at'] ?? null,
            endsAt:       $data['ends_at']   ?? null,
            isActive:     isset($data['is_active'])   ? (bool)$data['is_active'] : null,
            translations: $data['translations'] ?? null,
        );
    }
}
