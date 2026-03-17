<?php
declare(strict_types=1);

/**
 * /admin/fragments/escrow.php
 * Escrow Management – Admin Fragment
 */

// ════════════════════════════════════════════════════════════
// DETECT REQUEST TYPE
// ════════════════════════════════════════════════════════════
$isAjax     = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$isEmbedded = isset($_GET['embedded']) || isset($_POST['embedded']);
$isFragment = $isAjax || $isEmbedded;

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
        header('Location: /admin/login.php');
        exit;
    }
}

// ════════════════════════════════════════════════════════════
// GET USER CONTEXT & PERMISSIONS
// ════════════════════════════════════════════════════════════
$user     = admin_user();
$lang     = admin_lang();
$dir      = admin_dir();
$csrf     = admin_csrf();
$tenantId = admin_tenant_id();

$canCreate = can('escrow.manage') || can('escrow.create') || is_super_admin();
$canEdit   = can('escrow.manage') || can('escrow.edit')   || is_super_admin();
$canDelete = can('escrow.manage') || can('escrow.delete') || is_super_admin();
$canView   = $canCreate || $canEdit || $canDelete || can('escrow.view') || is_super_admin();

if (!$canView) {
    if ($isFragment) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }
    http_response_code(403);
    die('Access denied');
}

// ════════════════════════════════════════════════════════════
// DB-DRIVEN CSS VARS HELPER
// ════════════════════════════════════════════════════════════
if (!function_exists('renderEscrowFragmentThemeVars')) {
    function renderEscrowFragmentThemeVars(array $theme): void {
        echo ':root {' . PHP_EOL;
        foreach ($theme['color_settings'] ?? [] as $c) {
            if (empty($c['setting_key']) || !isset($c['color_value'])) continue;
            $k = htmlspecialchars($c['setting_key'], ENT_QUOTES);
            $v = htmlspecialchars($c['color_value'], ENT_QUOTES);
            echo "    --{$k}: {$v};" . PHP_EOL;
        }
        echo '}' . PHP_EOL;
    }
}

$apiBase = '/api';
?>
<style id="db-theme-vars-escrow">
<?php renderEscrowFragmentThemeVars($GLOBALS['ADMIN_UI']['theme'] ?? []); ?>
</style>
<link rel="stylesheet" href="/admin/assets/css/pages/escrow.css?v=<?= time() ?>">

<meta data-page="escrow"
      data-assets-css="/admin/assets/css/pages/escrow.css"
      data-i18n-files="/languages/escrow/<?= rawurlencode($lang) ?>.json">

