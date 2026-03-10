<?php
/**
 * TORO — v1/modules/UserTokens/Services/UserTokensService.php
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\{ValidationException, NotFoundException};

final class UserTokensService
{
    private const VALID_TYPES = ['refresh', 'reset_password', 'verify_email', 'verify_phone'];
    private const TTL = [
        'refresh'       => 30 * 24 * 3600,   // 30 days
        'reset_password'=> 3600,              // 1 hour
        'verify_email'  => 24 * 3600,         // 24 hours
        'verify_phone'  => 15 * 60,           // 15 minutes
    ];

    public function __construct(private readonly UserTokensRepositoryInterface $repo) {}

    public function listActive(int $userId, string $type): array
    {
        $this->validateType($type);
        return $this->repo->findActiveByUserId($userId, $type);
    }

    public function issue(int $userId, string $type): array
    {
        $this->validateType($type);

        $rawToken  = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $ttl       = self::TTL[$type];
        $expiresAt = date('Y-m-d H:i:s', time() + $ttl);

        $id = $this->repo->create([
            'user_id'    => $userId,
            'token_hash' => $tokenHash,
            'type'       => $type,
            'expires_at' => $expiresAt,
        ]);

        return [
            'id'         => $id,
            'token'      => $rawToken,   // plain token returned ONCE
            'type'       => $type,
            'expires_at' => $expiresAt,
        ];
    }

    public function verify(string $rawToken, string $type): array
    {
        $this->validateType($type);
        $tokenHash = hash('sha256', $rawToken);
        $record    = $this->repo->findByHash($tokenHash, $type);
        if (!$record) throw new NotFoundException('الرمز غير صالح أو منتهي الصلاحية');
        return $record;
    }

    public function consume(string $rawToken, string $type): array
    {
        $record = $this->verify($rawToken, $type);
        $this->repo->markUsed((int)$record['id']);
        return $record;
    }

    public function revokeAll(int $userId, string $type): int
    {
        $this->validateType($type);
        return $this->repo->revokeByUserId($userId, $type);
    }

    public function purgeExpired(): int
    {
        return $this->repo->deleteExpired();
    }

    private function validateType(string $type): void
    {
        if (!in_array($type, self::VALID_TYPES, true)) {
            throw new ValidationException(['type' => 'نوع الرمز غير مدعوم']);
        }
    }
}
