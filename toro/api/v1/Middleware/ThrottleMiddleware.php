<?php
/**
 * TORO — v1/Middleware/ThrottleMiddleware.php
 * معدل الطلبات لكل route بشكل مستقل
 * الاستخدام: ThrottleMiddleware::class . ':10,60'  (10 طلبات في 60 ثانية)
 */
declare(strict_types=1);
namespace V1\Middleware;

use Shared\Core\{MiddlewareBase, DatabaseConnection};
use Shared\Helpers\Response;

final class ThrottleMiddleware extends MiddlewareBase
{
    private int $max;
    private int $window;

    public function __construct(string $config = '60,60')
    {
        [$this->max, $this->window] = array_map('intval', explode(',', $config, 2));
    }

    public function handle(callable $next): void
    {
        $ip     = $this->ip();
        $uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $key    = "route:{$uri}:{$ip}";

        $pdo = DatabaseConnection::getInstance();

        $pdo->prepare("
            INSERT INTO rate_limits (`key`, attempts, last_attempt)
            VALUES (:k, 1, NOW())
            ON DUPLICATE KEY UPDATE
                attempts     = IF(last_attempt < DATE_SUB(NOW(), INTERVAL :w SECOND), 1, attempts + 1),
                last_attempt = NOW()
        ")->execute([':k' => $key, ':w' => $this->window]);

        $row = $pdo->prepare("SELECT attempts, blocked_until FROM rate_limits WHERE `key` = :k")
                   ->execute([':k' => $key]) ? $pdo->prepare("SELECT attempts, blocked_until FROM rate_limits WHERE `key` = :k") : null;

        // إعادة الاستعلام بشكل صحيح
        $stmt = $pdo->prepare("SELECT attempts, blocked_until FROM rate_limits WHERE `key` = :k");
        $stmt->execute([':k' => $key]);
        $row = $stmt->fetch();

        if ($row && (int)$row['attempts'] > $this->max) {
            // حظر مؤقت
            $pdo->prepare("UPDATE rate_limits SET blocked_until = DATE_ADD(NOW(), INTERVAL :w SECOND) WHERE `key` = :k")
                ->execute([':k' => $key, ':w' => $this->window]);

            header("Retry-After: {$this->window}");
            header("X-RateLimit-Limit: {$this->max}");
            header("X-RateLimit-Remaining: 0");
            Response::json(['success' => false, 'message' => 'طلبات كثيرة جداً، انتظر قليلاً'], 429);
            exit;
        }

        $remaining = max(0, $this->max - (int)($row['attempts'] ?? 0));
        header("X-RateLimit-Limit: {$this->max}");
        header("X-RateLimit-Remaining: {$remaining}");

        $next();
    }

    private function ip(): string
    {
        return $_SERVER['HTTP_CF_CONNECTING_IP']
            ?? $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '0.0.0.0';
    }
}