<div class="page-container" id="escrowPageContainer" dir="<?= htmlspecialchars($dir) ?>">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title" data-i18n="escrow.title">Escrow Transactions</h1>
            <p class="page-subtitle" data-i18n="escrow.subtitle">Manage escrow transactions, disputes and ledger</p>
        </div>
        <?php if ($canCreate): ?>
        <div class="page-header-actions">
            <button id="esc-btnAdd" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                <span data-i18n="escrow.add_new">New Escrow</span>
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Form Container -->
    <div id="esc-formContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title" id="esc-formTitle" data-i18n="form.add_title">New Escrow Transaction</h3>
            <button type="button" class="btn btn-sm btn-outline" id="esc-btnCloseForm">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="card-body">
            <form id="esc-form" novalidate>
                <input type="hidden" id="esc-formId"   name="id">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="tenant_id"  value="<?= $tenantId ?>">

                <!-- Tabs -->
                <div class="form-tabs">
                    <button type="button" class="tab-btn active" data-tab="details">
                        <i class="fas fa-info-circle"></i>
                        <span data-i18n="form.tabs.details">Details</span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="disputes">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span data-i18n="form.tabs.disputes">Disputes</span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="history">
                        <i class="fas fa-history"></i>
                        <span data-i18n="form.tabs.history">Status History</span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="ledger">
                        <i class="fas fa-book"></i>
                        <span data-i18n="form.tabs.ledger">Ledger</span>
                    </button>
                </div>

                <!-- Tab: Details -->
                <div class="tab-content active" id="esc-tab-details" style="display:block">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="esc-escrowNumber" data-i18n="form.fields.escrow_number.label">Escrow Number</label>
                            <input type="text" id="esc-escrowNumber" name="escrow_number" class="form-control"
                                   placeholder="Auto-generated" readonly>
                        </div>
                        <div class="form-group">
                            <label for="esc-status" data-i18n="form.fields.status.label">Status</label>
                            <select id="esc-status" name="status" class="form-control">
                                <option value="pending"    data-i18n="status.pending">Pending</option>
                                <option value="funded"     data-i18n="status.funded">Funded</option>
                                <option value="in_transit" data-i18n="status.in_transit">In Transit</option>
                                <option value="delivered"  data-i18n="status.delivered">Delivered</option>
                                <option value="released"   data-i18n="status.released">Released</option>
                                <option value="disputed"   data-i18n="status.disputed">Disputed</option>
                                <option value="refunded"   data-i18n="status.refunded">Refunded</option>
                                <option value="cancelled"  data-i18n="status.cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="esc-orderId" data-i18n="form.fields.order_id.label">Order ID</label>
                            <input type="number" id="esc-orderId" name="order_id" class="form-control" placeholder="Optional">
                        </div>
                        <div class="form-group">
                            <label for="esc-currencyCode" data-i18n="form.fields.currency_code.label">Currency</label>
                            <input type="text" id="esc-currencyCode" name="currency_code" class="form-control"
                                   placeholder="USD" maxlength="8" value="USD">
                        </div>
                        <div class="form-group">
                            <label for="esc-autoReleaseDays" data-i18n="form.fields.auto_release_days.label">Auto-Release Days</label>
                            <input type="number" id="esc-autoReleaseDays" name="auto_release_days" class="form-control"
                                   min="1" value="7">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="esc-amount" data-i18n="form.fields.amount.label">Amount</label>
                            <input type="number" id="esc-amount" name="amount" class="form-control"
                                   step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="esc-escrowFee" data-i18n="form.fields.escrow_fee.label">Escrow Fee</label>
                            <input type="number" id="esc-escrowFee" name="escrow_fee" class="form-control"
                                   step="0.01" min="0" value="0">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="esc-buyerEntityId" data-i18n="form.fields.buyer_entity_id.label">Buyer Entity ID</label>
                            <input type="number" id="esc-buyerEntityId" name="buyer_entity_id" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="esc-buyerEntityType" data-i18n="form.fields.buyer_entity_type.label">Buyer Entity Type</label>
                            <input type="text" id="esc-buyerEntityType" name="buyer_entity_type" class="form-control"
                                   placeholder="user, vendor…">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="esc-sellerEntityId" data-i18n="form.fields.seller_entity_id.label">Seller Entity ID</label>
                            <input type="number" id="esc-sellerEntityId" name="seller_entity_id" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="esc-sellerEntityType" data-i18n="form.fields.seller_entity_type.label">Seller Entity Type</label>
                            <input type="text" id="esc-sellerEntityType" name="seller_entity_type" class="form-control"
                                   placeholder="user, vendor…">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="esc-notes" data-i18n="form.fields.notes.label">Notes</label>
                        <textarea id="esc-notes" name="notes" class="form-control" rows="3"
                                  data-i18n-placeholder="form.fields.notes.placeholder"
                                  placeholder="Additional notes…"></textarea>
                    </div>
                </div>

                <!-- Tab: Disputes -->
                <div class="tab-content" id="esc-tab-disputes" style="display:none">
                    <div id="esc-disputesList">
                        <p style="color:var(--text-secondary);text-align:center;padding:20px" data-i18n="disputes.empty">
                            No disputes for this escrow
                        </p>
                    </div>
                </div>

                <!-- Tab: Status History -->
                <div class="tab-content" id="esc-tab-history" style="display:none">
                    <div id="esc-historyList">
                        <p style="color:var(--text-secondary);text-align:center;padding:20px" data-i18n="history.empty">
                            No status history yet
                        </p>
                    </div>
                </div>

                <!-- Tab: Ledger -->
                <div class="tab-content" id="esc-tab-ledger" style="display:none">
                    <div id="esc-ledgerList">
                        <p style="color:var(--text-secondary);text-align:center;padding:20px" data-i18n="ledger.empty">
                            No ledger entries yet
                        </p>
                    </div>
                </div>

                <div class="form-actions">
                    <?php if ($canEdit): ?>
                    <button type="submit" class="btn btn-primary" id="esc-btnSave">
                        <i class="fas fa-save"></i>
                        <span data-i18n="form.buttons.save">Save</span>
                    </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-outline" id="esc-btnCancelForm" data-i18n="form.buttons.cancel">
                        Cancel
                    </button>
                    <?php if ($canDelete): ?>
                    <button type="button" id="esc-btnDelete" class="btn btn-danger" style="display:none">
                        <i class="fas fa-trash"></i>
                        <span data-i18n="form.buttons.delete">Delete</span>
                    </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Filters -->
    <div class="card filter-card">
        <div class="card-body">
            <div class="filters-grid">
                <div class="filter-group">
                    <label for="esc-searchInput" data-i18n="filters.search">Search</label>
                    <input type="text" id="esc-searchInput" class="form-control"
                           data-i18n-placeholder="filters.search_placeholder"
                           placeholder="Search by escrow number…">
                </div>
                <div class="filter-group">
                    <label for="esc-statusFilter" data-i18n="filters.status">Status</label>
                    <select id="esc-statusFilter" class="form-control">
                        <option value="" data-i18n="filters.all_statuses">All Statuses</option>
                        <option value="pending"    data-i18n="status.pending">Pending</option>
                        <option value="funded"     data-i18n="status.funded">Funded</option>
                        <option value="in_transit" data-i18n="status.in_transit">In Transit</option>
                        <option value="delivered"  data-i18n="status.delivered">Delivered</option>
                        <option value="released"   data-i18n="status.released">Released</option>
                        <option value="disputed"   data-i18n="status.disputed">Disputed</option>
                        <option value="refunded"   data-i18n="status.refunded">Refunded</option>
                        <option value="cancelled"  data-i18n="status.cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button id="esc-btnApplyFilters" class="btn btn-secondary" data-i18n="filters.apply">Apply</button>
                    <button id="esc-btnResetFilters" class="btn btn-outline"   data-i18n="filters.reset">Reset</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="card table-card">
        <div class="card-body">
            <div id="esc-tableLoading" class="loading-state">
                <div class="spinner"></div>
                <p data-i18n="escrow.loading">Loading escrow transactions…</p>
            </div>
            <div id="esc-tableContainer" style="display:none">
                <div class="table-responsive">
                    <table class="data-table" id="esc-table">
                        <thead>
                            <tr>
                                <th data-i18n="table.headers.id">ID</th>
                                <th data-i18n="table.headers.escrow_number">Escrow #</th>
                                <th data-i18n="table.headers.order_id">Order</th>
                                <th data-i18n="table.headers.amount">Amount</th>
                                <th data-i18n="table.headers.currency">Currency</th>
                                <th data-i18n="table.headers.status">Status</th>
                                <th data-i18n="table.headers.created_at">Created</th>
                                <th data-i18n="table.headers.actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="esc-tableBody"></tbody>
                    </table>
                </div>
                <div class="pagination-wrapper">
                    <div class="pagination-info"><span id="esc-paginationInfo">0-0 / 0</span></div>
                    <div class="pagination" id="esc-pagination"></div>
                </div>
            </div>
            <div id="esc-emptyState" class="empty-state" style="display:none">
                <div class="empty-icon">🔒</div>
                <h3 data-i18n="table.empty.title">No Escrow Transactions Found</h3>
                <p data-i18n="table.empty.message">There are no escrow transactions matching your criteria.</p>
                <?php if ($canCreate): ?>
                <button class="btn btn-primary" id="esc-btnAddFirst"
                        onclick="document.getElementById('esc-btnAdd')?.click()"
                        data-i18n="table.empty.add_first">
                    Create First Escrow
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
window.APP_CONFIG    = { API_BASE: '<?= $apiBase ?>', TENANT_ID: <?= $tenantId ?>, CSRF_TOKEN: '<?= addslashes($csrf) ?>' };
window.USER_LANGUAGE = '<?= addslashes($lang) ?>';
window.ADMIN_LANG    = window.ADMIN_LANG || '<?= addslashes($lang) ?>';
window.PAGE_PERMISSIONS = <?= json_encode(['canCreate' => $canCreate, 'canEdit' => $canEdit, 'canDelete' => $canDelete]) ?>;
window.ESCROW_CONFIG = {
    apiUrl:      '<?= $apiBase ?>/escrow_transactions',
    historyApi:  '<?= $apiBase ?>/escrow_status_history',
    disputesApi: '<?= $apiBase ?>/escrow_disputes',
    ledgerApi:   '<?= $apiBase ?>/escrow_ledger',
    itemsPerPage: 20,
    lang:        '<?= addslashes($lang) ?>',
    tenantId:    <?= $tenantId ?>
};
</script>

<script src="/admin/assets/js/admin_framework.js?v=<?= time() ?>"></script>
<script src="/admin/assets/js/pages/escrow.js?v=<?= time() ?>"></script>
<script>
(function () {
    var initialized = false;
    var poll;

    function cleanup() {
        clearInterval(poll);
        window.removeEventListener('admin:i18n:applied', tryInit);
    }

    function tryInit() {
        if (initialized) return;
        if (!window.TRANSLATIONS) return;
        if (!window.Escrow || typeof window.Escrow.init !== 'function') return;
        initialized = true;
        cleanup();
        window.Escrow.init();
    }

    window.addEventListener('admin:i18n:applied', tryInit);
    tryInit();

    var pollCount = 0;
    poll = setInterval(function () {
        pollCount++;
        tryInit();
        if (initialized || pollCount >= 60) {
            cleanup();
            if (!initialized) {
                console.warn('[Escrow] init timed out');
            }
        }
    }, 100);
})();
</script>
<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>
