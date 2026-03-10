<?php
/**
 * TORO — v1/modules/CsrfTokens/Services/CsrfTokensService.php
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\ValidationException;

final class CsrfTokensService
{
    private const TTL_SECONDS = 3600; // 1 hour

    public function __construct(private readonly CsrfTokensRepositoryInterface $repo) {}

    public function generate(string $sessionId): array
    {
        // Delete any existing token for this session first
        $this->repo->deleteBySession($sessionId);

        $token     = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + self::TTL_SECONDS);

        $id = $this->repo->create($token, $sessionId, $expiresAt);

        return [
            'token'      => $token,
            'expires_at' => $expiresAt,
        ];
    }

    public function verify(string $token, string $sessionId): bool
    {
        $row = $this->repo->findByToken($token);

        if (!$row) return false;
        if ($row['session_id'] !== $sessionId) return false;

        // Single-use: delete after verification
        $this->repo->delete((int)$row['id']);

        return true;
    }

    public function cleanup(): int
    {
        return $this->repo->deleteExpired();
    }

    public function validate(string $token, string $sessionId): void
    {
        if (!$this->verify($token, $sessionId)) {
            throw new ValidationException(['csrf' => 'رمز CSRF غير صالح أو منتهي الصلاحية']);
        }
    }
}
