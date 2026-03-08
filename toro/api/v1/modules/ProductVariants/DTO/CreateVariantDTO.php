<?php
/**
 * TORO — v1/modules/ProductVariants/DTO/CreateVariantDTO.php
 */
declare(strict_types=1);

final class CreateVariantDTO
{
    public function __construct(
        public readonly int     $productId,
        public readonly int     $sizeId,
        public readonly string  $sku,
        public readonly float   $price,
        public readonly ?float  $salePrice  = null,
        public readonly int     $stockQty   = 0,
        public readonly bool    $isActive   = true,
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(
            productId:  (int)($d['product_id'] ?? 0),
            sizeId:     (int)($d['size_id']    ?? 0),
            sku:        strtoupper(trim($d['sku'] ?? '')),
            price:      (float)($d['price']    ?? 0),
            salePrice:  isset($d['sale_price']) ? (float)$d['sale_price'] : null,
            stockQty:   (int)($d['stock_qty']  ?? 0),
            isActive:   (bool)($d['is_active'] ?? true),
        );
    }
}
