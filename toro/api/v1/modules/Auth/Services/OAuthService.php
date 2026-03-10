<?php
/**
 * TORO — v1/modules/Auth/Services/OAuthService.php
 * التحقق من Google/Facebook tokens
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\AuthorizationException;
use Shared\Domain\Exceptions\ValidationException;

final class OAuthService
{
    /**
     * يُرجع profile موحد من أي provider
     * ['uid', 'email', 'first_name', 'last_name']
     */
    public function verify(string $provider, string $token): array
    {
        return match ($provider) {
            'google'   => $this->verifyGoogle($token),
            'facebook' => $this->verifyFacebook($token),
            default    => throw new ValidationException("مزود OAuth غير مدعوم: {$provider}", []),
        };
    }

    // ── Google ────────────────────────────────────────────────
    private function verifyGoogle(string $token): array
    {
        // يدعم id_token (JWT) أو access_token
        $url  = "https://oauth2.googleapis.com/tokeninfo?id_token=" . urlencode($token);
        $data = $this->httpGet($url);

        if (!empty($data['error'])) {
            // جرب مع access_token
            $url2 = "https://www.googleapis.com/oauth2/v3/userinfo";
            $data = $this->httpGet($url2, ["Authorization: Bearer {$token}"]);
        }

        if (empty($data['email'])) {
            throw new AuthorizationException('فشل التحقق من Google token');
        }

        $clientId = $_ENV['GOOGLE_CLIENT_ID'] ?? null;
        if ($clientId && isset($data['aud']) && $data['aud'] !== $clientId) {
            throw new AuthorizationException('Google token غير مخصص لهذا التطبيق');
        }

        $nameParts = explode(' ', $data['name'] ?? '', 2);
        return [
            'uid'        => $data['sub'],
            'email'      => strtolower($data['email']),
            'first_name' => $data['given_name']  ?? ($nameParts[0] ?? ''),
            'last_name'  => $data['family_name'] ?? ($nameParts[1] ?? ''),
        ];
    }

    // ── Facebook ──────────────────────────────────────────────
    private function verifyFacebook(string $token): array
    {
        $appId     = $_ENV['FACEBOOK_APP_ID']     ?? '';
        $appSecret = $_ENV['FACEBOOK_APP_SECRET'] ?? '';

        // 1. التحقق من صحة التوكن
        if ($appId && $appSecret) {
            $appToken = "{$appId}|{$appSecret}";
            $debug    = $this->httpGet("https://graph.facebook.com/debug_token?input_token={$token}&access_token={$appToken}");
            if (empty($debug['data']['is_valid'])) {
                throw new AuthorizationException('Facebook token غير صالح');
            }
        }

        // 2. جلب بيانات المستخدم
        $data = $this->httpGet("https://graph.facebook.com/me?fields=id,email,first_name,last_name,picture&access_token={$token}");

        if (empty($data['id'])) {
            throw new AuthorizationException('فشل جلب بيانات Facebook');
        }
        if (empty($data['email'])) {
            throw new AuthorizationException('Facebook لم يُشارك البريد الإلكتروني. تأكد من صلاحية email');
        }

        return [
            'uid'        => $data['id'],
            'email'      => strtolower($data['email']),
            'first_name' => $data['first_name'] ?? '',
            'last_name'  => $data['last_name']  ?? '',
        ];
    }

    // ── HTTP Helper ───────────────────────────────────────────
    private function httpGet(string $url, array $headers = []): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => array_merge(['Accept: application/json'], $headers),
        ]);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) throw new AuthorizationException("OAuth HTTP خطأ: {$err}");
        return json_decode($body ?: '{}', true) ?? [];
    }
}
