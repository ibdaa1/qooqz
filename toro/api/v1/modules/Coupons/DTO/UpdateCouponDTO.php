<?php
/**
 * TORO — v1/modules/Coupons/DTO/UpdateCouponDTO.php
 */
declare(strict_types=1);

final class UpdateCouponDTO
{
    public function __construct(
        public readonly ?string $code           = null,
        public readonly ?string $type           = null,
        public readonly ?float  $value          = null,
        public readonly ?float  $minOrderAmount = null,
        public readonly ?int    $maxUses        = null,
        public readonly ?string $startsAt       = null,
        public readonly ?string $expiresAt      = null,
        public readonly ?bool   $isActive       = null,
        public readonly ?array  $translations   = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            code:           isset($data['code'])             ? strtoupper(trim($data['code']))  : null,
            type:           $data['type']                    ?? null,
            value:          isset($data['value'])            ? (float)$data['value']            : null,
            minOrderAmount: isset($data['min_order_amount']) ? (float)$data['min_order_amount'] : null,
            maxUses:        isset($data['max_uses'])         ? (int)$data['max_uses']           : null,
            startsAt:       $data['starts_at']  ?? null,
            expiresAt:      $data['expires_at'] ?? null,
            isActive:       isset($data['is_active'])        ? (bool)$data['is_active']         : null,
            translations:   $data['translations']            ?? null,
        );
    }
}
