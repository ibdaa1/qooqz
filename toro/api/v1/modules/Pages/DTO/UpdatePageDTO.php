<?php
/**
 * TORO — v1/modules/Pages/DTO/UpdatePageDTO.php
 */
declare(strict_types=1);

final class UpdatePageDTO
{
    public function __construct(
        public readonly ?string $slug         = null,
        public readonly ?string $template     = null,
        public readonly ?bool   $isActive     = null,
        public readonly ?array  $translations = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            slug:         isset($data['slug'])     ? trim(strtolower($data['slug'])) : null,
            template:     isset($data['template']) ? trim($data['template'])         : null,
            isActive:     isset($data['is_active']) ? (bool)$data['is_active']       : null,
            translations: $data['translations'] ?? null,
        );
    }
}
