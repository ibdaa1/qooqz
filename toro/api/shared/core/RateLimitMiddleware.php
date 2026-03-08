<?php
/**
 * TORO — RateLimitMiddleware.php
 * /public_html/toro/api/shared/core/RateLimitMiddleware.php
 *
 * Reads limits from `rate_limits` table.
 * Allows different limits per context (public vs admin vs auth endpoints).
 */

declare(strict_types=1);

namespace Shared\Core;

use Shared\Helpers\Response;

class RateLimitMiddleware extends MiddlewareBase
{
    // Max requests per window per IP
    private const LIMITS = [
        'public'  => ['max' => 120, 'window' => 60],   // 120 req/min
        'admin'   => ['max' => 300, 'window' => 60],
        'mobile'  => ['max' => 200, 'window' => 60],
        'auth'    => ['max' => 10,  'window' => 60],    // login/register stricter
    ];

    public function handle(callable $next): void
    {
        $context = defined('REQUEST_CONTEXT') ? REQUEST_CONTEXT : 'public';
        $ip      = $this->getClientIp();
        $uri     = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        // Stricter limit for auth endpoints
        if (str_contains($uri, '/auth/')) {
            $limit  = self::LIMITS['auth'];
            $bucket = "auth:{$ip}";
        } else {
            $limit  = self::LIMITS[$context] ?? self::LIMITS['public'];
            $bucket = "{$context}:{$ip}";
        }

        $pdo = DatabaseConnection::getInstance();

        // Upsert attempt counter
        $stmt = $pdo->prepare("
            INSERT INTO rate_limits (`key`, attempts, last_attempt)
            VALUES (:key, 1, NOW())
            ON DUPLICATE KEY UPDATE
                attempts     = IF(last_attempt < DATE_SUB(NOW(), INTERVAL :window SECOND), 1, attempts + 1),
                last_attempt = NOW(),
                blocked_until = IF(
                    attempts + 1 >= :max AND last_attempt >= DATE_SUB(NOW(), INTERVAL :window SECOND),
                    DATE_ADD(NOW(), INTERVAL :block SECOND),
                    blocked_until
                )
        ");
        $stmt->execute([
            ':key'    => $bucket,
            ':window' => $limit['window'],
            ':max'    => $limit['max'],
            ':block'  => $limit['window'] * 2,
        ]);

        // Check if currently blocked
        $check = $pdo->prepare("
            SELECT attempts, blocked_until
            FROM rate_limits
            WHERE `key` = :key
        ");
        $check->execute([':key' => $bucket]);
        $row = $check->fetch(\PDO::FETCH_ASSOC);

        if ($row && $row['blocked_until'] && strtotime($row['blocked_until']) > time()) {
            $retryAfter = strtotime($row['blocked_until']) - time();
            header('Retry-After: ' . $retryAfter);
            header('X-RateLimit-Limit: ' . $limit['max']);
            header('X-RateLimit-Remaining: 0');
            Response::json([
                'success' => false,
                'message' => 'Too many requests. Please slow down.',
                'retry_after' => $retryAfter,
            ], 429);
            exit;
        }

        $remaining = max(0, $limit['max'] - (int)($row['attempts'] ?? 0));
        header('X-RateLimit-Limit: ' . $limit['max']);
        header('X-RateLimit-Remaining: ' . $remaining);

        $next();
    }

    private function getClientIp(): string
    {
        // Respect proxy headers (validate if trusted proxy)
        $trustedProxy = $_ENV['TRUSTED_PROXY_IP'] ?? null;
        $remoteAddr   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if ($trustedProxy && $remoteAddr === $trustedProxy) {
            return $_SERVER['HTTP_X_FORWARDED_FOR']
                ?? $_SERVER['HTTP_CF_CONNECTING_IP']
                ?? $remoteAddr;
        }
        return $remoteAddr;
    }
}