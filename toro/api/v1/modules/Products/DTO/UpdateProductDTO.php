<?php
/**
 * TORO — v1/modules/Products/DTO/UpdateProductDTO.php
 */
declare(strict_types=1);

final class UpdateProductDTO
{
    public function __construct(
        public readonly ?string $sku          = null,
        public readonly ?int    $brandId      = null,
        public readonly ?int    $categoryId   = null,
        public readonly ?string $type         = null,
        public readonly ?float  $basePrice    = null,
        public readonly ?float  $salePrice    = null,
        public readonly ?int    $stockQty     = null,
        public readonly ?int    $weightGrams  = null,
        public readonly ?string $thumbnail    = null,
        public readonly ?bool   $isFeatured   = null,
        public readonly ?bool   $isActive     = null,
        public readonly ?int    $sortOrder    = null,
        /** @var array<array{lang: string, name: string, ...}>|null */
        public readonly ?array  $translations = null,
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(
            sku:          isset($d['sku'])          ? strtoupper(trim($d['sku']))  : null,
            brandId:      isset($d['brand_id'])     ? (int)$d['brand_id']         : null,
            categoryId:   isset($d['category_id'])  ? (int)$d['category_id']      : null,
            type:         $d['type']               ?? null,
            basePrice:    isset($d['base_price'])  ? (float)$d['base_price']      : null,
            salePrice:    isset($d['sale_price'])  ? (float)$d['sale_price']      : null,
            stockQty:     isset($d['stock_qty'])   ? (int)$d['stock_qty']         : null,
            weightGrams:  isset($d['weight_grams']) ? (int)$d['weight_grams']     : null,
            thumbnail:    $d['thumbnail']          ?? null,
            isFeatured:   isset($d['is_featured']) ? (bool)$d['is_featured']      : null,
            isActive:     isset($d['is_active'])   ? (bool)$d['is_active']        : null,
            sortOrder:    isset($d['sort_order'])  ? (int)$d['sort_order']        : null,
            translations: $d['translations']       ?? null,
        );
    }
}
