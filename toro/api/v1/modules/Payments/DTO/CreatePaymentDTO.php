<?php
/**
 * TORO — v1/modules/Payments/DTO/CreatePaymentDTO.php
 */
declare(strict_types=1);

final class CreatePaymentDTO
{
    public function __construct(
        public readonly int     $orderId,
        public readonly string  $method,
        public readonly float   $amount,
        public readonly string  $currency    = 'SAR',
        public readonly ?string $gateway     = null,
        public readonly ?string $gatewayTxnId = null,
        public readonly ?string $gatewayRef  = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            orderId:      (int)($data['order_id']       ?? 0),
            method:       (string)($data['method']      ?? ''),
            amount:       (float)($data['amount']       ?? 0),
            currency:     (string)($data['currency']    ?? 'SAR'),
            gateway:      $data['gateway']              ?? null,
            gatewayTxnId: $data['gateway_txn_id']       ?? null,
            gatewayRef:   $data['gateway_ref']          ?? null,
        );
    }
}
