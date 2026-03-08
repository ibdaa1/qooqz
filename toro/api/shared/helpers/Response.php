<?php
// htdocs/api/shared/helpers/Response.php
// Unified response helpers (compatible and safe).
// Save as UTF-8 without BOM.

// ============================================================
// Shared\Helpers\Response class (used by V1 namespaced code)
// ============================================================
namespace Shared\Helpers {
    final class Response
    {
        /**
         * Send a JSON response and exit.
         */
        public static function json(array $data, int $status = 200): void
        {
            if (!headers_sent()) {
                http_response_code($status);
                header('Content-Type: application/json; charset=utf-8');
                header('Cache-Control: no-cache, no-store, must-revalidate');
            }
            echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }
}

// ============================================================
// Global helper functions (backward-compatible legacy API)
// ============================================================
namespace {
    if (defined('API_RESPONSE_HELPER_LOADED')) {
        return;
    }
    define('API_RESPONSE_HELPER_LOADED', true);

    if (!defined('HTTP_OK'))                   define('HTTP_OK', 200);
    if (!defined('HTTP_CREATED'))              define('HTTP_CREATED', 201);
    if (!defined('HTTP_NO_CONTENT'))           define('HTTP_NO_CONTENT', 204);
    if (!defined('HTTP_BAD_REQUEST'))          define('HTTP_BAD_REQUEST', 400);
    if (!defined('HTTP_UNAUTHORIZED'))         define('HTTP_UNAUTHORIZED', 401);
    if (!defined('HTTP_FORBIDDEN'))            define('HTTP_FORBIDDEN', 403);
    if (!defined('HTTP_NOT_FOUND'))            define('HTTP_NOT_FOUND', 404);
    if (!defined('HTTP_METHOD_NOT_ALLOWED'))   define('HTTP_METHOD_NOT_ALLOWED', 405);
    if (!defined('HTTP_UNPROCESSABLE_ENTITY')) define('HTTP_UNPROCESSABLE_ENTITY', 422);
    if (!defined('HTTP_INTERNAL_SERVER_ERROR'))define('HTTP_INTERNAL_SERVER_ERROR', 500);
    if (!defined('HTTP_TOO_MANY_REQUESTS'))    define('HTTP_TOO_MANY_REQUESTS', 429);

    if (!defined('ERROR_CODE_VALIDATION'))        define('ERROR_CODE_VALIDATION', 1004);
    if (!defined('ERROR_CODE_AUTHENTICATION'))    define('ERROR_CODE_AUTHENTICATION', 1001);
    if (!defined('ERROR_CODE_AUTHORIZATION'))     define('ERROR_CODE_AUTHORIZATION', 1002);
    if (!defined('ERROR_CODE_NOT_FOUND'))         define('ERROR_CODE_NOT_FOUND', 1003);
    if (!defined('ERROR_CODE_DATABASE'))          define('ERROR_CODE_DATABASE', 1006);
    if (!defined('ERROR_CODE_SERVER'))            define('ERROR_CODE_SERVER', 1005);
    if (!defined('ERROR_CODE_PAYMENT'))           define('ERROR_CODE_PAYMENT', 1007);
    if (!defined('ERROR_CODE_INSUFFICIENT_STOCK'))define('ERROR_CODE_INSUFFICIENT_STOCK', 1008);
    if (!defined('ERROR_CODE_INVALID_COUPON'))    define('ERROR_CODE_INVALID_COUPON', 1009);

    if (!defined('LOG_ENABLED'))  define('LOG_ENABLED', false);
    if (!defined('LOG_FILE_API')) define('LOG_FILE_API', sys_get_temp_dir() . '/api_response.log');
    if (!defined('DEBUG_MODE'))   define('DEBUG_MODE', false);

    function respond($payload, $httpCode = HTTP_OK)
    {
        if (!headers_sent()) {
            http_response_code((int)$httpCode);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
        if (!is_array($payload) && !is_object($payload)) {
            $payload = ['success' => false, 'message' => (string)$payload];
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    function respond_success($data = [], $message = 'Success', $httpCode = HTTP_OK, $meta = [])
    {
        $out = ['success' => true, 'message' => $message, 'data' => $data,
                'timestamp' => time(), 'datetime' => date('Y-m-d H:i:s')];
        if (!empty($meta)) $out['meta'] = $meta;
        respond($out, $httpCode);
    }

    function respond_error($message = 'Error occurred', $httpCode = HTTP_BAD_REQUEST, $errors = null, $errorCode = null)
    {
        $out = ['success' => false, 'message' => $message,
                'timestamp' => time(), 'datetime' => date('Y-m-d H:i:s')];
        if ($errors !== null)    $out['errors']     = $errors;
        if ($errorCode !== null) $out['error_code'] = $errorCode;
        respond($out, $httpCode);
    }

    function json_input()
    {
        $raw = @file_get_contents('php://input');
        if (!$raw) return null;
        $data = json_decode($raw, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $data : null;
    }

    function get_json_input()
    {
        $json = json_input();
        if (is_array($json) && !empty($json)) {
            return array_merge($_POST, $json);
        }
        return $_POST;
    }

    function respond_created($data = null, $message = 'Created successfully')
    {
        respond_success($data, $message, HTTP_CREATED);
    }

    function respond_not_found($message = 'Resource not found')
    {
        respond_error($message, HTTP_NOT_FOUND, null, ERROR_CODE_NOT_FOUND);
    }

    function respond_validation_error($errors, $message = 'Validation failed')
    {
        respond_error($message, HTTP_UNPROCESSABLE_ENTITY, $errors, ERROR_CODE_VALIDATION);
    }

    function respond_server_error($message = 'Internal server error')
    {
        respond_error($message, HTTP_INTERNAL_SERVER_ERROR, null, ERROR_CODE_SERVER);
    }
} // end global namespace
