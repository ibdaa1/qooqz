<?php
/**
 * TORO — v1/modules/Categories/DTO/UpdateCategoryDTO.php
 */
declare(strict_types=1);
namespace V1\Modules\Categories\DTO;

final class UpdateCategoryDTO
{
    public function __construct(
        public readonly ?string $slug       = null,
        public readonly ?int    $parentId   = null,
        public readonly ?string $image      = null,
        public readonly ?int    $sortOrder  = null,
        public readonly ?bool   $isActive   = null,
        /** @var array<array{lang: string, name: string, description?: string, meta_title?: string, meta_desc?: string}>|null */
        public readonly ?array  $translations = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            slug:         isset($data['slug'])       ? trim(strtolower($data['slug'])) : null,
            parentId:     isset($data['parent_id'])  ? (int)$data['parent_id']         : null,
            image:        $data['image']             ?? null,
            sortOrder:    isset($data['sort_order']) ? (int)$data['sort_order']         : null,
            isActive:     isset($data['is_active'])  ? (bool)$data['is_active']         : null,
            translations: $data['translations']      ?? null,
        );
    }
}
