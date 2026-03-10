<?php
declare(strict_types=1);

/**
 * /toro/admin/fragments/orders.php
 * Production-ready Orders management fragment for the admin panel.
 *
 * Permissions: view (read-only) + edit (status update) + delete (super admin).
 * Orders are created on the frontend; no create UI is exposed here.
 */

// ════════════════════════════════════════════════════════════
// DETECT REQUEST TYPE
// ════════════════════════════════════════════════════════════
$isAjax      = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$isEmbedded  = isset($_GET['embedded']) || isset($_POST['embedded']);
$isFragment  = $isAjax || $isEmbedded;

// ════════════════════════════════════════════════════════════
// LOAD CONTEXT / HEADER
// ════════════════════════════════════════════════════════════
if ($isFragment) {
    require_once __DIR__ . '/../includes/admin_context.php';
} else {
    require_once __DIR__ . '/../includes/header.php';
}

// ════════════════════════════════════════════════════════════
// VERIFY USER IS LOGGED IN
// ════════════════════════════════════════════════════════════
if (!is_admin_logged_in()) {
    if ($isFragment) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    } else {
        header('Location: /toro/admin/login.php');
        exit;
    }
}

// ════════════════════════════════════════════════════════════
// GET USER CONTEXT
// ════════════════════════════════════════════════════════════
$user     = admin_user();
$lang     = admin_lang();
$dir      = admin_dir();
$csrf     = admin_csrf();
$tenantId = admin_tenant_id();

// ════════════════════════════════════════════════════════════
// CHECK PERMISSIONS
// ════════════════════════════════════════════════════════════
$canViewAll    = can_view_all('orders');
$canViewOwn    = can_view_own('orders');
$canViewTenant = can_view_tenant('orders');
$canView       = $canViewAll || $canViewOwn || $canViewTenant || is_super_admin();

$canEditAll  = can_edit_all('orders');
$canEditOwn  = can_edit_own('orders');
$canEdit     = $canEditAll || $canEditOwn || is_super_admin();

// Delete is reserved for super admins; also honour explicit can_delete_all permission
$canDelete   = is_super_admin() || can_delete_all('orders');

// Orders are not created through the admin UI
$canCreate   = false;

if (!$canView) {
    if ($isFragment) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    } else {
        http_response_code(403);
        die('Access denied: You do not have permission to view orders.');
    }
}

// ════════════════════════════════════════════════════════════
// API BASE
// ════════════════════════════════════════════════════════════
$apiBase = '/toro/api/v1';

