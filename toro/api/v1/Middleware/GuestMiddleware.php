<?php
/**
 * TORO — v1/Middleware/GuestMiddleware.php
 * يمنع المستخدم المسجل من الوصول لـ login/register
 */
declare(strict_types=1);
namespace V1\Middleware;

use Shared\Core\MiddlewareBase;
use Shared\Helpers\Response;
use V1\Modules\Auth\Services\JwtService;

final class GuestMiddleware extends MiddlewareBase
{
    public function handle(callable $next): void
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($header, 'Bearer ')) {
            $token = substr($header, 7);
            try {
                (new JwtService())->verify($token);
                // إذا نجح التحقق = مستخدم مسجل
                Response::json(['success' => false, 'message' => 'أنت مسجل الدخول مسبقاً'], 403);
                exit;
            } catch (\Throwable) {
                // توكن منتهي أو غير صالح = ضيف → تابع
            }
        }
        $next();
    }
}
