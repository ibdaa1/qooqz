<?php
/**
 * TORO — v1/modules/Auth/Controllers/AuthController.php
 * يستقبل HTTP فقط — لا منطق هنا أبداً
 */
declare(strict_types=1);

use Shared\Core\DatabaseConnection;
use Shared\Helpers\Response;

final class AuthController
{
    private AuthService $service;

    public function __construct()
    {
        $pdo = DatabaseConnection::getInstance();
        $this->service = new AuthService(
            new PdoAuthRepository($pdo),
            new JwtService(),
            new OAuthService(),
        );
    }

    // ── POST /v1/auth/login ───────────────────────────────────
    public function login(array $params = []): void
    {
        $data = $this->json();
        AuthValidator::login($data);
        $result = $this->service->login(LoginDTO::fromArray($data));
        Response::json(['success' => true, 'data' => $result], 200);
    }

    // ── POST /v1/auth/register ────────────────────────────────
    public function register(array $params = []): void
    {
        $data = $this->json();
        AuthValidator::register($data);
        $result = $this->service->register(RegisterDTO::fromArray($data));
        Response::json(['success' => true, 'data' => $result], 201);
    }

    // ── POST /v1/auth/logout ──────────────────────────────────
    public function logout(array $params = []): void
    {
        $userId       = $this->authUserId();
        $data         = $this->json();
        $refreshToken = $data['refresh_token'] ?? null;
        $this->service->logout($userId, $refreshToken);
        Response::json(['success' => true, 'message' => 'تم تسجيل الخروج'], 200);
    }

    // ── POST /v1/auth/refresh ─────────────────────────────────
    public function refresh(array $params = []): void
    {
        $data = $this->json();
        if (empty($data['refresh_token'])) {
            Response::json(['success' => false, 'message' => 'refresh_token مطلوب'], 422);
            return;
        }
        $result = $this->service->refresh($data['refresh_token']);
        Response::json(['success' => true, 'data' => $result], 200);
    }

    // ── GET /v1/auth/me ───────────────────────────────────────
    public function me(array $params = []): void
    {
        $userId = $this->authUserId();
        $user   = $this->service->getMe($userId);
        Response::json(['success' => true, 'data' => $user], 200);
    }

    // ── POST /v1/auth/change-password ─────────────────────────
    public function changePassword(array $params = []): void
    {
        $userId = $this->authUserId();
        $data   = $this->json();
        AuthValidator::changePassword($data);
        $this->service->changePassword($userId, $data['current_password'], $data['new_password']);
        Response::json(['success' => true, 'message' => 'تم تغيير كلمة المرور'], 200);
    }

    // ── POST /v1/auth/forgot-password ─────────────────────────
    public function forgotPassword(array $params = []): void
    {
        $data = $this->json();
        AuthValidator::forgotPassword($data);
        $this->service->forgotPassword($data['email']);
        // نُرجع نفس الرسالة دائماً (لا نُفصح إذا كان البريد موجوداً)
        Response::json(['success' => true, 'message' => 'إذا كان البريد مسجلاً ستصلك رسالة'], 200);
    }

    // ── POST /v1/auth/reset-password ──────────────────────────
    public function resetPassword(array $params = []): void
    {
        $data = $this->json();
        if (empty($data['token']) || empty($data['new_password'])) {
            Response::json(['success' => false, 'message' => 'token و new_password مطلوبان'], 422);
            return;
        }
        $this->service->resetPassword($data['token'], $data['new_password']);
        Response::json(['success' => true, 'message' => 'تم تعيين كلمة المرور الجديدة'], 200);
    }

    // ── POST /v1/auth/oauth/google ────────────────────────────
    public function oauthGoogle(array $params = []): void
    {
        $data = $this->json();
        AuthValidator::oauth($data);
        $result = $this->service->oauthLogin(OAuthDTO::fromArray($data, 'google'));
        Response::json(['success' => true, 'data' => $result], 200);
    }

    // ── POST /v1/auth/oauth/facebook ──────────────────────────
    public function oauthFacebook(array $params = []): void
    {
        $data = $this->json();
        AuthValidator::oauth($data);
        $result = $this->service->oauthLogin(OAuthDTO::fromArray($data, 'facebook'));
        Response::json(['success' => true, 'data' => $result], 200);
    }

    // ── GET /v1/auth/verify-email/{token} ─────────────────────
    public function verifyEmail(array $params = []): void
    {
        $token = $params['token'] ?? '';
        if (!$token) {
            Response::json(['success' => false, 'message' => 'رمز التحقق مطلوب'], 422);
            return;
        }
        $this->service->verifyEmail($token);
        Response::json(['success' => true, 'message' => 'تم تأكيد البريد الإلكتروني ✅'], 200);
    }

    // ── Helpers ───────────────────────────────────────────────
    private function json(): array
    {
        $raw = file_get_contents('php://input');
        return json_decode($raw ?: '{}', true) ?? [];
    }

    private function authUserId(): int
    {
        // يُضبط بواسطة AuthMiddleware في REQUEST_USER_ID
        $id = $_SERVER['REQUEST_USER_ID'] ?? null;
        if (!$id) {
            Response::json(['success' => false, 'message' => 'غير مصرح'], 401);
            exit;
        }
        return (int)$id;
    }
}
