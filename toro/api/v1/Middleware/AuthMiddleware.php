<?php
/**
 * TORO — v1/Middleware/AuthMiddleware.php
 * يتحقق من JWT ويُضع userId في $_SERVER
 */
declare(strict_types=1);
namespace V1\Middleware;

use Shared\Core\MiddlewareBase;
use Shared\Helpers\Response;

final class AuthMiddleware extends MiddlewareBase
{
    public function handle(callable $next): void
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!str_starts_with($header, 'Bearer ')) {
            Response::json(['success' => false, 'message' => 'مطلوب تسجيل دخول'], 401);
            exit;
        }

        $token = substr($header, 7);
        try {
            $payload = (new \JwtService())->verify($token);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 401);
            exit;
        }

        // أتاحه للـ Controller
        $_SERVER['REQUEST_USER_ID']   = $payload['sub'];
        $_SERVER['REQUEST_USER_ROLE'] = $payload['role'];

        $next();
    }
}
