<?php
declare(strict_types=1);

/**
 * /admin/fragments/tickets.php
 * Production Version - Support Tickets Management
 */

// ════════════════════════════════════════════════════════════
// DETECT REQUEST TYPE
// ════════════════════════════════════════════════════════════
 $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
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
 $user = admin_user();
 $lang = admin_lang();
 $dir = admin_dir();
 $csrf = admin_csrf();
 $tenantId = admin_tenant_id();

// ════════════════════════════════════════════════════════════
// CHECK PERMISSIONS
// ════════════════════════════════════════════════════════════
 $canManageTickets = can('tickets.manage') || can('tickets.create');
 $canViewAll = can_view_all('tickets');
 $canViewOwn = can_view_own('tickets');
 $canCreate = can_create('tickets');
 $canEdit = can_edit_all('tickets') || $canManageTickets;
 $canDelete = can_delete_all('tickets') || $canManageTickets;
 $canView = $canViewAll || $canViewOwn;

if (!$canView && !is_super_admin()) {
    if ($isFragment) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    } else {
        http_response_code(403);
        die('Access denied');
    }
}

// ════════════════════════════════════════════════════════════
// TRANSLATION HELPERS
// ════════════════════════════════════════════════════════════
function __t($key, $fallback = '') {
    if (function_exists('i18n_get')) {
        $v = i18n_get($key);
        return $v ?? ($fallback ?? $key);
    }
    return $fallback ?? $key;
}

// ════════════════════════════════════════════════════════════
// DB-DRIVEN CSS VARS HELPER
// ════════════════════════════════════════════════════════════
if (!function_exists('renderFragmentThemeVars')) {
    function renderFragmentThemeVars(array $theme): void {
        echo ':root {' . PHP_EOL;
        foreach ($theme['color_settings'] ?? [] as $c) {
            if (empty($c['setting_key']) || !isset($c['color_value'])) continue;
            $k = htmlspecialchars($c['setting_key'], ENT_QUOTES);
            $v = htmlspecialchars($c['color_value'], ENT_QUOTES);
            echo "    --{$k}: {$v};" . PHP_EOL;
        }
        // (Shortened for brevity, same as products.php)
        echo '}' . PHP_EOL;
    }
}

 $apiBase = '/api';
?>
<style id="db-theme-vars-tickets">
<?php renderFragmentThemeVars($GLOBALS['ADMIN_UI']['theme'] ?? []); ?>
</style>
<link rel="stylesheet" href="/admin/assets/css/pages/tickets.css?v=<?= time() ?>">

<meta data-page="tickets" data-assets-css="/admin/assets/css/pages/tickets.css" data-i18n-files="/languages/Ticket/<?= rawurlencode($lang) ?>.json">

