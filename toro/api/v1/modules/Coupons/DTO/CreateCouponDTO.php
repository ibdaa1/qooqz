<?php
/**
 * TORO — v1/modules/Coupons/DTO/CreateCouponDTO.php
 */
declare(strict_types=1);

final class CreateCouponDTO
{
    public function __construct(
        public readonly string  $code,
        public readonly string  $type             = 'percent',
        public readonly float   $value            = 0.0,
        public readonly ?float  $minOrderAmount   = null,
        public readonly ?int    $maxUses          = null,
        public readonly ?string $startsAt         = null,
        public readonly ?string $expiresAt        = null,
        public readonly bool    $isActive         = true,
        public readonly array   $translations     = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            code:           strtoupper(trim($data['code'] ?? '')),
            type:           $data['type']              ?? 'percent',
            value:          (float)($data['value']     ?? 0),
            minOrderAmount: isset($data['min_order_amount']) ? (float)$data['min_order_amount'] : null,
            maxUses:        isset($data['max_uses'])         ? (int)$data['max_uses']           : null,
            startsAt:       $data['starts_at']  ?? null,
            expiresAt:      $data['expires_at'] ?? null,
            isActive:       (bool)($data['is_active'] ?? true),
            translations:   $data['translations'] ?? [],
        );
    }
}
