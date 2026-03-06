<?php
declare(strict_types=1);

/**
 * api/routes/pos_sessions.php
 * POS Session Management API
 *
 * GET    ?action=current          – active session for entity
 * GET    ?action=list             – paginated sessions
 * GET    ?id=<n>                  – single session
 * POST   action=open              – open new session
 * POST   action=close             – close session
 * POST   action=create_order      – create POS order with items
 * GET    ?action=session_orders   – orders for a session
 */

$baseDir = dirname(__DIR__);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';
require_once $baseDir . '/shared/helpers/safe_helpers.php';
require_once $baseDir . '/shared/config/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    ResponseFormatter::error('Database not initialized', 500);
    exit;
}

$tenantId = isset($_GET['tenant_id']) && is_numeric($_GET['tenant_id'])
    ? (int)$_GET['tenant_id']
    : (isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : null);

if ($tenantId === null) {
    ResponseFormatter::error('Unauthorized: tenant not found', 401);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$raw    = file_get_contents('php://input');
$data   = ($raw !== '' && $raw !== false) ? (json_decode($raw, true) ?? []) : [];
$action = $_GET['action'] ?? $data['action'] ?? '';

// ─────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────
function pos_json_ok($d = [], int $code = 200): void
{
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8', true, $code);
    $out = is_array($d) ? array_merge(['success' => true], $d) : ['success' => true, 'data' => $d];
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

function pos_json_error(string $m, int $code = 400): void
{
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8', true, $code);
    echo json_encode(['success' => false, 'message' => $m], JSON_UNESCAPED_UNICODE);
    exit;
}

function pos_fetch_all(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $type = is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue($k, $v, $type);
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function pos_fetch_one(PDO $pdo, string $sql, array $params = []): ?array
{
    $rows = pos_fetch_all($pdo, $sql, $params);
    return $rows[0] ?? null;
}

// ─────────────────────────────────────────────
// OPTIONS pre-flight
// ─────────────────────────────────────────────
if ($method === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    http_response_code(204);
    exit;
}

try {
    // ═══════════════════════════════════════
    // GET actions
    // ═══════════════════════════════════════
    if ($method === 'GET') {

        // GET ?action=current[&entity_id=N]
        if ($action === 'current') {
            $entityId = isset($_GET['entity_id']) && is_numeric($_GET['entity_id'])
                ? (int)$_GET['entity_id'] : null;

            $sql = "SELECT ps.*, 
                           CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) AS cashier_name,
                           e.store_name
                    FROM pos_sessions ps
                    LEFT JOIN users u ON ps.cashier_user_id = u.id
                    LEFT JOIN entities e ON ps.entity_id = e.id
                    WHERE ps.tenant_id = :tenant_id
                      AND ps.status = 'open'";
            $params = [':tenant_id' => $tenantId];

            if ($entityId) {
                $sql .= ' AND ps.entity_id = :entity_id';
                $params[':entity_id'] = $entityId;
            }

            $sql .= ' ORDER BY ps.opened_at DESC LIMIT 1';
            $session = pos_fetch_one($pdo, $sql, $params);
            pos_json_ok(['session' => $session]);
        }

        // GET ?action=list[&page&per_page&entity_id&status]
        if ($action === 'list') {
            $page    = max(1, (int)($_GET['page'] ?? 1));
            $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 25)));
            $offset  = ($page - 1) * $perPage;
            $entityId = isset($_GET['entity_id']) && is_numeric($_GET['entity_id'])
                ? (int)$_GET['entity_id'] : null;
            $status = $_GET['status'] ?? null;

            $where  = ['ps.tenant_id = :tenant_id'];
            $params = [':tenant_id' => $tenantId];

            if ($entityId) {
                $where[] = 'ps.entity_id = :entity_id';
                $params[':entity_id'] = $entityId;
            }
            if ($status && in_array($status, ['open', 'closed'], true)) {
                $where[] = 'ps.status = :status';
                $params[':status'] = $status;
            }

            $whereClause = 'WHERE ' . implode(' AND ', $where);
            $count = (int)(pos_fetch_one($pdo,
                "SELECT COUNT(*) AS n FROM pos_sessions ps $whereClause", $params)['n'] ?? 0);

            $params[':limit']  = $perPage;
            $params[':offset'] = $offset;
            $rows = pos_fetch_all($pdo, "
                SELECT ps.*,
                       CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) AS cashier_name,
                       e.store_name
                FROM pos_sessions ps
                LEFT JOIN users u ON ps.cashier_user_id = u.id
                LEFT JOIN entities e ON ps.entity_id = e.id
                $whereClause
                ORDER BY ps.opened_at DESC
                LIMIT :limit OFFSET :offset
            ", $params);

            pos_json_ok([
                'sessions'    => $rows,
                'total'       => $count,
                'page'        => $page,
                'per_page'    => $perPage,
                'total_pages' => (int)ceil($count / $perPage),
            ]);
        }

        // GET ?action=session_orders&session_id=N
        if ($action === 'session_orders') {
            $sessionId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;
            if (!$sessionId) pos_json_error('session_id required');

            $orders = pos_fetch_all($pdo, "
                SELECT o.*,
                       CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) AS customer_name
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.id
                WHERE o.tenant_id = :tid AND o.pos_session_id = :sid
                ORDER BY o.created_at DESC
            ", [':tid' => $tenantId, ':sid' => $sessionId]);

            pos_json_ok(['orders' => $orders]);
        }

        // GET ?id=N  – single session
        if (isset($_GET['id']) && is_numeric($_GET['id'])) {
            $row = pos_fetch_one($pdo, "
                SELECT ps.*,
                       CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) AS cashier_name,
                       e.store_name
                FROM pos_sessions ps
                LEFT JOIN users u ON ps.cashier_user_id = u.id
                LEFT JOIN entities e ON ps.entity_id = e.id
                WHERE ps.id = :id AND ps.tenant_id = :tid
            ", [':id' => (int)$_GET['id'], ':tid' => $tenantId]);

            if (!$row) pos_json_error('Session not found', 404);
            pos_json_ok(['session' => $row]);
        }

        pos_json_error('Unknown action', 400);
    }

    // ═══════════════════════════════════════
    // POST actions
    // ═══════════════════════════════════════
    if ($method === 'POST') {

        // POST action=open
        if ($action === 'open') {
            $entityId        = isset($data['entity_id']) ? (int)$data['entity_id'] : 0;
            $openingBalance  = isset($data['opening_balance']) ? (float)$data['opening_balance'] : 0.00;
            $cashierUserId   = isset($data['cashier_user_id']) ? (int)$data['cashier_user_id'] : null;

            if (!$entityId) pos_json_error('entity_id required');

            // Check no open session already
            $existing = pos_fetch_one($pdo, "
                SELECT id FROM pos_sessions
                WHERE tenant_id = :tid AND entity_id = :eid AND status = 'open'
                LIMIT 1
            ", [':tid' => $tenantId, ':eid' => $entityId]);

            if ($existing) {
                pos_json_error('A session is already open for this entity. Close it first.');
            }

            $stmt = $pdo->prepare("
                INSERT INTO pos_sessions (tenant_id, entity_id, cashier_user_id, opening_balance, status, opened_at)
                VALUES (:tid, :eid, :cuid, :bal, 'open', NOW())
            ");
            $stmt->execute([
                ':tid'  => $tenantId,
                ':eid'  => $entityId,
                ':cuid' => $cashierUserId,
                ':bal'  => $openingBalance,
            ]);
            $sessionId = (int)$pdo->lastInsertId();

            $session = pos_fetch_one($pdo, "
                SELECT ps.*,
                       CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) AS cashier_name,
                       e.store_name
                FROM pos_sessions ps
                LEFT JOIN users u ON ps.cashier_user_id = u.id
                LEFT JOIN entities e ON ps.entity_id = e.id
                WHERE ps.id = :id
            ", [':id' => $sessionId]);

            pos_json_ok(['session' => $session, 'message' => 'Session opened successfully'], 201);
        }

        // POST action=close
        if ($action === 'close') {
            $sessionId      = isset($data['session_id']) ? (int)$data['session_id'] : 0;
            $closingBalance = isset($data['closing_balance']) ? (float)$data['closing_balance'] : null;

            if (!$sessionId) pos_json_error('session_id required');

            $session = pos_fetch_one($pdo,
                "SELECT * FROM pos_sessions WHERE id = :id AND tenant_id = :tid",
                [':id' => $sessionId, ':tid' => $tenantId]);

            if (!$session) pos_json_error('Session not found', 404);
            if ($session['status'] === 'closed') pos_json_error('Session is already closed');

            // Sum up totals from linked orders
            $totals = pos_fetch_one($pdo, "
                SELECT
                    COALESCE(SUM(CASE WHEN o.payment_status='paid' THEN o.grand_total ELSE 0 END), 0) AS total_sales,
                    COALESCE(SUM(CASE WHEN p.payment_method LIKE '%cash%' THEN o.grand_total ELSE 0 END), 0) AS total_cash
                FROM orders o
                LEFT JOIN (
                    SELECT order_id, MAX(payment_method) AS payment_method FROM payments GROUP BY order_id
                ) p ON p.order_id = o.id
                WHERE o.pos_session_id = :sid AND o.tenant_id = :tid
            ", [':sid' => $sessionId, ':tid' => $tenantId]) ?? [];

            $stmt = $pdo->prepare("
                UPDATE pos_sessions
                SET status = 'closed',
                    closed_at = NOW(),
                    closing_balance = :cb,
                    total_cash  = :tc
                WHERE id = :id AND tenant_id = :tid
            ");
            $stmt->execute([
                ':cb'  => $closingBalance ?? $session['opening_balance'],
                ':tc'  => (float)($totals['total_cash'] ?? 0),
                ':id'  => $sessionId,
                ':tid' => $tenantId,
            ]);

            $updated = pos_fetch_one($pdo,
                "SELECT * FROM pos_sessions WHERE id = :id",
                [':id' => $sessionId]);

            pos_json_ok(['session' => $updated, 'message' => 'Session closed successfully']);
        }

        // POST action=create_order  – full POS sale
        if ($action === 'create_order') {
            $sessionId  = isset($data['session_id']) ? (int)$data['session_id'] : 0;
            $entityId   = isset($data['entity_id']) ? (int)$data['entity_id'] : 0;
            $items      = $data['items'] ?? [];
            $paymentMethod = $data['payment_method'] ?? 'cash';
            $amountPaid    = isset($data['amount_paid']) ? (float)$data['amount_paid'] : 0.0;
            $discount      = isset($data['discount_amount']) ? (float)$data['discount_amount'] : 0.0;
            $notes         = $data['notes'] ?? '';
            $cashierUserId = isset($data['cashier_user_id']) ? (int)$data['cashier_user_id'] : null;
            $customerId    = isset($data['customer_id']) ? (int)$data['customer_id'] : 1; // default walk-in

            if (!$sessionId) pos_json_error('session_id required');
            if (!$entityId) pos_json_error('entity_id required');
            if (empty($items)) pos_json_error('Order must have at least one item');

            // Verify session is open
            $session = pos_fetch_one($pdo,
                "SELECT * FROM pos_sessions WHERE id=:id AND tenant_id=:tid AND status='open'",
                [':id' => $sessionId, ':tid' => $tenantId]);
            if (!$session) pos_json_error('Session not found or not open', 404);

            // Calculate totals
            $subtotal  = 0.0;
            $taxAmount = 0.0;
            $orderItems = [];

            foreach ($items as $item) {
                $productId = (int)($item['product_id'] ?? 0);
                $qty       = max(1, (int)($item['quantity'] ?? 1));
                $unitPrice = (float)($item['unit_price'] ?? 0);
                $salePrice = isset($item['sale_price']) ? (float)$item['sale_price'] : $unitPrice;
                $taxRate   = (float)($item['tax_rate'] ?? 0);
                $productName = $item['product_name'] ?? 'Product';
                $sku         = $item['sku'] ?? '';

                $itemSubtotal = $salePrice * $qty;
                $itemTax      = $itemSubtotal * ($taxRate / 100);
                $itemTotal    = $itemSubtotal + $itemTax;

                $subtotal  += $itemSubtotal;
                $taxAmount += $itemTax;

                $orderItems[] = [
                    'product_id'   => $productId,
                    'product_name' => $productName,
                    'sku'          => $sku,
                    'quantity'     => $qty,
                    'unit_price'   => $unitPrice,
                    'sale_price'   => $salePrice,
                    'tax_rate'     => $taxRate,
                    'tax_amount'   => $itemTax,
                    'subtotal'     => $itemSubtotal,
                    'total'        => $itemTotal,
                ];
            }

            $totalAmount = $subtotal + $taxAmount;
            $grandTotal  = max(0, $totalAmount - $discount);

            // Generate order number
            $orderNumber = 'POS-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 6));

            $pdo->beginTransaction();
            try {
                // Insert order
                $stmt = $pdo->prepare("
                    INSERT INTO orders (
                        tenant_id, entity_id, order_number, user_id, order_type,
                        status, payment_status, subtotal, tax_amount, discount_amount,
                        total_amount, grand_total, currency_code,
                        customer_notes, pos_session_id, cashier_user_id,
                        branch_entity_id, sales_channel, created_at
                    ) VALUES (
                        :tid, :eid, :onum, :uid, 'pos',
                        'completed', 'paid', :sub, :tax, :disc,
                        :tot, :grand, 'SAR',
                        :notes, :sid, :cuid,
                        :eid, 'pos', NOW()
                    )
                ");
                $stmt->execute([
                    ':tid'   => $tenantId,
                    ':eid'   => $entityId,
                    ':onum'  => $orderNumber,
                    ':uid'   => $customerId,
                    ':sub'   => $subtotal,
                    ':tax'   => $taxAmount,
                    ':disc'  => $discount,
                    ':tot'   => $totalAmount,
                    ':grand' => $grandTotal,
                    ':notes' => $notes,
                    ':sid'   => $sessionId,
                    ':cuid'  => $cashierUserId,
                ]);
                $orderId = (int)$pdo->lastInsertId();

                // Insert order items
                $iStmt = $pdo->prepare("
                    INSERT INTO order_items (
                        tenant_id, order_id, entity_id, product_id,
                        product_name, sku, quantity, unit_price, sale_price,
                        tax_rate, tax_amount, subtotal, total, currency_code,
                        created_at
                    ) VALUES (
                        :tid, :oid, :eid, :pid,
                        :pname, :sku, :qty, :up, :sp,
                        :tr, :ta, :sub, :tot, 'SAR', NOW()
                    )
                ");
                foreach ($orderItems as $oi) {
                    $iStmt->execute([
                        ':tid'   => $tenantId,
                        ':oid'   => $orderId,
                        ':eid'   => $entityId,
                        ':pid'   => $oi['product_id'],
                        ':pname' => $oi['product_name'],
                        ':sku'   => $oi['sku'],
                        ':qty'   => $oi['quantity'],
                        ':up'    => $oi['unit_price'],
                        ':sp'    => $oi['sale_price'],
                        ':tr'    => $oi['tax_rate'],
                        ':ta'    => $oi['tax_amount'],
                        ':sub'   => $oi['subtotal'],
                        ':tot'   => $oi['total'],
                    ]);
                }

                // Update session totals
                $pdo->prepare("
                    UPDATE pos_sessions
                    SET total_cash = total_cash + :cash,
                        total_card = total_card + :card
                    WHERE id = :id
                ")->execute([
                    ':cash' => $paymentMethod === 'cash' ? $grandTotal : 0,
                    ':card' => $paymentMethod === 'card' ? $grandTotal : 0,
                    ':id'   => $sessionId,
                ]);

                $pdo->commit();

                pos_json_ok([
                    'order_id'     => $orderId,
                    'order_number' => $orderNumber,
                    'grand_total'  => $grandTotal,
                    'change'       => max(0, $amountPaid - $grandTotal),
                    'message'      => 'Order created successfully',
                ], 201);

            } catch (\Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }

        pos_json_error('Unknown action', 400);
    }

    pos_json_error('Method not allowed', 405);

} catch (\Throwable $e) {
    safe_log('critical', 'pos_sessions.fatal', ['error' => $e->getMessage()]);
    pos_json_error('Internal Server Error: ' . $e->getMessage(), 500);
}
