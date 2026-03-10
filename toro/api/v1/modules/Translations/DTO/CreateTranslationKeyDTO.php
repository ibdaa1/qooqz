<?php
/**
 * TORO — v1/modules/Translations/DTO/CreateTranslationKeyDTO.php
 */
declare(strict_types=1);

final class CreateTranslationKeyDTO
{
    public function __construct(
        public readonly string $keyName,
        public readonly ?string $context = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            keyName: trim($data['key_name'] ?? $data['key'] ?? ''),
            context: isset($data['context']) ? trim($data['context']) : null,
        );
    }
}