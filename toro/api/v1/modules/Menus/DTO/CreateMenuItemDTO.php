<?php
/**
 * TORO — v1/modules/Menus/DTO/CreateMenuItemDTO.php
 */
declare(strict_types=1);

final class CreateMenuItemDTO
{
    public function __construct(
        public readonly int     $menuId,
        public readonly ?int    $parentId    = null,
        public readonly string  $type        = 'link',
        public readonly ?int    $referenceId = null,
        public readonly ?string $url         = null,
        public readonly ?string $icon        = null,
        public readonly string  $target      = '_self',
        public readonly int     $sortOrder   = 0,
        public readonly bool    $isActive    = true,
        /** @var array<array{language_id: int, label: string, tooltip?: string}> */
        public readonly array   $translations = [],
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(
            menuId:       (int)($d['menu_id']      ?? 0),
            parentId:     isset($d['parent_id'])    ? (int)$d['parent_id']    : null,
            type:         $d['type']               ?? 'link',
            referenceId:  isset($d['reference_id']) ? (int)$d['reference_id'] : null,
            url:          isset($d['url'])           ? trim($d['url'])         : null,
            icon:         isset($d['icon'])          ? trim($d['icon'])        : null,
            target:       in_array($d['target'] ?? '', ['_blank']) ? '_blank' : '_self',
            sortOrder:    (int)($d['sort_order']    ?? 0),
            isActive:     (bool)($d['is_active']    ?? true),
            translations: $d['translations']        ?? [],
        );
    }
}
