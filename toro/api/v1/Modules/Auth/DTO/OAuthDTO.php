<?php
declare(strict_types=1);
namespace V1\Modules\Auth\DTO;

final class OAuthDTO
{
    public function __construct(
        public readonly string $provider,   // google | facebook
        public readonly string $token,      // access_token from client SDK
        public readonly string $language = 'ar',
    ) {}

    public static function fromArray(array $data, string $provider): self
    {
        return new self(
            provider: $provider,
            token:    trim($data['token'] ?? $data['access_token'] ?? ''),
            language: trim($data['language'] ?? 'ar'),
        );
    }
}
