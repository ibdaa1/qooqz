<?php
declare(strict_types=1);

final class RegisterDTO
{
    public function __construct(
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $email,
        public readonly string $password,
        public readonly string $phone    = '',
        public readonly string $language = 'ar',
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            firstName: trim($data['first_name'] ?? ''),
            lastName:  trim($data['last_name']  ?? ''),
            email:     trim(strtolower($data['email'] ?? '')),
            password:  $data['password'] ?? '',
            phone:     trim($data['phone']    ?? ''),
            language:  trim($data['language'] ?? 'ar'),
        );
    }
}
