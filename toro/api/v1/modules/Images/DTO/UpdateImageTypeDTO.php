<?php
/**
 * TORO — v1/modules/Images/DTO/UpdateImageTypeDTO.php
 */
declare(strict_types=1);

final class UpdateImageTypeDTO
{
    public function __construct(
        public readonly ?string $code         = null,
        public readonly ?string $name         = null,
        public readonly ?int    $width        = null,
        public readonly ?int    $height       = null,
        public readonly ?string $description  = null,
        public readonly ?string $crop         = null,
        public readonly ?int    $quality      = null,
        public readonly ?string $format       = null,
        public readonly ?bool   $isThumbnail  = null,
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(
            code:        isset($d['code'])   ? strtolower(trim($d['code'])) : null,
            name:        isset($d['name'])   ? trim($d['name'])             : null,
            width:       isset($d['width'])  ? (int)$d['width']             : null,
            height:      isset($d['height']) ? (int)$d['height']            : null,
            description: $d['description']  ?? null,
            crop:        $d['crop']         ?? null,
            quality:     isset($d['quality']) ? (int)$d['quality']          : null,
            format:      $d['format']        ?? null,
            isThumbnail: isset($d['is_thumbnail']) ? (bool)$d['is_thumbnail'] : null,
        );
    }
}
