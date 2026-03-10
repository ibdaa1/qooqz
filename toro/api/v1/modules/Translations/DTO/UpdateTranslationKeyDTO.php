<?php
/**
 * TORO — v1/modules/Translations/DTO/UpdateTranslationKeyDTO.php
 */
declare(strict_types=1);

final class UpdateTranslationKeyDTO
{
    public function __construct(
        public readonly ?string $keyName = null,
        public readonly ?string $context = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            keyName: isset($data['key_name']) ? trim($data['key_name']) : null,
            context: isset($data['context'])  ? trim($data['context']) : null,
        );
    }
}