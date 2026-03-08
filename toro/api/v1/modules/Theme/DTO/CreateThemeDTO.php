<?php
/**
 * TORO — v1/modules/Theme/DTO/CreateThemeDTO.php
 */
declare(strict_types=1);

final class CreateThemeDTO
{
    public function __construct(
        public readonly string  $variable,
        public readonly string  $value,
        public readonly ?string $label    = null,
        public readonly bool    $isActive = true,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            variable: trim($data['variable'] ?? ''),
            value:    trim($data['value']    ?? ''),
            label:    isset($data['label'])  ? trim($data['label']) : null,
            isActive: (bool)($data['is_active'] ?? true),
        );
    }
}
