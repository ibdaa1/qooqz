<?php
/**
 * TORO — v1/modules/Languages/DTO/CreateLanguageDTO.php
 */
declare(strict_types=1);

final class CreateLanguageDTO
{
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly string $native,
        public readonly string $direction = 'ltr',
        public readonly ?string $flagIcon = null,
        public readonly bool $isActive = true,
        public readonly bool $isDefault = false,
        public readonly int $sortOrder = 0,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            code:       trim(strtolower($data['code'] ?? '')),
            name:       trim($data['name'] ?? ''),
            native:     trim($data['native'] ?? ''),
            direction:  in_array($data['direction'] ?? '', ['ltr','rtl']) ? $data['direction'] : 'ltr',
            flagIcon:   $data['flag_icon'] ?? null,
            isActive:   (bool)($data['is_active'] ?? true),
            isDefault:  (bool)($data['is_default'] ?? false),
            sortOrder:  (int)($data['sort_order'] ?? 0),
        );
    }
}