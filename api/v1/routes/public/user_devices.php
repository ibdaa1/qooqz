<?php
declare(strict_types=1);
/**
 * Public route: /api/public/user_devices
 *
 * Allows logged-in frontend users to register/update their device info.
 * This works as a fallback when FCM is not configured — records basic
 * device metadata (user_agent, IP, device_type, device_name).
 *
 * POST   — Register or update device
 * GET    — Get current user's devices
 */

if (session_status() === PHP_SESSION_NONE) session_start();

// Resolve user_id from session
$userId = null;
if (!empty($_SESSION['user']['id'])) {
    $userId = (int)$_SESSION['user']['id'];
} elseif (!empty($_SESSION['user_id'])) {
    $userId = (int)$_SESSION['user_id'];
}

if ($userId === null || $userId <= 0) {
    ResponseFormatter::error('Authentication required', 401);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    switch ($method) {
        case 'GET':
            $stmt = $pdo->prepare(
                "SELECT id, device_type, device_name, ip, last_seen_at, is_active, created_at
                 FROM user_devices WHERE user_id = ? ORDER BY last_seen_at DESC"
            );
            $stmt->execute([$userId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ResponseFormatter::success(['items' => $items, 'total' => count($items)]);
            break;

        case 'POST':
            $raw  = file_get_contents('php://input');
            $data = $raw ? json_decode($raw, true) : [];

            $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512);
            $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');

            $fcmToken   = !empty($data['fcm_token']) ? trim($data['fcm_token']) : null;
            $deviceType = !empty($data['device_type']) ? $data['device_type'] : null;
            $deviceName = !empty($data['device_name']) ? substr($data['device_name'], 0, 100) : null;

            // Validate device_type
            if ($deviceType !== null && !in_array($deviceType, ['web', 'android', 'ios', 'other'], true)) {
                $deviceType = 'other';
            }

            // Auto-detect device_type from user_agent if not provided
            if ($deviceType === null && $ua !== '') {
                $uaLower = strtolower($ua);
                if (str_contains($uaLower, 'android'))       $deviceType = 'android';
                elseif (str_contains($uaLower, 'iphone') || str_contains($uaLower, 'ipad')) $deviceType = 'ios';
                else                                          $deviceType = 'web';
            }

            // Auto-detect device_name if not provided
            if ($deviceName === null && $ua !== '') {
                $browser = 'Browser';
                if (preg_match('/(?:Chrome|CriOS)\/[\d.]+/', $ua) && !str_contains($ua, 'Edg'))
                    $browser = 'Chrome';
                elseif (preg_match('/(?:Edg|Edge)\/[\d.]+/', $ua))
                    $browser = 'Edge';
                elseif (preg_match('/Firefox\/[\d.]+/', $ua))
                    $browser = 'Firefox';
                elseif (preg_match('/Safari\/[\d.]+/', $ua) && !str_contains($ua, 'Chrome'))
                    $browser = 'Safari';

                $uaLower = strtolower($ua);
                $os = '';
                if (str_contains($uaLower, 'windows'))      $os = ' on Windows';
                elseif (str_contains($uaLower, 'macintosh')) $os = ' on macOS';
                elseif (str_contains($uaLower, 'android'))   $os = ' on Android';
                elseif (str_contains($uaLower, 'iphone'))    $os = ' on iOS';
                elseif (str_contains($uaLower, 'linux'))     $os = ' on Linux';

                $deviceName = $browser . $os;
            }

            // Handle deregister sub-action
            $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            if (str_ends_with(rtrim($uri, '/'), '/deregister')) {
                if (empty($fcmToken)) {
                    ResponseFormatter::error('fcm_token is required for deregister', 422);
                    exit;
                }
                $stmt = $pdo->prepare("UPDATE user_devices SET is_active = 0 WHERE fcm_token = ? AND user_id = ?");
                $stmt->execute([$fcmToken, $userId]);
                ResponseFormatter::success(['deregistered' => true], 'Device deregistered');
                break;
            }

            // Deduplication: check by FCM token first, then by user_agent
            $existing = null;
            if ($fcmToken !== null) {
                $stmt = $pdo->prepare("SELECT id FROM user_devices WHERE fcm_token = ? LIMIT 1");
                $stmt->execute([$fcmToken]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            if (!$existing && $ua !== '') {
                $stmt = $pdo->prepare(
                    "SELECT id FROM user_devices WHERE user_id = ? AND user_agent = ? AND is_active = 1 LIMIT 1"
                );
                $stmt->execute([$userId, $ua]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            if ($existing) {
                // Update existing device
                $sets = ["ip = ?", "last_seen_at = NOW()", "is_active = 1", "updated_at = CURRENT_TIMESTAMP"];
                $params = [$ip];
                if ($fcmToken !== null) { $sets[] = "fcm_token = ?"; $params[] = $fcmToken; }
                if ($deviceType !== null) { $sets[] = "device_type = ?"; $params[] = $deviceType; }
                if ($deviceName !== null) { $sets[] = "device_name = ?"; $params[] = $deviceName; }
                $params[] = $existing['id'];
                $stmt = $pdo->prepare("UPDATE user_devices SET " . implode(', ', $sets) . " WHERE id = ?");
                $stmt->execute($params);
                ResponseFormatter::success(['id' => (int)$existing['id']], 'Device updated');
            } else {
                // Insert new device
                $stmt = $pdo->prepare(
                    "INSERT INTO user_devices (user_id, fcm_token, device_type, device_name, user_agent, ip, last_seen_at, is_active, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, NOW(), 1, CURRENT_TIMESTAMP)"
                );
                $stmt->execute([$userId, $fcmToken, $deviceType, $deviceName, $ua, $ip]);
                $newId = (int)$pdo->lastInsertId();
                ResponseFormatter::success(['id' => $newId], 'Device registered', 201);
            }
            break;

        case 'OPTIONS':
            http_response_code(204);
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (Throwable $e) {
    if (function_exists('safe_log')) {
        safe_log('error', 'public.user_devices', ['error' => $e->getMessage()]);
    }
    ResponseFormatter::error('Device registration failed', 500);
}
