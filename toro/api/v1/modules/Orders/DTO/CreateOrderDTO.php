<?php
/**
 * TORO — v1/modules/Orders/DTO/CreateOrderDTO.php
 */
declare(strict_types=1);

final class CreateOrderDTO
{
    public function __construct(
        public readonly ?int    $userId       = null,
        public readonly ?int    $addressId    = null,
        public readonly float   $subtotal     = 0.0,
        public readonly float   $discount     = 0.0,
        public readonly float   $shippingCost = 0.0,
        public readonly float   $tax          = 0.0,
        public readonly string  $currency     = 'SAR',
        public readonly ?int    $couponId     = null,
        public readonly ?string $notes        = null,
        public readonly ?int    $languageId   = null,
        /** @var array<array{product_id: int, product_name: string, sku: string, qty: int, unit_price: float, discount?: float, variant_id?: int}> */
        public readonly array   $items        = [],
    ) {}

    public static function fromArray(array $data): self
    {
        $subtotal     = (float)($data['subtotal']      ?? 0);
        $discount     = (float)($data['discount']      ?? 0);
        $shippingCost = (float)($data['shipping_cost'] ?? 0);
        $tax          = (float)($data['tax']           ?? 0);

        return new self(
            userId:       isset($data['user_id'])    ? (int)$data['user_id']    : null,
            addressId:    isset($data['address_id']) ? (int)$data['address_id'] : null,
            subtotal:     $subtotal,
            discount:     $discount,
            shippingCost: $shippingCost,
            tax:          $tax,
            currency:     $data['currency']   ?? 'SAR',
            couponId:     isset($data['coupon_id']) ? (int)$data['coupon_id'] : null,
            notes:        $data['notes']      ?? null,
            languageId:   isset($data['language_id']) ? (int)$data['language_id'] : null,
            items:        $data['items']      ?? [],
        );
    }

    public function total(): float
    {
        return $this->subtotal - $this->discount + $this->shippingCost + $this->tax;
    }
}
