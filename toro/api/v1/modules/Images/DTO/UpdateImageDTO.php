<?php
/**
 * TORO — v1/modules/Images/DTO/UpdateImageDTO.php
 */
declare(strict_types=1);

final class UpdateImageDTO
{
    public function __construct(
        public readonly ?int    $ownerId      = null,
        public readonly ?int    $imageTypeId  = null,
        public readonly ?string $filename     = null,
        public readonly ?string $url          = null,
        public readonly ?string $thumbUrl     = null,
        public readonly ?string $mimeType     = null,
        public readonly ?int    $size         = null,
        public readonly ?string $visibility   = null,
        public readonly ?bool   $isMain       = null,
        public readonly ?int    $sortOrder    = null,
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(
            ownerId:     isset($d['owner_id'])     ? (int)$d['owner_id']     : null,
            imageTypeId: isset($d['image_type_id']) ? (int)$d['image_type_id'] : null,
            filename:    $d['filename']  ?? null,
            url:         $d['url']       ?? null,
            thumbUrl:    $d['thumb_url'] ?? null,
            mimeType:    $d['mime_type'] ?? null,
            size:        isset($d['size'])       ? (int)$d['size']       : null,
            visibility:  $d['visibility']        ?? null,
            isMain:      isset($d['is_main'])    ? (bool)$d['is_main']    : null,
            sortOrder:   isset($d['sort_order']) ? (int)$d['sort_order'] : null,
        );
    }
}
