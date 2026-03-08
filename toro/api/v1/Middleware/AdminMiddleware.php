<?php
/**
 * TORO — v1/Middleware/AdminMiddleware.php
 * يسمح فقط للـ admin و super_admin
 */
declare(strict_types=1);
namespace V1\Middleware;

use Shared\Core\MiddlewareBase;
use Shared\Helpers\Response;

final class AdminMiddleware extends MiddlewareBase
{
    private array $allowedRoles;

    public function __construct(string $roles = 'admin,super_admin')
    {
        $this->allowedRoles = array_map('trim', explode(',', $roles));
    }

    public function handle(callable $next): void
    {
        $role = $_SERVER['REQUEST_USER_ROLE'] ?? null;
        if (!$role || !in_array($role, $this->allowedRoles, true)) {
            Response::json(['success' => false, 'message' => 'غير مسموح — صلاحيات الأدمن مطلوبة'], 403);
            exit;
        }
        $next();
    }
}
