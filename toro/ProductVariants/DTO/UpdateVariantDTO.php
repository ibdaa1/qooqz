<?php
/**
 * TORO — v1/modules/ProductVariants/DTO/UpdateVariantDTO.php
 */
declare(strict_types=1);

final class UpdateVariantDTO
{
    public function __construct(
        public readonly ?int    $sizeId    = null,
        public readonly ?string $sku       = null,
        public readonly ?float  $price     = null,
        public readonly ?float  $salePrice = null,
        public readonly ?int    $stockQty  = null,
        public readonly ?bool   $isActive  = null,
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(
            sizeId:    isset($d['size_id'])    ? (int)$d['size_id']        : null,
            sku:       isset($d['sku'])        ? strtoupper(trim($d['sku'])): null,
            price:     isset($d['price'])      ? (float)$d['price']        : null,
            salePrice: isset($d['sale_price']) ? (float)$d['sale_price']   : null,
            stockQty:  isset($d['stock_qty'])  ? (int)$d['stock_qty']      : null,
            isActive:  isset($d['is_active'])  ? (bool)$d['is_active']     : null,
        );
    }
}
