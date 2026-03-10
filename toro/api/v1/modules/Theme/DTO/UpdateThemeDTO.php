<?php
/**
 * TORO — v1/modules/Theme/DTO/UpdateThemeDTO.php
 */
declare(strict_types=1);

final class UpdateThemeDTO
{
    public function __construct(
        public readonly ?string $variable = null,
        public readonly ?string $value    = null,
        public readonly ?string $label    = null,
        public readonly ?bool   $isActive = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            variable: isset($data['variable']) ? trim($data['variable']) : null,
            value:    isset($data['value'])    ? trim($data['value'])    : null,
            label:    isset($data['label'])    ? trim($data['label'])    : null,
            isActive: isset($data['is_active']) ? (bool)$data['is_active'] : null,
        );
    }
}