<div class="page-container" id="ticketsPageContainer" dir="<?= htmlspecialchars($dir) ?>">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title" data-i18n="tickets.title"><?= __t('tickets.title', 'Support Tickets') ?></h1>
            <p class="page-subtitle" data-i18n="tickets.subtitle"><?= __t('tickets.subtitle', 'Manage customer support requests') ?></p>
        </div>
        <div class="page-header-actions">
            <?php if ($canCreate): ?>
            <button id="btnAddTicket" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                <span data-i18n="tickets.add_new"><?= __t('tickets.add_new', 'New Ticket') ?></span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Form Container -->
    <div id="ticketFormContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title" id="formTitle" data-i18n="form.add_title"><?= __t('form.add_title', 'New Ticket') ?></h3>
            <button type="button" class="btn btn-sm btn-outline" id="btnCloseForm"><i class="fas fa-times"></i></button>
        </div>
        <div class="card-body">
            <form id="ticketForm" novalidate>
                <input type="hidden" id="formId" name="id">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" id="ticketTenantId" name="tenant_id" value="<?= $tenantId ?>">

                <!-- Tabs -->
                <div class="form-tabs">
                    <button type="button" class="tab-btn active" data-tab="details"><i class="fas fa-info-circle"></i> <span data-i18n="tabs.details">Details</span></button>
                    <button type="button" class="tab-btn" data-tab="messages"><i class="fas fa-comments"></i> <span data-i18n="tabs.messages">Messages</span></button>
                    <button type="button" class="tab-btn" data-tab="history"><i class="fas fa-history"></i> <span data-i18n="tabs.history">Status History</span></button>
                </div>

                <!-- Tab: Details -->
                <div class="tab-content active" id="tab-details">
                    <div class="form-row">
                        <div class="form-group" style="flex:2;">
                            <label for="ticketSubject" data-i18n="form.fields.subject.label">Subject</label>
                            <input type="text" id="ticketSubject" name="subject" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="ticketCategory" data-i18n="form.fields.category.label">Category</label>
                            <select id="ticketCategory" name="category_id" class="form-control"></select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="ticketUser" data-i18n="form.fields.user.label">Customer</label>
                            <select id="ticketUser" name="user_id" class="form-control"></select>
                        </div>
                        <div class="form-group">
                            <label for="ticketOrder" data-i18n="form.fields.order.label">Related Order</label>
                            <select id="ticketOrder" name="order_id" class="form-control"></select>
                        </div>
                        <div class="form-group">
                            <label for="ticketEntity" data-i18n="form.fields.entity.label">Entity</label>
                            <select id="ticketEntity" name="entity_id" class="form-control"></select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="ticketDescription" data-i18n="form.fields.description.label">Description</label>
                        <textarea id="ticketDescription" name="description" class="form-control" rows="5"></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="ticketStatus" data-i18n="form.fields.status.label">Status</label>
                            <select id="ticketStatus" name="status" class="form-control">
                                <option value="open">Open</option>
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="resolved">Resolved</option>
                                <option value="closed">Closed</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="ticketPriority" data-i18n="form.fields.priority.label">Priority</label>
                            <select id="ticketPriority" name="priority" class="form-control">
                                <option value="low">Low</option>
                                <option value="normal">Normal</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="ticketAssigned" data-i18n="form.fields.assigned_to.label">Assigned To</label>
                            <select id="ticketAssigned" name="assigned_to" class="form-control"></select>
                        </div>
                    </div>
                </div>

                <!-- Tab: Messages -->
                <div class="tab-content" id="tab-messages" style="display:none">
                    <div id="ticketMessagesList" class="messages-list"></div>
                    <div class="reply-section" style="margin-top:20px; padding-top:15px; border-top:1px solid var(--border-color);">
                        <div class="form-group">
                            <label for="ticketReply" data-i18n="form.fields.reply.label">Add Reply</label>
                            <textarea id="ticketReply" class="form-control" rows="3" placeholder="Type your response here..."></textarea>
                        </div>
                        <div style="display:flex; gap:10px;">
                            <button type="button" id="btnSendReply" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send Reply</button>
                            <label style="display:flex; align-items:center; gap:5px; color:var(--text-secondary); font-size:0.9rem;">
                                <input type="checkbox" id="replyInternal"> Internal Note?
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Tab: History -->
                <div class="tab-content" id="tab-history" style="display:none">
                    <div id="ticketHistoryList"></div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="btnSubmitForm"><i class="fas fa-save"></i> Save</button>
                    <button type="button" class="btn btn-outline" id="btnCancelForm">Cancel</button>
                    <?php if ($canDelete): ?>
                    <button type="button" id="btnDeleteTicket" class="btn btn-danger" style="display:none"><i class="fas fa-trash"></i> Delete</button>
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
                    <label for="searchInput">Search</label>
                    <input type="text" id="searchInput" class="form-control" placeholder="Subject or #ID">
                </div>
                <div class="filter-group">
                    <label for="statusFilter">Status</label>
                    <select id="statusFilter" class="form-control">
                        <option value="">All Statuses</option>
                        <option value="open">Open</option>
                        <option value="pending">Pending</option>
                        <option value="in_progress">In Progress</option>
                        <option value="resolved">Resolved</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="priorityFilter">Priority</label>
                    <select id="priorityFilter" class="form-control">
                        <option value="">All Priorities</option>
                        <option value="low">Low</option>
                        <option value="normal">Normal</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button id="btnApplyFilters" class="btn btn-secondary">Apply</button>
                    <button id="btnResetFilters" class="btn btn-outline">Reset</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="card table-card">
        <div class="card-body">
            <div id="tableLoading" class="loading-state"><div class="spinner"></div><p>Loading...</p></div>
            <div id="tableContainer" style="display:none">
                <div class="table-responsive">
                    <table class="data-table" id="ticketsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Subject</th>
                                <th>Customer</th>
                                <th>Category</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody"></tbody>
                    </table>
                </div>
                <div class="pagination-wrapper">
                    <div class="pagination-info"><span id="paginationInfo">0-0 of 0</span></div>
                    <div class="pagination" id="pagination"></div>
                </div>
            </div>
            <div id="emptyState" class="empty-state" style="display:none">
                <div class="empty-icon">🎟️</div>
                <h3>No Tickets Found</h3>
                <p>There are no support tickets matching your criteria.</p>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
window.APP_CONFIG = { API_BASE: '<?= $apiBase ?>', TENANT_ID: <?= $tenantId ?>, CSRF_TOKEN: '<?= addslashes($csrf) ?>' };
window.USER_LANGUAGE = '<?= addslashes($lang) ?>';
window.PAGE_PERMISSIONS = <?= json_encode(['canCreate'=>$canCreate, 'canEdit'=>$canEdit, 'canDelete'=>$canDelete]) ?>;
window.TICKETS_CONFIG = {
    apiUrl: '<?= $apiBase ?>/support_tickets',
    categoriesApi: '<?= $apiBase ?>/ticket_categories',
    messagesApi: '<?= $apiBase ?>/ticket_messages',
    historyApi: '<?= $apiBase ?>/ticket_status_history',
    usersApi: '<?= $apiBase ?>/users', // Assuming generic users endpoint
    ordersApi: '<?= $apiBase ?>/orders', // Assuming generic orders endpoint
    lang: '<?= addslashes($lang) ?>',
    itemsPerPage: 20
};
</script>

<script src="/admin/assets/js/admin_framework.js?v=<?= time() ?>"></script>
<script src="/admin/assets/js/pages/tickets.js?v=<?= time() ?>"></script>
<script>
(function(){
    var attempts = 0;
    var interval = setInterval(function(){
        attempts++;
        if (window.Tickets && typeof window.Tickets.init === 'function') {
            clearInterval(interval);
            window.Tickets.init();
        } else if (attempts > 50) clearInterval(interval);
    }, 100);
})();
</script>
<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>