// ════════════════════════════════════════════════════════════
// THEME VARS HELPER
// ════════════════════════════════════════════════════════════
if (!function_exists('renderFragmentThemeVars')) {
    function renderFragmentThemeVars(array $theme): void {
        echo ':root {' . PHP_EOL;
        foreach ($theme['color_settings'] ?? [] as $c) {
            if (empty($c['setting_key']) || !isset($c['color_value'])) continue;
            $k = htmlspecialchars($c['setting_key'], ENT_QUOTES);
            $h = htmlspecialchars(str_replace('_', '-', $c['setting_key']), ENT_QUOTES);
            $v = htmlspecialchars($c['color_value'], ENT_QUOTES);
            echo "    --{$k}: {$v};" . PHP_EOL;
            if ($h !== $k) echo "    --{$h}: {$v};" . PHP_EOL;
        }
        echo '}' . PHP_EOL;
    }
}
?>
<style id="db-theme-vars-orders">
<?php renderFragmentThemeVars($GLOBALS['ADMIN_UI']['theme'] ?? []); ?>
</style>
<link rel="stylesheet" href="/toro/admin/assets/css/pages/orders.css?v=<?= time() ?>">
<?php
// ════════════════════════════════════════════════════════════
// HTML OUTPUT
// ════════════════════════════════════════════════════════════
?>
<div id="orders-page"
     class="page-container"
     dir="<?= htmlspecialchars($dir, ENT_QUOTES) ?>"
     data-page="orders"
     data-lang="<?= htmlspecialchars($lang, ENT_QUOTES) ?>"
     data-tenant="<?= (int)$tenantId ?>">

    <!-- ── Page Header ────────────────────────────────────── -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title">Orders</h1>
            <p class="page-subtitle">Manage customer orders and update their statuses.</p>
        </div>
        <!-- Stats row -->
        <div class="page-header-stats">
            <div class="stat-badge" id="orders-total-count">
                <span class="stat-label">Total Orders</span>
                <span class="stat-value" id="orders-count-value">—</span>
            </div>
        </div>
    </div>

    <!-- ── Filter Card ────────────────────────────────────── -->
    <div class="filter-card card mb-4">
        <div class="card-body">
            <div class="row g-2 align-items-end">

                <!-- Search -->
                <div class="col-12 col-md-3">
                    <label class="form-label small fw-semibold" for="filter-search">Search</label>
                    <input type="text"
                           id="filter-search"
                           class="form-control form-control-sm"
                           placeholder="Order # or customer email">
                </div>

                <!-- Order Status -->
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-semibold" for="filter-status">Order Status</label>
                    <select id="filter-status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="pending">Pending</option>
                        <option value="processing">Processing</option>
                        <option value="shipped">Shipped</option>
                        <option value="delivered">Delivered</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="refunded">Refunded</option>
                    </select>
                </div>

                <!-- Payment Status -->
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-semibold" for="filter-payment-status">Payment</label>
                    <select id="filter-payment-status" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="paid">Paid</option>
                        <option value="pending">Pending</option>
                        <option value="failed">Failed</option>
                        <option value="refunded">Refunded</option>
                    </select>
                </div>

                <!-- Date From -->
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-semibold" for="filter-date-from">Date From</label>
                    <input type="date" id="filter-date-from" class="form-control form-control-sm">
                </div>

                <!-- Date To -->
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-semibold" for="filter-date-to">Date To</label>
                    <input type="date" id="filter-date-to" class="form-control form-control-sm">
                </div>

                <!-- Actions -->
                <div class="col-12 col-md-1 d-flex gap-2">
                    <button type="button" id="btn-filter-apply" class="btn btn-primary btn-sm w-100">Apply</button>
                    <button type="button" id="btn-filter-reset" class="btn btn-outline-secondary btn-sm w-100">Reset</button>
                </div>

            </div>
        </div>
    </div>

    <!-- ── Orders Table ───────────────────────────────────── -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="orders-table">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Order #</th>
                            <th>Customer Name</th>
                            <th>Email</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Date</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="orders-tbody">
                        <tr id="orders-loading-row">
                            <td colspan="8" class="text-center py-5 text-muted">
                                <div class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></div>
                                Loading orders…
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <!-- Pagination -->
        <div class="card-footer bg-transparent d-flex justify-content-between align-items-center flex-wrap gap-2">
            <small class="text-muted" id="orders-pagination-info"></small>
            <nav aria-label="Orders pagination">
                <ul class="pagination pagination-sm mb-0" id="orders-pagination"></ul>
            </nav>
        </div>
    </div>

</div><!-- /#orders-page -->

<!-- ══════════════════════════════════════════════════════════
     VIEW ORDER MODAL (large)
