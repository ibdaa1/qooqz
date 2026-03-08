<?php
/**
 * TORO — v1/modules/Auth/Services/JwtService.php
 * توليد والتحقق من JWT tokens
 */
declare(strict_types=1);
namespace V1\Modules\Auth\Services;

use Shared\Domain\Exceptions\AuthorizationException;

final class JwtService
{
    private string $secret;
    private int    $accessTtl;   // ثواني
    private int    $refreshTtl;

    public function __construct()
    {
        $this->secret     = $_ENV['JWT_SECRET'] ?? 'change_this_secret_in_env';
        $this->accessTtl  = (int)($_ENV['JWT_ACCESS_TTL']  ?? 900);    // 15 min
        $this->refreshTtl = (int)($_ENV['JWT_REFRESH_TTL'] ?? 2592000); // 30 days
    }

    public function generatePair(int $userId, string $role): array
    {
        $now       = time();
        $accessExp = $now + $this->accessTtl;

        $accessToken  = $this->encode([
            'sub'  => $userId,
            'role' => $role,
            'iat'  => $now,
            'exp'  => $accessExp,
            'type' => 'access',
        ]);

        $refreshToken = bin2hex(random_bytes(40)); // opaque — مخزن في DB

        return [
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in'    => $this->accessTtl,
        ];
    }

    public function verify(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) throw new AuthorizationException('توكن غير صالح');

        [$header64, $payload64, $sig64] = $parts;

        $expected = $this->sign("{$header64}.{$payload64}");
        if (!hash_equals($expected, $sig64)) {
            throw new AuthorizationException('توقيع التوكن غير صحيح');
        }

        $payload = json_decode(base64_decode(strtr($payload64, '-_', '+/')), true);
        if (!$payload) throw new AuthorizationException('محتوى التوكن غير صالح');

        if (($payload['exp'] ?? 0) < time()) {
            throw new AuthorizationException('انتهت صلاحية التوكن');
        }

        return $payload;
    }

    private function encode(array $payload): string
    {
        $header  = $this->base64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $body    = $this->base64url(json_encode($payload));
        $sig     = $this->sign("{$header}.{$body}");
        return "{$header}.{$body}.{$sig}";
    }

    private function sign(string $data): string
    {
        return $this->base64url(hash_hmac('sha256', $data, $this->secret, true));
    }

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
