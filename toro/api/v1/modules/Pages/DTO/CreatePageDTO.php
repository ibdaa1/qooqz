<?php
/**
 * TORO — v1/modules/Pages/DTO/CreatePageDTO.php
 */
declare(strict_types=1);

final class CreatePageDTO
{
    public function __construct(
        public readonly string  $slug,
        public readonly string  $template     = 'default',
        public readonly bool    $isActive     = true,
        public readonly array   $translations = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            slug:         trim(strtolower($data['slug'] ?? '')),
            template:     trim($data['template'] ?? 'default') ?: 'default',
            isActive:     (bool)($data['is_active'] ?? true),
            translations: $data['translations'] ?? [],
        );
    }
}
