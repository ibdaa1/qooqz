<?php
declare(strict_types=1);

// api/routes/theme_translations.php

ini_set('display_errors', '0');
ini_set('log_errors', '1');

$baseDir = dirname(__DIR__);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';
require_once API_VERSION_PATH . '/models/themes/repositories/PdoThemeTranslationsRepository.php';

header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

$repo     = new PdoThemeTranslationsRepository($pdo);
$method   = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$rawInput = file_get_contents('php://input');
$data     = $rawInput ? (json_decode($rawInput, true) ?? []) : [];

$tenantId = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : null;

try {
    switch ($method) {
        case 'GET':
            $themeId = isset($_GET['theme_id']) ? (int)$_GET['theme_id'] : null;
            if ($themeId === null) {
                ResponseFormatter::error('theme_id is required', 400);
                exit;
            }
            $items = $repo->getByTheme($themeId, $tenantId);
            ResponseFormatter::success($items);
            break;

        case 'POST':
        case 'PUT':
            // Support PUT with ?id=X for routing compatibility
            if ($method === 'PUT' && isset($_GET['id']) && !isset($data['id'])) {
                $data['id'] = (int)$_GET['id'];
            }
            if (empty($data['theme_id']) || empty($data['language_code'])) {
                ResponseFormatter::error('theme_id and language_code are required', 400);
                exit;
            }
            if ($tenantId !== null && !isset($data['tenant_id'])) {
                $data['tenant_id'] = $tenantId;
            }
            $id = $repo->save($data);
            ResponseFormatter::success(['id' => $id], 'Saved successfully', 201);
            break;

        case 'DELETE':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($data['id']) ? (int)$data['id'] : null);
            if (!$id) {
                ResponseFormatter::error('id is required', 400);
                exit;
            }
            $repo->delete($id);
            ResponseFormatter::success([], 'Deleted successfully');
            break;

        default:
            ResponseFormatter::error('Method not allowed', 405);
    }
} catch (Throwable $e) {
    error_log('[theme_translations] ' . $e->getMessage());
    ResponseFormatter::error('Internal server error', 500);
}
