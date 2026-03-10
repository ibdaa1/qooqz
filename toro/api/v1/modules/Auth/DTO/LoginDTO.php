<?php
/**
 * TORO — v1/modules/Auth/DTO/LoginDTO.php
 */
declare(strict_types=1);

final class LoginDTO
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly bool   $remember = false,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            email:    trim(strtolower($data['email'] ?? '')),
            password: $data['password'] ?? '',
            remember: (bool)($data['remember'] ?? false),
        );
    }
}
