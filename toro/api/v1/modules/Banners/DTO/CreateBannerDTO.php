<?php
/**
 * TORO — v1/modules/Banners/DTO/CreateBannerDTO.php
 */
declare(strict_types=1);

final class CreateBannerDTO
{
    public function __construct(
        public readonly string  $position,
        public readonly ?string $linkUrl    = null,
        public readonly int     $sortOrder  = 0,
        public readonly ?string $startsAt   = null,
        public readonly ?string $endsAt     = null,
        public readonly bool    $isActive   = true,
        public readonly array   $translations = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            position:     trim($data['position'] ?? ''),
            linkUrl:      $data['link_url']   ?? null,
            sortOrder:    (int)($data['sort_order'] ?? 0),
            startsAt:     $data['starts_at']  ?? null,
            endsAt:       $data['ends_at']    ?? null,
            isActive:     (bool)($data['is_active'] ?? true),
            translations: $data['translations'] ?? [],
        );
    }
}
