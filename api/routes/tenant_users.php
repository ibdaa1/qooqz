<?php
declare(strict_types=1);

/**
 * Route: /api/tenant_users
 *
 * Handles list/get/create/update/delete for tenant_users.
 * Expects the models/services/controllers already implemented under API_VERSION_PATH.
 */

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/shared/core/ResponseFormatter.php';
require_once dirname(__DIR__) . '/shared/helpers/safe_helpers.php';
require_once dirname(__DIR__) . '/shared/config/db.php';

if (!defined('API_VERSION_PATH')) {
    define('API_VERSION_PATH', dirname(__DIR__) . '/v1');
}

require_once API_VERSION_PATH . '/models/tenant_users/repositories/PdoTenant_usersRepository.php';
require_once API_VERSION_PATH . '/models/tenant_users/validators/Tenant_usersValidator.php';
require_once API_VERSION_PATH . '/models/tenant_users/services/Tenant_usersService.php';
require_once API_VERSION_PATH . '/models/tenant_users/controllers/Tenant_usersController.php';

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    return;
}

// Current tenant from session (or default)
$tenantId = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 1;

// Load admin context to get permission functions
if (file_exists(dirname(__DIR__, 2) . '/admin/includes/admin_context.php')) {
    require_once dirname(__DIR__, 2) . '/admin/includes/admin_context.php';
}

// Get current user's entity_id and determine permission scope
$currentUserId = null;
$currentEntityId = null;
$isSuperAdmin = false;
$canViewAll = false;
$canViewTenant = false;
$canViewOwn = false;

if (function_exists('admin_user')) {
    $adminUser = admin_user();
    $currentUserId = (int)($adminUser['id'] ?? 0);
    
    // Check if super admin
    if (function_exists('is_super_admin')) {
        $isSuperAdmin = is_super_admin();
    }
    
    // Get resource permissions
    if (function_exists('can_view_all')) {
        $canViewAll = can_view_all('tenant_users');
    }
    if (function_exists('can_view_tenant')) {
        $canViewTenant = can_view_tenant('tenant_users');
    }
    if (function_exists('can_view_own')) {
        $canViewOwn = can_view_own('tenant_users');
    }
    
    // Get user's entity_id from tenant_users table
    if ($currentUserId > 0 && !$isSuperAdmin && !$canViewAll) {
        $stmt = $pdo->prepare("SELECT entity_id FROM tenant_users WHERE user_id = ? AND tenant_id = ? LIMIT 1");
        $stmt->execute([$currentUserId, $tenantId]);
        $userTenant = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($userTenant && isset($userTenant['entity_id'])) {
            $currentEntityId = (int)$userTenant['entity_id'];
        }
    }
}

// Instantiate dependencies
$repo = new PdoTenant_usersRepository($pdo);
$validator = new Tenant_usersValidator();
$service = new Tenant_usersService($repo, $validator);
$controller = new Tenant_usersController($service);

/**
 * Try to extract numeric id from ?id= or path (/api/tenant_users/123)
 */
function extractIdFromRequest(): ?int
{
    if (!empty($_GET['id']) && is_numeric($_GET['id'])) return (int)$_GET['id'];
    if (!empty($_SERVER['PATH_INFO'])) {
        $parts = explode('/', trim((string)$_SERVER['PATH_INFO'], '/'));
        $last = end($parts);
        if (is_numeric($last)) return (int)$last;
    }
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $uri = explode('?', $uri, 2)[0];
    if (preg_match('#/(\d+)(?:/)?$#', $uri, $m)) return (int)$m[1];
    return null;
}

/**
 * Read JSON body safely
 */
function jsonBody(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode((string)$raw, true);
    return is_array($data) ? $data : [];
}

/**
 * Get acting user id for audit logging (try session/admin_user, header, or query)
 */
function getActingUserId(): ?int
{
    if (function_exists('admin_user')) {
        $u = admin_user();
        if (is_array($u) && !empty($u['id'])) return (int)$u['id'];
    }
    $hdr = $_SERVER['HTTP_X_USER_ID'] ?? null;
    if ($hdr !== null && is_numeric($hdr)) return (int)$hdr;
    if (!empty($_GET['user_id']) && is_numeric($_GET['user_id'])) return (int)$_GET['user_id'];
    return null;
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        $id = extractIdFromRequest();
        if ($id !== null) {
            $row = $controller->get($tenantId, $id);
            ResponseFormatter::success($row);
            return;
        }

        // list with filters and pagination — pass $_GET to controller
        $query = $_GET ?? [];
        
        // Apply permission-based filtering
        if (!$isSuperAdmin) {
            // If user can only view tenant data, ensure tenant_id is set
            if ($canViewTenant && !$canViewAll) {
                $query['tenant_id'] = $tenantId;
            }
            
            // If user can only view their entity's data, add entity filter
            if (!$canViewAll && !$canViewTenant && $canViewOwn && $currentEntityId !== null) {
                $query['entity_id'] = $currentEntityId;
            }
            
            // If user can only view own data and not others, filter by user_id
            if (!$canViewAll && !$canViewTenant && $canViewOwn && $currentEntityId === null && $currentUserId > 0) {
                $query['user_id'] = $currentUserId;
            }
        }
        
        $result = $controller->list($tenantId, $query);
        ResponseFormatter::success($result);
        return;
    }

    if ($method === 'POST') {
        $data = jsonBody();
        $actingUserId = getActingUserId();
        // create may throw InvalidArgumentException on validation or PDOException on write failure
        $row = $controller->create($tenantId, $data, $actingUserId);
        ResponseFormatter::success($row, 201);
        return;
    }

    if ($method === 'PUT') {
        $data = jsonBody();
        $actingUserId = getActingUserId();
        $row = $controller->update($tenantId, $data, $actingUserId);
        ResponseFormatter::success($row);
        return;
    }

    if ($method === 'DELETE') {
        $data = jsonBody();
        // allow id in path or body
        if (empty($data['id'])) {
            $maybeId = extractIdFromRequest();
            if ($maybeId !== null) $data['id'] = $maybeId;
        }
        $controller->delete($tenantId, $data);
        ResponseFormatter::success(['deleted' => true]);
        return;
    }

    ResponseFormatter::error('Method not allowed', 405);
} catch (InvalidArgumentException $e) {
    ResponseFormatter::error($e->getMessage(), 422);
} catch (RuntimeException $e) {
    ResponseFormatter::error($e->getMessage(), 404);
} catch (PDOException $e) {
    // DB error: log details for ops and return 500
    safe_log('error', 'Tenant_users DB error', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? null,
        'GET' => $_GET ?? null,
        'BODY' => file_get_contents('php://input')
    ]);
    ResponseFormatter::error('Database error', 500);
} catch (Throwable $e) {
    safe_log('error', 'Tenant_users route failed', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? null,
        'GET' => $_GET ?? null,
        'BODY' => file_get_contents('php://input')
    ]);
    ResponseFormatter::error('Internal server error', 500);
}