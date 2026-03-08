<?php
/**
 * TORO — v1/modules/Products/DTO/CreateProductDTO.php
 */
declare(strict_types=1);

final class CreateProductDTO
{
    public function __construct(
        public readonly string  $sku,
        public readonly int     $brandId,
        public readonly ?int    $categoryId   = null,
        public readonly string  $type         = 'simple',
        public readonly float   $basePrice    = 0.0,
        public readonly ?float  $salePrice    = null,
        public readonly int     $stockQty     = 0,
        public readonly ?int    $weightGrams  = null,
        public readonly ?string $thumbnail    = null,
        public readonly bool    $isFeatured   = false,
        public readonly bool    $isActive     = true,
        public readonly int     $sortOrder    = 0,
        /** @var array<array{lang: string, name: string, ...}> */
        public readonly array   $translations = [],
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(
            sku:          strtoupper(trim($d['sku'] ?? '')),
            brandId:      (int)($d['brand_id'] ?? 0),
            categoryId:   isset($d['category_id']) ? (int)$d['category_id'] : null,
            type:         $d['type']         ?? 'simple',
            basePrice:    (float)($d['base_price']   ?? 0),
            salePrice:    isset($d['sale_price']) ? (float)$d['sale_price'] : null,
            stockQty:     (int)($d['stock_qty']   ?? 0),
            weightGrams:  isset($d['weight_grams']) ? (int)$d['weight_grams'] : null,
            thumbnail:    $d['thumbnail']    ?? null,
            isFeatured:   (bool)($d['is_featured'] ?? false),
            isActive:     (bool)($d['is_active']   ?? true),
            sortOrder:    (int)($d['sort_order']    ?? 0),
            translations: $d['translations'] ?? [],
        );
    }
}
