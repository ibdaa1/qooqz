<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__, 2);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

$modelsPath = API_VERSION_PATH . '/models/notification';
require_once $modelsPath . '/repositories/PdoNotificationsRepository.php';
require_once $modelsPath . '/validators/NotificationsValidator.php';
require_once $modelsPath . '/services/NotificationsService.php';
require_once $modelsPath . '/controllers/NotificationsController.php';

if (session_status() === PHP_SESSION_NONE) session_start();

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

$repo       = new PdoNotificationsRepository($pdo);
$validator  = new NotificationsValidator();
$service    = new NotificationsService($repo, $validator);
$controller = new NotificationsController($service);

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // Extract path for special actions like /unread-count
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $segments = explode('/', trim($uri, '/'));
    $lastSegment = end($segments);

    if ($method === 'GET') {
        // Special endpoint: /notifications/unread-count?user_id=...
        if ($lastSegment === 'unread-count') {
            $userId = isset($_GET['user_id']) && is_numeric($_GET['user_id']) ? (int)$_GET['user_id'] : null;
            if (!$userId) {
                ResponseFormatter::error('user_id is required', 400);
                exit;
            }
            $count = $controller->unreadCount($userId);
            ResponseFormatter::success(['unread_count' => $count]);
            exit;
        }

        // Single notification by id
        if (isset($_GET['id']) && is_numeric($_GET['id'])) {
            $item = $controller->get((int)$_GET['id']);
            ResponseFormatter::success($item);
            exit;
        }

        // List with filters
        $filters = [
            'user_id'            => isset($_GET['user_id']) && is_numeric($_GET['user_id']) ? (int)$_GET['user_id'] : null,
            'entity_id'          => isset($_GET['entity_id']) && is_numeric($_GET['entity_id']) ? (int)$_GET['entity_id'] : null,
            'is_read'            => isset($_GET['is_read']) && is_numeric($_GET['is_read']) ? (int)$_GET['is_read'] : null,
            'notification_type_id' => isset($_GET['notification_type_id']) && is_numeric($_GET['notification_type_id']) ? (int)$_GET['notification_type_id'] : null,
        ];

        // Date range for sent_at
        if (isset($_GET['date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $_GET['date_from'])) {
            $filters['date_from'] = $_GET['date_from'];
        }
        if (isset($_GET['date_to']) && preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $_GET['date_to'])) {
            $filters['date_to'] = $_GET['date_to'];
        }

        $orderBy  = $_GET['order_by']  ?? 'sent_at';
        $orderDir = $_GET['order_dir'] ?? 'DESC';
        $page     = isset($_GET['page'])  ? max(1, (int)$_GET['page']) : 1;
        $limit    = isset($_GET['limit']) ? min(1000, max(1, (int)$_GET['limit'])) : 25;
        $offset   = ($page - 1) * $limit;

        $result = $controller->list($filters, $orderBy, $orderDir, $limit, $offset);
        $total  = $result['total'];

        ResponseFormatter::success([
            'items' => $result['items'],
            'meta'  => [
                'total'       => $total,
                'page'        => $page,
                'per_page'    => $limit,
                'total_pages' => $total > 0 ? (int)ceil($total / $limit) : 0,
                'from'        => $total > 0 ? $offset + 1 : 0,
                'to'          => $total > 0 ? min($offset + $limit, $total) : 0,
            ],
        ]);
        exit;
    }

    $raw  = file_get_contents('php://input');
    $data = ($raw !== false && $raw !== '') ? (json_decode($raw, true) ?? []) : [];

    if ($method === 'POST') {
        // Check for mark-as-read action
        if ($lastSegment === 'mark-read' && isset($data['id'])) {
            $controller->markAsRead((int)$data['id']);
            ResponseFormatter::success(['marked_read' => true], 'Marked as read');
            exit;
        }

        // Send notification via helper (multi-channel: database, push, email, sms)
        if ($lastSegment === 'send') {
            require_once dirname(__DIR__, 2) . '/shared/helpers/notification.php';
            \Notification::setPDO($pdo);

            $recipientId   = isset($data['recipient_id']) && is_numeric($data['recipient_id']) ? (int)$data['recipient_id'] : 0;
            $recipientType = in_array($data['recipient_type'] ?? '', ['user', 'entity', 'tenant']) ? $data['recipient_type'] : 'user';
            $tenantId      = isset($data['tenant_id']) && is_numeric($data['tenant_id']) ? (int)$data['tenant_id'] : 1;
            $typeCode      = !empty($data['type_code']) ? $data['type_code'] : 'general';
            $title         = trim($data['title'] ?? '');
            $message       = trim($data['message'] ?? '');
            $extraData     = [];
            $channels      = isset($data['channels']) && is_array($data['channels']) ? $data['channels'] : ['database'];
            $priority      = in_array($data['priority'] ?? '', ['low', 'normal', 'high', 'urgent']) ? $data['priority'] : 'normal';
            $expiresAt     = !empty($data['expires_at']) ? $data['expires_at'] : null;
            $senderEntityId = isset($data['sender_entity_id']) && is_numeric($data['sender_entity_id']) ? (int)$data['sender_entity_id'] : null;

            if ($recipientId <= 0) {
                ResponseFormatter::error('recipient_id is required and must be positive', 422);
                exit;
            }
            if ($title === '') {
                ResponseFormatter::error('title is required', 422);
                exit;
            }
            if ($message === '') {
                ResponseFormatter::error('message is required', 422);
                exit;
            }

            // Parse extra JSON data (must always be an array for Notification::send)
            if (!empty($data['data'])) {
                if (is_string($data['data'])) {
                    $decoded = json_decode($data['data'], true);
                    $extraData = is_array($decoded) ? $decoded : [];
                } elseif (is_array($data['data'])) {
                    $extraData = $data['data'];
                }
            }

            // Parse device_ids for targeted push delivery
            $deviceIds = [];
            if (!empty($data['device_ids']) && is_array($data['device_ids'])) {
                $deviceIds = array_map('intval', array_filter($data['device_ids'], 'is_numeric'));
            }

            // Validate channels — filter empty strings and invalid values
            $channels = array_filter($channels, fn($ch) => is_string($ch) && $ch !== '');
            $validChannels = ['database', 'push', 'email', 'sms'];
            $channels = array_values(array_intersect($channels, $validChannels));
            if (empty($channels)) {
                $channels = ['database'];
            }

            $result = \Notification::send(
                $recipientId,
                $recipientType,
                $tenantId,
                $typeCode,
                $title,
                $message,
                $extraData,
                $channels,
                $priority,
                $expiresAt,
                $senderEntityId,
                $deviceIds
            );

            ResponseFormatter::success($result, 'Notification sent successfully', 201);
            exit;
        }

        // ── Bulk send notification to multiple recipients ──
        if ($lastSegment === 'send-bulk') {
            require_once dirname(__DIR__, 2) . '/shared/helpers/notification.php';
            \Notification::setPDO($pdo);

            $userIds       = isset($data['user_ids']) && is_array($data['user_ids']) ? array_map('intval', array_filter($data['user_ids'], 'is_numeric')) : [];
            $tenantId      = isset($data['tenant_id']) && is_numeric($data['tenant_id']) ? (int)$data['tenant_id'] : 1;
            $typeCode      = !empty($data['type_code']) ? $data['type_code'] : 'general';
            $title         = trim($data['title'] ?? '');
            $message       = trim($data['message'] ?? '');
            $channels      = isset($data['channels']) && is_array($data['channels']) ? $data['channels'] : ['database'];
            $priority      = in_array($data['priority'] ?? '', ['low', 'normal', 'high', 'urgent']) ? $data['priority'] : 'normal';
            $extraData     = [];

            if (empty($userIds)) {
                ResponseFormatter::error('user_ids array is required and must not be empty', 422);
                exit;
            }
            if (count($userIds) > 5000) {
                ResponseFormatter::error('Maximum 5000 recipients per bulk send', 422);
                exit;
            }
            if ($title === '') {
                ResponseFormatter::error('title is required', 422);
                exit;
            }
            if ($message === '') {
                ResponseFormatter::error('message is required', 422);
                exit;
            }

            // Parse extra JSON data
            if (!empty($data['data'])) {
                if (is_string($data['data'])) {
                    $decoded = json_decode($data['data'], true);
                    $extraData = is_array($decoded) ? $decoded : [];
                } elseif (is_array($data['data'])) {
                    $extraData = $data['data'];
                }
            }

            // Validate channels
            $channels = array_filter($channels, fn($ch) => is_string($ch) && $ch !== '');
            $validChannels = ['database', 'push', 'email', 'sms'];
            $channels = array_values(array_intersect($channels, $validChannels));
            if (empty($channels)) {
                $channels = ['database'];
            }

            $result = \Notification::sendBulk(
                $userIds,
                $typeCode,
                $title,
                $message,
                $extraData,
                $channels,
                $tenantId
            );

            ResponseFormatter::success($result, 'Bulk notification processed', 201);
            exit;
        }

        $newId = $controller->create($data);
        ResponseFormatter::success(['id' => $newId], 'Created successfully', 201);
        exit;
    }

    if ($method === 'PUT') {
        $updatedId = $controller->update($data);
        ResponseFormatter::success(['id' => $updatedId], 'Updated successfully');
        exit;
    }

    if ($method === 'DELETE') {
        $id = $data['id'] ?? null;
        if (empty($id) || !is_numeric($id)) {
            ResponseFormatter::error('Missing or invalid id for deletion', 400);
            exit;
        }
        $controller->delete((int)$id);
        ResponseFormatter::success(['deleted' => true], 'Deleted successfully');
        exit;
    }

    ResponseFormatter::error('Method not allowed', 405);

} catch (InvalidArgumentException $e) {
    safe_log('warning', 'notifications.validation', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 422);
} catch (RuntimeException $e) {
    safe_log('error', 'notifications.runtime', ['error' => $e->getMessage()]);
    ResponseFormatter::error($e->getMessage(), 400);
} catch (Throwable $e) {
    $trace = array_map(
        fn($f) => ($f['file'] ?? '?') . ':' . ($f['line'] ?? '?') . ' ' . ($f['class'] ?? '') . ($f['type'] ?? '') . ($f['function'] ?? ''),
        array_slice($e->getTrace(), 0, 5)
    );
    safe_log('critical', 'notifications.fatal', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
        'trace' => $trace,
        'GET'   => $_GET,
    ]);
    // Surface the actual error in development; hide in production
    $msg = (defined('IS_DEBUG') && IS_DEBUG) ? $e->getMessage() : 'Internal server error';
    ResponseFormatter::error($msg, 500);
}