═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="order-view-modal" tabindex="-1" aria-labelledby="orderViewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">

            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0" id="orderViewModalLabel">Order Details</h5>
                    <small class="text-muted" id="modal-order-number"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div id="order-modal-loading" class="text-center py-5">
                    <div class="spinner-border" role="status"><span class="visually-hidden">Loading…</span></div>
                </div>
                <div id="order-modal-content" style="display:none;">

                    <!-- Customer Info -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="fw-bold mb-2">Customer Information</h6>
                            <table class="table table-sm table-borderless">
                                <tr><th class="text-muted fw-normal" style="width:40%">Name</th><td id="modal-customer-name">—</td></tr>
                                <tr><th class="text-muted fw-normal">Email</th><td id="modal-customer-email">—</td></tr>
                                <tr><th class="text-muted fw-normal">Phone</th><td id="modal-customer-phone">—</td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold mb-2">Shipping Address</h6>
                            <address id="modal-shipping-address" class="mb-0 text-muted small">—</address>
                        </div>
                    </div>

                    <!-- Order Items -->
                    <h6 class="fw-bold mb-2">Order Items</h6>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Product</th>
                                    <th class="text-center" style="width:80px">Qty</th>
                                    <th class="text-end" style="width:120px">Unit Price</th>
                                    <th class="text-end" style="width:120px">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody id="modal-order-items">
                                <tr><td colspan="4" class="text-muted">No items.</td></tr>
                            </tbody>
                            <tfoot id="modal-order-totals" class="table-group-divider">
                                <!-- Filled by JS: subtotal / discount / shipping / tax / total -->
                            </tfoot>
                        </table>
                    </div>

                    <!-- Status Update -->
                    <?php if ($canEdit): ?>
                    <div class="card bg-light">
                        <div class="card-body">
                            <h6 class="fw-bold mb-3">Update Order Status</h6>
                            <div class="d-flex align-items-center gap-3 flex-wrap">
                                <select id="modal-status-select" class="form-select form-select-sm" style="max-width:220px">
                                    <option value="pending">Pending</option>
                                    <option value="processing">Processing</option>
                                    <option value="shipped">Shipped</option>
                                    <option value="delivered">Delivered</option>
                                    <option value="cancelled">Cancelled</option>
                                    <option value="refunded">Refunded</option>
                                </select>
                                <button type="button"
                                        id="btn-update-status"
                                        class="btn btn-primary btn-sm"
                                        data-order-id="">
                                    Update Status
                                </button>
                                <span id="modal-status-msg" class="small text-success d-none"></span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                </div><!-- /#order-modal-content -->
            </div><!-- /.modal-body -->

            <div class="modal-footer">
                <?php if ($canDelete): ?>
                <button type="button" id="btn-delete-order" class="btn btn-outline-danger btn-sm me-auto" data-order-id="">
                    <i class="bi bi-trash me-1"></i>Delete Order
                </button>
                <?php endif; ?>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>

        </div>
    </div>
</div>

<!-- ── Toast Container ────────────────────────────────────── -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="orders-toast-container" style="z-index:1100"></div>

<!-- ════════════════════════════════════════════════════════
     CONFIG + SCRIPTS
═══════════════════════════════════════════════════════════ -->
<script>
window.ORDERS_CONFIG = {
    apiUrl:         '<?= $apiBase ?>/orders',
    csrfToken:      '<?= addslashes($csrf) ?>',
    lang:           '<?= addslashes($lang) ?>',
    dir:            '<?= addslashes($dir) ?>',
    canCreate:      <?= $canCreate  ? 'true' : 'false' ?>,
    canEdit:        <?= $canEdit    ? 'true' : 'false' ?>,
    canDelete:      <?= $canDelete  ? 'true' : 'false' ?>,
    isSuperAdmin:   <?= is_super_admin() ? 'true' : 'false' ?>,
    tenantId:       <?= (int)$tenantId ?>,
    statusOptions:  ['pending','processing','shipped','delivered','cancelled','refunded']
};
</script>
<?php if ($isFragment): ?>
<script src="/toro/admin/assets/js/admin_core.js?v=<?= time() ?>"></script>
<script src="/toro/admin/assets/js/pages/orders.js?v=<?= time() ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.OrdersModule && typeof window.OrdersModule.init === 'function') {
        window.OrdersModule.init();
    }
});
</script>
<?php else: ?>
<script src="/toro/admin/assets/js/pages/orders.js?v=<?= time() ?>"></script>
<script>
(function () {
    function tryInit() {
        if (window.OrdersModule && typeof window.OrdersModule.init === 'function') {
            window.OrdersModule.init();
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', tryInit);
    } else {
        tryInit();
    }
})();
</script>
<?php endif; ?>
<?php if (!$isFragment): require_once __DIR__ . '/../includes/footer.php'; endif; ?>
