<?php
/**
 * TORO — v1/modules/Images/DTO/CreateImageDTO.php
 */
declare(strict_types=1);

final class CreateImageDTO
{
    public function __construct(
        public readonly ?int    $ownerId      = null,
        public readonly ?int    $imageTypeId  = null,
        public readonly ?int    $userId       = null,
        public readonly ?string $filename     = null,
        public readonly ?string $url          = null,
        public readonly ?string $thumbUrl     = null,
        public readonly ?string $mimeType     = null,
        public readonly ?int    $size         = null,
        public readonly string  $visibility   = 'public',
        public readonly bool    $isMain       = false,
        public readonly int     $sortOrder    = 0,
    ) {}

    public static function fromArray(array $d, int $actorId): self
    {
        return new self(
            ownerId:     isset($d['owner_id'])     ? (int)$d['owner_id']     : null,
            imageTypeId: isset($d['image_type_id']) ? (int)$d['image_type_id'] : null,
            userId:      $actorId,
            filename:    $d['filename']   ?? null,
            url:         $d['url']        ?? null,
            thumbUrl:    $d['thumb_url']  ?? null,
            mimeType:    $d['mime_type']  ?? null,
            size:        isset($d['size']) ? (int)$d['size'] : null,
            visibility:  $d['visibility'] ?? 'public',
            isMain:      (bool)($d['is_main']    ?? false),
            sortOrder:   (int)($d['sort_order'] ?? 0),
        );
    }
}
