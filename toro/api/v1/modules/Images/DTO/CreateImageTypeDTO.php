<?php
/**
 * TORO — v1/modules/Images/DTO/CreateImageTypeDTO.php
 */
declare(strict_types=1);

final class CreateImageTypeDTO
{
    public function __construct(
        public readonly string  $code,
        public readonly string  $name,
        public readonly int     $width,
        public readonly int     $height,
        public readonly ?string $description  = null,
        public readonly string  $crop         = 'cover',
        public readonly int     $quality      = 85,
        public readonly string  $format       = 'webp',
        public readonly bool    $isThumbnail  = false,
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(
            code:        strtolower(trim($d['code'] ?? '')),
            name:        trim($d['name'] ?? ''),
            width:       (int)($d['width']  ?? 0),
            height:      (int)($d['height'] ?? 0),
            description: $d['description'] ?? null,
            crop:        $d['crop']        ?? 'cover',
            quality:     (int)($d['quality'] ?? 85),
            format:      $d['format']      ?? 'webp',
            isThumbnail: (bool)($d['is_thumbnail'] ?? false),
        );
    }
}
