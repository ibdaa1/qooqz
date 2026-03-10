<?php
/**
 * TORO — v1/modules/Translations/DTO/UpsertTranslationValueDTO.php
 */
declare(strict_types=1);

final class UpsertTranslationValueDTO
{
    public function __construct(
        public readonly int $keyId,
        public readonly int $languageId,
        public readonly string $value,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            keyId:      (int)($data['key_id'] ?? 0),
            languageId: (int)($data['language_id'] ?? 0),
            value:      trim($data['value'] ?? ''),
        );
    }
}