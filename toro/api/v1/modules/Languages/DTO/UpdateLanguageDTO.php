<?php
/**
 * TORO — v1/modules/Languages/DTO/UpdateLanguageDTO.php
 */
declare(strict_types=1);

final class UpdateLanguageDTO
{
    public function __construct(
        public readonly ?string $code       = null,
        public readonly ?string $name       = null,
        public readonly ?string $native     = null,
        public readonly ?string $direction  = null,
        public readonly ?string $flagIcon   = null,
        public readonly ?bool   $isActive   = null,
        public readonly ?bool   $isDefault  = null,
        public readonly ?int    $sortOrder  = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            code:       isset($data['code'])       ? trim(strtolower($data['code'])) : null,
            name:       isset($data['name'])       ? trim($data['name'])             : null,
            native:     isset($data['native'])     ? trim($data['native'])           : null,
            direction:  isset($data['direction']) && in_array($data['direction'], ['ltr','rtl']) ? $data['direction'] : null,
            flagIcon:   $data['flag_icon']         ?? null,
            isActive:   isset($data['is_active'])  ? (bool)$data['is_active']        : null,
            isDefault:  isset($data['is_default']) ? (bool)$data['is_default']       : null,
            sortOrder:  isset($data['sort_order']) ? (int)$data['sort_order']        : null,
        );
    }
}