<?php
/**
 * TORO — v1/modules/Auth/Services/AuthService.php
 * كل منطق المصادقة هنا — Controller لا يعرف PDO أبداً
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\{ValidationException, AuthorizationException, NotFoundException};
use Shared\Helpers\AuditLogger;

final class AuthService
{
    public function __construct(
        private readonly AuthRepositoryInterface $repo,
        private readonly JwtService              $jwt,
        private readonly OAuthService            $oauth,
    ) {}

    // ══════════════════════════════════════════════════════════
    // LOGIN
    // ══════════════════════════════════════════════════════════
    public function login(LoginDTO $dto): array
    {
        $user = $this->repo->findByEmail($dto->email);

        if (!$user || !password_verify($dto->password, (string)$user['password_hash'])) {
            AuditLogger::log('login_failed', null, 'users', null, ['email' => $dto->email]);
            throw new AuthorizationException('البريد الإلكتروني أو كلمة المرور غير صحيحة');
        }

        if (!(bool)$user['is_active']) {
            throw new AuthorizationException('هذا الحساب موقوف. تواصل مع الدعم');
        }

        // تحديث last_login
        $this->repo->updateUser($user['id'], ['last_login_at' => date('Y-m-d H:i:s')]);

        $tokens = $this->jwt->generatePair((int)$user['id'], $user['role_slug'] ?? 'customer');

        // حفظ refresh token
        $refreshExpiry = new \DateTimeImmutable('+30 days');
        $this->repo->saveToken(
            (int)$user['id'],
            'refresh',
            hash('sha256', $tokens['refresh_token']),
            $refreshExpiry
        );

        AuditLogger::log('login_success', $user['id'], 'users', $user['id']);

        return [
            'user'          => $this->sanitizeUser($user),
            'access_token'  => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_in'    => $tokens['expires_in'],
            'token_type'    => 'Bearer',
        ];
    }

    // ══════════════════════════════════════════════════════════
    // REGISTER
    // ══════════════════════════════════════════════════════════
    public function register(RegisterDTO $dto): array
    {
        // تحقق من البريد مكرر
        if ($this->repo->findByEmail($dto->email)) {
            throw new ValidationException('هذا البريد الإلكتروني مسجل مسبقاً', ['email' => 'البريد مسجل مسبقاً']);
        }

        $langId = null;
        if ($dto->language) {
            $lang   = $this->repo->findLanguageByCode($dto->language);
            $langId = $lang ? (int)$lang['id'] : $this->repo->getDefaultLanguageId();
        }

        $userId = $this->repo->createUser([
            'first_name'    => $dto->firstName,
            'last_name'     => $dto->lastName,
            'email'         => $dto->email,
            'password_hash' => password_hash($dto->password, PASSWORD_ARGON2ID),
            'phone'         => $dto->phone ?: null,
            'language_id'   => $langId,
            'role_id'       => 4, // customer
        ]);

        $user   = $this->repo->findById($userId);
        $tokens = $this->jwt->generatePair($userId, 'customer');

        $refreshExpiry = new \DateTimeImmutable('+30 days');
        $this->repo->saveToken($userId, 'refresh', hash('sha256', $tokens['refresh_token']), $refreshExpiry);

        // TODO: أرسل بريد تحقق — سيُضاف في NotificationService
        AuditLogger::log('register', $userId, 'users', $userId);

        return [
            'user'          => $this->sanitizeUser($user),
            'access_token'  => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_in'    => $tokens['expires_in'],
            'token_type'    => 'Bearer',
        ];
    }

    // ══════════════════════════════════════════════════════════
    // OAUTH (Google / Facebook)
    // ══════════════════════════════════════════════════════════
    public function oauthLogin(OAuthDTO $dto): array
    {
        // 1. تحقق من token مع مزود الخدمة
        $profile = $this->oauth->verify($dto->provider, $dto->token);
        // profile: ['uid', 'email', 'first_name', 'last_name', 'avatar']

        // 2. ابحث عن social account موجود
        $social = $this->repo->findSocialAccount($dto->provider, $profile['uid']);

        if ($social) {
            $userId = (int)$social['user_id'];
        } else {
            // 3. ابحث بالبريد
            $existing = $this->repo->findByEmail($profile['email']);
            if ($existing) {
                $userId = (int)$existing['id'];
            } else {
                // 4. أنشئ مستخدم جديد
                $langId = $this->repo->getDefaultLanguageId();
                $userId = $this->repo->createUser([
                    'first_name'    => $profile['first_name'],
                    'last_name'     => $profile['last_name'],
                    'email'         => $profile['email'],
                    'password_hash' => null,
                    'avatar'        => $profile['avatar'] ?? null,
                    'language_id'   => $langId,
                    'role_id'       => 4,
                ]);
            }
            // 5. ربط الحساب الاجتماعي
            $this->repo->createSocialAccount($userId, $dto->provider, $profile['uid'], $dto->token);
        }

        $user = $this->repo->findById($userId);
        if (!$user || !(bool)$user['is_active']) {
            throw new AuthorizationException('الحساب موقوف');
        }

        $this->repo->updateUser($userId, ['last_login_at' => date('Y-m-d H:i:s')]);
        $tokens = $this->jwt->generatePair($userId, $user['role_slug'] ?? 'customer');
        $this->repo->saveToken($userId, 'refresh', hash('sha256', $tokens['refresh_token']), new \DateTimeImmutable('+30 days'));

        AuditLogger::log('oauth_login', $userId, 'users', $userId, ['provider' => $dto->provider]);

        return [
            'user'          => $this->sanitizeUser($user),
            'access_token'  => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_in'    => $tokens['expires_in'],
            'token_type'    => 'Bearer',
        ];
    }

    // ══════════════════════════════════════════════════════════
    // REFRESH TOKEN
    // ══════════════════════════════════════════════════════════
    public function refresh(string $refreshToken): array
    {
        $hash  = hash('sha256', $refreshToken);
        $token = $this->repo->findToken('refresh', $hash);

        if (!$token) {
            throw new AuthorizationException('رمز التجديد غير صالح أو منتهي الصلاحية');
        }

        $this->repo->consumeToken((int)$token['id']);

        $user   = $this->repo->findById((int)$token['user_id']);
        if (!$user || !(bool)$user['is_active']) {
            throw new AuthorizationException('الحساب غير نشط');
        }

        $tokens = $this->jwt->generatePair((int)$user['id'], $user['role_slug'] ?? 'customer');
        $this->repo->saveToken((int)$user['id'], 'refresh', hash('sha256', $tokens['refresh_token']), new \DateTimeImmutable('+30 days'));

        return [
            'access_token'  => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_in'    => $tokens['expires_in'],
            'token_type'    => 'Bearer',
        ];
    }

    // ══════════════════════════════════════════════════════════
    // LOGOUT
    // ══════════════════════════════════════════════════════════
    public function logout(int $userId, ?string $refreshToken = null): void
    {
        if ($refreshToken) {
            $hash  = hash('sha256', $refreshToken);
            $token = $this->repo->findToken('refresh', $hash);
            if ($token) $this->repo->consumeToken((int)$token['id']);
        }
        AuditLogger::log('logout', $userId, 'users', $userId);
    }

    // ══════════════════════════════════════════════════════════
    // CHANGE PASSWORD
    // ══════════════════════════════════════════════════════════
    public function changePassword(int $userId, string $current, string $new): void
    {
        $user = $this->repo->findById($userId);
        if (!$user) throw new NotFoundException('المستخدم غير موجود');

        // جلب كلمة المرور المشفرة (findById لا يُرجعها لأسباب أمنية)
        $stmt = \Shared\Core\DatabaseConnection::getInstance()
            ->prepare("SELECT password_hash FROM users WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($current, (string)$row['password_hash'])) {
            throw new ValidationException('كلمة المرور الحالية غير صحيحة', ['current_password' => 'غير صحيحة']);
        }

        $this->repo->updateUser($userId, [
            'password_hash' => password_hash($new, PASSWORD_ARGON2ID),
        ]);

        AuditLogger::log('password_changed', $userId, 'users', $userId);
    }

    // ══════════════════════════════════════════════════════════
    // FORGOT / RESET PASSWORD
    // ══════════════════════════════════════════════════════════
    public function forgotPassword(string $email): void
    {
        $user = $this->repo->findByEmail($email);
        if (!$user) return; // لا نُفصح إذا كان البريد غير موجود (security)

        $rawToken = bin2hex(random_bytes(32));
        $this->repo->saveToken(
            (int)$user['id'],
            'reset_password',
            hash('sha256', $rawToken),
            new \DateTimeImmutable('+1 hour')
        );

        // TODO: أرسل البريد عبر NotificationService
        // NotificationService::send($user, 'reset_password', ['token' => $rawToken]);
        AuditLogger::log('forgot_password', $user['id'], 'users', $user['id']);
    }

    public function resetPassword(string $rawToken, string $newPassword): void
    {
        $hash  = hash('sha256', $rawToken);
        $token = $this->repo->findToken('reset_password', $hash);
        if (!$token) throw new ValidationException('الرابط غير صالح أو منتهي الصلاحية', ['token' => 'غير صالح']);

        $this->repo->consumeToken((int)$token['id']);
        $this->repo->updateUser((int)$token['user_id'], [
            'password_hash' => password_hash($newPassword, PASSWORD_ARGON2ID),
        ]);

        AuditLogger::log('password_reset', $token['user_id'], 'users', $token['user_id']);
    }

    // ══════════════════════════════════════════════════════════
    // VERIFY EMAIL
    // ══════════════════════════════════════════════════════════
    public function verifyEmail(string $rawToken): void
    {
        $hash  = hash('sha256', $rawToken);
        $token = $this->repo->findToken('verify_email', $hash);
        if (!$token) throw new ValidationException('رابط التحقق غير صالح أو منتهي', ['token' => 'غير صالح']);

        $this->repo->consumeToken((int)$token['id']);
        $this->repo->updateUser((int)$token['user_id'], [
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // GET ME
    // ══════════════════════════════════════════════════════════
    public function getMe(int $userId): array
    {
        $user = $this->repo->findById($userId);
        if (!$user) throw new NotFoundException('المستخدم غير موجود');
        return $this->sanitizeUser($user);
    }

    // ── Private helpers ────────────────────────────────────────
    private function sanitizeUser(array $user): array
    {
        unset($user['password_hash'], $user['remember_token']);
        return $user;
    }
}
