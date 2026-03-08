<?php
/**
 * TORO — v1/modules/Categories/DTO/CreateCategoryDTO.php
 */
declare(strict_types=1);

final class CreateCategoryDTO
{
    public function __construct(
        public readonly string  $slug,
        public readonly ?int    $parentId   = null,
        public readonly ?string $image      = null,
        public readonly int     $sortOrder  = 0,
        public readonly bool    $isActive   = true,
        /** @var array<array{lang: string, name: string, description?: string, meta_title?: string, meta_desc?: string}> */
        public readonly array   $translations = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            slug:         trim(strtolower($data['slug'] ?? '')),
            parentId:     isset($data['parent_id']) ? (int)$data['parent_id'] : null,
            image:        $data['image'] ?? null,
            sortOrder:    (int)($data['sort_order'] ?? 0),
            isActive:     (bool)($data['is_active'] ?? true),
            translations: $data['translations'] ?? [],
        );
    }
}
