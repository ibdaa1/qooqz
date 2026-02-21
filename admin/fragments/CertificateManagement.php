<?php
declare(strict_types=1);
/**
 * /admin/fragments/CertificateManagement.php
 * Audit workflow management: view requests, conduct audits, manage payments,
 * view issued certificates, and review logs.
 */

$isAjax     = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$isEmbedded = isset($_GET['embedded']) || isset($_POST['embedded']);
$isFragment = $isAjax || $isEmbedded;

if ($isFragment) { require_once __DIR__ . '/../includes/admin_context.php'; }
else             { require_once __DIR__ . '/../includes/header.php'; }

if (!is_admin_logged_in()) {
    if ($isFragment) { http_response_code(401); echo json_encode(['error'=>'Not authenticated']); exit; }
    header('Location: /admin/login.php'); exit;
}

$user      = admin_user();
$lang      = admin_lang();
$dir       = admin_dir();
$csrf      = admin_csrf();
$tenantId  = admin_tenant_id();

/* Permissions */
$canAudit     = can('certificates_audits.manage') || can_edit_all('certificates_audits') || is_super_admin();
$canVerifyPay = can('certificates_payments.manage') || can_edit_all('certificates_payments') || is_super_admin();
$canIssue     = can('certificates_issued.manage') || can_edit_all('certificates_issued') || is_super_admin();
$canViewReq   = can_view_all('certificates_requests') || can_view_own('certificates_requests') || can_view_tenant('certificates_requests') || is_super_admin();

if (!$canViewReq && !is_super_admin()) {
    if ($isFragment) { http_response_code(403); echo json_encode(['error'=>'Access denied']); exit; }
    die('Access denied');
}

function __cm_t($key, $fallback = '') {
    if (function_exists('i18n_get')) { $v = i18n_get($key); return $v ?? ($fallback ?? $key); }
    return $fallback ?? $key;
}
?>
<?php if ($isFragment): ?>
<link rel="stylesheet" href="/admin/assets/css/pages/CertificateManagement.css?v=<?= time() ?>">
<?php endif; ?>

<div class="cm-page" id="certMgmtPage" dir="<?= htmlspecialchars($dir) ?>">

    <!-- ══ PAGE HEADER ══════════════════════════════════════════════════ -->
    <div class="cm-header">
        <div class="cm-header-left">
            <div class="cm-header-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <div>
                <h1 class="cm-title" data-i18n="page.title">Certificate Management</h1>
                <p class="cm-subtitle" data-i18n="page.subtitle">Audit, payment verification & issuance workflow</p>
            </div>
        </div>
        <div class="cm-header-right">
            <div class="cm-stats" id="cmStats">
                <div class="cm-stat-item" id="statPending">
                    <span class="cm-stat-num" id="statPendingNum">—</span>
                    <span class="cm-stat-lbl" data-i18n="stats.pending">Pending Audit</span>
                </div>
                <div class="cm-stat-item" id="statPayment">
                    <span class="cm-stat-num" id="statPaymentNum">—</span>
                    <span class="cm-stat-lbl" data-i18n="stats.payment">Awaiting Payment</span>
                </div>
                <div class="cm-stat-item" id="statApproved">
                    <span class="cm-stat-num" id="statApprovedNum">—</span>
                    <span class="cm-stat-lbl" data-i18n="stats.approved">Approved</span>
                </div>
                <div class="cm-stat-item" id="statIssued">
                    <span class="cm-stat-num" id="statIssuedNum">—</span>
                    <span class="cm-stat-lbl" data-i18n="stats.issued">Issued</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ══ TABS ═══════════════════════════════════════════════════════════ -->
    <div class="cm-tabs">
        <button class="cm-tab active" data-tab="requests" id="tabRequests">
            <i class="fas fa-file-alt"></i>
            <span data-i18n="tabs.requests">Requests</span>
            <span class="cm-tab-badge" id="badgeRequests"></span>
        </button>
        <?php if ($canAudit): ?>
        <button class="cm-tab" data-tab="audits" id="tabAudits">
            <i class="fas fa-search"></i>
            <span data-i18n="tabs.audits">Audits</span>
            <span class="cm-tab-badge" id="badgeAudits"></span>
        </button>
        <?php endif; ?>
        <?php if ($canVerifyPay): ?>
        <button class="cm-tab" data-tab="payments" id="tabPayments">
            <i class="fas fa-credit-card"></i>
            <span data-i18n="tabs.payments">Payments</span>
            <span class="cm-tab-badge" id="badgePayments"></span>
        </button>
        <?php endif; ?>
        <?php if ($canIssue): ?>
        <button class="cm-tab" data-tab="issued" id="tabIssued">
            <i class="fas fa-stamp"></i>
            <span data-i18n="tabs.issued">Issued</span>
        </button>
        <?php endif; ?>
        <button class="cm-tab" data-tab="logs" id="tabLogs">
            <i class="fas fa-history"></i>
            <span data-i18n="tabs.logs">Logs</span>
        </button>
    </div>

    <!-- ══ TAB: REQUESTS ════════════════════════════════════════════════ -->
    <div class="cm-tab-panel active" id="panelRequests">
        <!-- Filters -->
        <div class="cm-card cm-filters-card">
            <div class="cm-filters-grid">
                <div class="cm-filter-group">
                    <label data-i18n="filters.search">Search</label>
                    <input type="text" id="reqSearch" class="form-control"
                           data-i18n-placeholder="filters.search_placeholder" placeholder="Importer name...">
                </div>
                <?php if (is_super_admin()): ?>
                <div class="cm-filter-group">
                    <label data-i18n="filters.tenant_id">Tenant ID</label>
                    <input type="number" id="reqTenantFilter" class="form-control" value="<?= (int)$tenantId ?>">
                </div>
                <?php endif; ?>
                <div class="cm-filter-group">
                    <label data-i18n="filters.entity">Entity</label>
                    <select id="reqEntityFilter" class="form-control">
                        <option value="" data-i18n="filters.all_entities">All Entities</option>
                    </select>
                </div>
                <div class="cm-filter-group">
                    <label data-i18n="filters.status">Status</label>
                    <select id="reqStatusFilter" class="form-control">
                        <option value="" data-i18n="filters.all">All</option>
                        <option value="draft" data-i18n="status.draft">Draft</option>
                        <option value="under_review" data-i18n="status.under_review">Under Review</option>
                        <option value="payment_pending" data-i18n="status.payment_pending">Payment Pending</option>
                        <option value="approved" data-i18n="status.approved">Approved</option>
                        <option value="rejected" data-i18n="status.rejected">Rejected</option>
                        <option value="issued" data-i18n="status.issued">Issued</option>
                    </select>
                </div>
                <div class="cm-filter-group">
                    <label data-i18n="filters.auditor">Auditor</label>
                    <select id="reqAuditorFilter" class="form-control">
                        <option value="" data-i18n="filters.all">All</option>
                    </select>
                </div>
                <div class="cm-filter-group cm-filter-actions">
                    <button id="btnReqFilter" class="btn btn-secondary" data-i18n="filters.apply">Apply</button>
                    <button id="btnReqReset"  class="btn btn-outline"   data-i18n="filters.reset">Reset</button>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="cm-card">
            <div id="reqLoading" class="cm-loading"><div class="cm-spinner"></div><p data-i18n="common.loading">Loading...</p></div>
            <div id="reqTable" style="display:none">
                <div class="table-responsive">
                    <table class="cm-data-table" id="requestsTable">
                        <thead>
                            <tr>
                                <th data-i18n="req_table.id">ID</th>
                                <?php if (is_super_admin()): ?><th data-i18n="req_table.tenant">Tenant</th><?php endif; ?>
                                <th data-i18n="req_table.entity">Entity</th>
                                <th data-i18n="req_table.certificate">Certificate</th>
                                <th data-i18n="req_table.importer">Importer</th>
                                <th data-i18n="req_table.items">Items</th>
                                <th data-i18n="req_table.status">Status</th>
                                <th data-i18n="req_table.payment">Payment</th>
                                <th data-i18n="req_table.auditor">Auditor</th>
                                <th data-i18n="req_table.created">Created</th>
                                <th data-i18n="req_table.actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="reqTableBody"></tbody>
                    </table>
                </div>
                <div class="cm-pagination-wrap">
                    <div class="cm-pagination-info"><span id="reqPaginationInfo"></span></div>
                    <div id="reqPagination" class="pagination"></div>
                </div>
            </div>
            <div id="reqEmpty" class="cm-empty-state" style="display:none">
                <div class="cm-empty-icon">📋</div>
                <h3 data-i18n="req_table.empty_title">No Requests Found</h3>
                <p data-i18n="req_table.empty_msg">Adjust filters to find requests</p>
            </div>
        </div>
    </div>

    <!-- ══ TAB: AUDITS ══════════════════════════════════════════════════ -->
    <?php if ($canAudit): ?>
    <div class="cm-tab-panel" id="panelAudits" style="display:none">
        <!-- Audit filters -->
        <div class="cm-card cm-filters-card">
            <div class="cm-filters-grid">
                <div class="cm-filter-group">
                    <label data-i18n="filters.request_id">Request ID</label>
                    <input type="number" id="auditReqIdFilter" class="form-control" placeholder="e.g. 42">
                </div>
                <div class="cm-filter-group">
                    <label data-i18n="filters.audit_status">Audit Status</label>
                    <select id="auditStatusFilter" class="form-control">
                        <option value="" data-i18n="filters.all">All</option>
                        <option value="pending"  data-i18n="audit.status.pending">Pending</option>
                        <option value="approved" data-i18n="audit.status.approved">Approved</option>
                        <option value="rejected" data-i18n="audit.status.rejected">Rejected</option>
                    </select>
                </div>
                <div class="cm-filter-group cm-filter-actions">
                    <button id="btnAuditFilter" class="btn btn-secondary" data-i18n="filters.apply">Apply</button>
                    <button id="btnAuditReset"  class="btn btn-outline"   data-i18n="filters.reset">Reset</button>
                </div>
            </div>
        </div>

        <div class="cm-card">
            <div id="auditLoading" class="cm-loading"><div class="cm-spinner"></div><p data-i18n="common.loading">Loading...</p></div>
            <div id="auditTable" style="display:none">
                <div class="table-responsive">
                    <table class="cm-data-table" id="auditsTable">
                        <thead>
                            <tr>
                                <th data-i18n="audit_table.id">ID</th>
                                <th data-i18n="audit_table.request_id">Request</th>
                                <th data-i18n="audit_table.entity">Entity</th>
                                <th data-i18n="audit_table.auditor">Auditor</th>
                                <th data-i18n="audit_table.status">Status</th>
                                <th data-i18n="audit_table.audit_date">Audit Date</th>
                                <th data-i18n="audit_table.notes">Notes</th>
                                <th data-i18n="audit_table.actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="auditTableBody"></tbody>
                    </table>
                </div>
                <div class="cm-pagination-wrap">
                    <div class="cm-pagination-info"><span id="auditPaginationInfo"></span></div>
                    <div id="auditPagination" class="pagination"></div>
                </div>
            </div>
            <div id="auditEmpty" class="cm-empty-state" style="display:none">
                <div class="cm-empty-icon">🔍</div>
                <h3 data-i18n="audit_table.empty_title">No Audits Found</h3>
                <p data-i18n="audit_table.empty_msg">No audit records match the filter</p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ══ TAB: PAYMENTS ════════════════════════════════════════════════ -->
    <?php if ($canVerifyPay): ?>
    <div class="cm-tab-panel" id="panelPayments" style="display:none">
        <div class="cm-card cm-filters-card">
            <div class="cm-filters-grid">
                <div class="cm-filter-group">
                    <label data-i18n="filters.request_id">Request ID</label>
                    <input type="number" id="payReqIdFilter" class="form-control" placeholder="e.g. 42">
                </div>
                <div class="cm-filter-group">
                    <label data-i18n="filters.pay_status">Payment Status</label>
                    <select id="payStatusFilter" class="form-control">
                        <option value="" data-i18n="filters.all">All</option>
                        <option value="waiting_verification" data-i18n="pay.status.waiting">Waiting Verification</option>
                        <option value="verified"             data-i18n="pay.status.verified">Verified</option>
                        <option value="rejected"             data-i18n="pay.status.rejected">Rejected</option>
                    </select>
                </div>
                <div class="cm-filter-group cm-filter-actions">
                    <button id="btnPayFilter" class="btn btn-secondary" data-i18n="filters.apply">Apply</button>
                    <button id="btnPayReset"  class="btn btn-outline"   data-i18n="filters.reset">Reset</button>
                </div>
            </div>
        </div>

        <div class="cm-card">
            <div id="payLoading" class="cm-loading"><div class="cm-spinner"></div><p data-i18n="common.loading">Loading...</p></div>
            <div id="payTable" style="display:none">
                <div class="table-responsive">
                    <table class="cm-data-table">
                        <thead>
                            <tr>
                                <th data-i18n="pay_table.id">ID</th>
                                <th data-i18n="pay_table.request_id">Request</th>
                                <th data-i18n="pay_table.type">Type</th>
                                <th data-i18n="pay_table.amount">Amount</th>
                                <th data-i18n="pay_table.reference">Reference</th>
                                <th data-i18n="pay_table.date">Date</th>
                                <th data-i18n="pay_table.receipt">Receipt</th>
                                <th data-i18n="pay_table.status">Status</th>
                                <th data-i18n="pay_table.actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="payTableBody"></tbody>
                    </table>
                </div>
                <div class="cm-pagination-wrap">
                    <div class="cm-pagination-info"><span id="payPaginationInfo"></span></div>
                    <div id="payPagination" class="pagination"></div>
                </div>
            </div>
            <div id="payEmpty" class="cm-empty-state" style="display:none">
                <div class="cm-empty-icon">💳</div>
                <h3 data-i18n="pay_table.empty_title">No Payments Found</h3>
                <p data-i18n="pay_table.empty_msg">No payment records match the filter</p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ══ TAB: ISSUED ══════════════════════════════════════════════════ -->
    <?php if ($canIssue): ?>
    <div class="cm-tab-panel" id="panelIssued" style="display:none">
        <div class="cm-card cm-filters-card">
            <div class="cm-filters-grid">
                <div class="cm-filter-group">
                    <label data-i18n="filters.cert_number">Certificate Number</label>
                    <input type="text" id="issuedSearchFilter" class="form-control" placeholder="e.g. CERT-2025-001">
                </div>
                <div class="cm-filter-group">
                    <label data-i18n="filters.is_cancelled">Cancelled</label>
                    <select id="issuedCancelledFilter" class="form-control">
                        <option value=""  data-i18n="filters.all">All</option>
                        <option value="0" data-i18n="filters.active">Active</option>
                        <option value="1" data-i18n="filters.cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="cm-filter-group cm-filter-actions">
                    <button id="btnIssuedFilter" class="btn btn-secondary" data-i18n="filters.apply">Apply</button>
                    <button id="btnIssuedReset"  class="btn btn-outline"   data-i18n="filters.reset">Reset</button>
                </div>
            </div>
        </div>

        <div class="cm-card">
            <div id="issuedLoading" class="cm-loading"><div class="cm-spinner"></div><p data-i18n="common.loading">Loading...</p></div>
            <div id="issuedTable" style="display:none">
                <div class="table-responsive">
                    <table class="cm-data-table">
                        <thead>
                            <tr>
                                <th data-i18n="iss_table.id">ID</th>
                                <th data-i18n="iss_table.cert_number">Cert Number</th>
                                <th data-i18n="iss_table.version">Version</th>
                                <th data-i18n="iss_table.issued_at">Issued At</th>
                                <th data-i18n="iss_table.printable_until">Valid Until</th>
                                <th data-i18n="iss_table.issued_by">Issued By</th>
                                <th data-i18n="iss_table.language">Language</th>
                                <th data-i18n="iss_table.status">Status</th>
                                <th data-i18n="iss_table.actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="issuedTableBody"></tbody>
                    </table>
                </div>
                <div class="cm-pagination-wrap">
                    <div class="cm-pagination-info"><span id="issuedPaginationInfo"></span></div>
                    <div id="issuedPagination" class="pagination"></div>
                </div>
            </div>
            <div id="issuedEmpty" class="cm-empty-state" style="display:none">
                <div class="cm-empty-icon">🏅</div>
                <h3 data-i18n="iss_table.empty_title">No Issued Certificates</h3>
                <p data-i18n="iss_table.empty_msg">No certificates have been issued yet</p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ══ TAB: LOGS ════════════════════════════════════════════════════ -->
    <div class="cm-tab-panel" id="panelLogs" style="display:none">
        <div class="cm-card cm-filters-card">
            <div class="cm-filters-grid">
                <div class="cm-filter-group">
                    <label data-i18n="filters.request_id">Request ID</label>
                    <input type="number" id="logReqIdFilter" class="form-control" placeholder="e.g. 42">
                </div>
                <div class="cm-filter-group">
                    <label data-i18n="filters.action_type">Action Type</label>
                    <select id="logActionFilter" class="form-control">
                        <option value="" data-i18n="filters.all">All</option>
                        <option value="create">create</option>
                        <option value="update">update</option>
                        <option value="approve">approve</option>
                        <option value="audit">audit</option>
                        <option value="payment_sent">payment_sent</option>
                        <option value="issue">issue</option>
                        <option value="reject">reject</option>
                    </select>
                </div>
                <div class="cm-filter-group cm-filter-actions">
                    <button id="btnLogFilter" class="btn btn-secondary" data-i18n="filters.apply">Apply</button>
                    <button id="btnLogReset"  class="btn btn-outline"   data-i18n="filters.reset">Reset</button>
                </div>
            </div>
        </div>

        <div class="cm-card">
            <div id="logLoading" class="cm-loading"><div class="cm-spinner"></div><p data-i18n="common.loading">Loading...</p></div>
            <div id="logTable" style="display:none">
                <div class="table-responsive">
                    <table class="cm-data-table">
                        <thead>
                            <tr>
                                <th data-i18n="log_table.id">ID</th>
                                <th data-i18n="log_table.request_id">Request</th>
                                <th data-i18n="log_table.user">User</th>
                                <th data-i18n="log_table.action">Action</th>
                                <th data-i18n="log_table.notes">Notes</th>
                                <th data-i18n="log_table.created">Date</th>
                            </tr>
                        </thead>
                        <tbody id="logTableBody"></tbody>
                    </table>
                </div>
                <div class="cm-pagination-wrap">
                    <div class="cm-pagination-info"><span id="logPaginationInfo"></span></div>
                    <div id="logPagination" class="pagination"></div>
                </div>
            </div>
            <div id="logEmpty" class="cm-empty-state" style="display:none">
                <div class="cm-empty-icon">📜</div>
                <h3 data-i18n="log_table.empty_title">No Logs Found</h3>
                <p data-i18n="log_table.empty_msg">No log records match the filter</p>
            </div>
        </div>
    </div>

</div><!-- /cm-page -->

<!-- ══ MODAL: AUDIT ACTION ══════════════════════════════════════════════ -->
<div id="modalAudit" class="cm-modal-backdrop" style="display:none">
    <div class="cm-modal">
        <div class="cm-modal-header">
            <h3 class="cm-modal-title" id="modalAuditTitle" data-i18n="audit.modal.title">Conduct Audit</h3>
            <button class="cm-modal-close" id="btnCloseAuditModal"><i class="fas fa-times"></i></button>
        </div>
        <div class="cm-modal-body">
            <!-- Request Summary -->
            <div class="cm-request-summary" id="auditRequestSummary"></div>

            <!-- Request Items -->
            <div class="cm-section-label" data-i18n="audit.modal.items">Request Items</div>
            <div id="auditItemsList" class="cm-items-readonly"></div>

            <!-- Audit Form -->
            <div class="cm-section-label" data-i18n="audit.modal.decision">Audit Decision</div>
            <form id="auditForm" novalidate>
                <input type="hidden" id="auditFormRequestId">
                <input type="hidden" id="auditFormId">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label data-i18n="audit.form.status">Decision <span class="req">*</span></label>
                        <select id="auditFormStatus" name="status" class="form-control" required>
                            <option value="approved" data-i18n="audit.status.approved">Approve</option>
                            <option value="rejected" data-i18n="audit.status.rejected">Reject</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label data-i18n="audit.form.audit_date">Audit Date <span class="req">*</span></label>
                        <input type="datetime-local" id="auditFormDate" name="audit_date" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label data-i18n="audit.form.notes">Notes</label>
                    <textarea id="auditFormNotes" name="notes" class="form-control" rows="3"></textarea>
                </div>

                <!-- Assign Auditor (for request assignment) -->
                <div class="form-group" id="auditAssignRow">
                    <label data-i18n="audit.form.assign_auditor">Assign Auditor</label>
                    <select id="auditAssignUser" name="auditor_user_id" class="form-control">
                        <option value="" data-i18n="audit.form.no_change">— No Change —</option>
                    </select>
                </div>
            </form>
        </div>
        <div class="cm-modal-footer">
            <button class="btn btn-outline" id="btnCancelAudit" data-i18n="common.cancel">Cancel</button>
            <button class="btn btn-primary" id="btnSubmitAudit">
                <i class="fas fa-check"></i>
                <span data-i18n="audit.form.submit">Submit Audit</span>
            </button>
        </div>
    </div>
</div>

<!-- ══ MODAL: PAYMENT VERIFY ════════════════════════════════════════════ -->
<div id="modalPayment" class="cm-modal-backdrop" style="display:none">
    <div class="cm-modal">
        <div class="cm-modal-header">
            <h3 class="cm-modal-title" data-i18n="pay.modal.title">Verify Payment</h3>
            <button class="cm-modal-close" id="btnClosePayModal"><i class="fas fa-times"></i></button>
        </div>
        <div class="cm-modal-body">
            <div id="paymentDetails" class="cm-payment-details"></div>
            <form id="paymentForm" novalidate>
                <input type="hidden" id="payFormId">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <div class="form-group">
                    <label data-i18n="pay.form.status">Verification Decision <span class="req">*</span></label>
                    <select id="payFormStatus" name="verification_status" class="form-control" required>
                        <option value="verified"  data-i18n="pay.status.verified">Verify (Accept)</option>
                        <option value="rejected"  data-i18n="pay.status.rejected">Reject</option>
                    </select>
                </div>
                <div class="form-group">
                    <label data-i18n="pay.form.notes">Notes</label>
                    <textarea id="payFormNotes" name="notes" class="form-control" rows="2"></textarea>
                </div>
            </form>
        </div>
        <div class="cm-modal-footer">
            <button class="btn btn-outline" id="btnCancelPay" data-i18n="common.cancel">Cancel</button>
            <button class="btn btn-primary" id="btnSubmitPay">
                <i class="fas fa-check-circle"></i>
                <span data-i18n="pay.form.submit">Confirm</span>
            </button>
        </div>
    </div>
</div>

<!-- ══ MODAL: REQUEST DETAIL ════════════════════════════════════════════ -->
<div id="modalDetail" class="cm-modal-backdrop" style="display:none">
    <div class="cm-modal cm-modal-lg">
        <div class="cm-modal-header">
            <h3 class="cm-modal-title" id="detailModalTitle" data-i18n="detail.title">Request Details</h3>
            <button class="cm-modal-close" id="btnCloseDetail"><i class="fas fa-times"></i></button>
        </div>
        <div class="cm-modal-body" id="detailModalBody">
            <div class="cm-loading"><div class="cm-spinner"></div></div>
        </div>
        <div class="cm-modal-footer">
            <button class="btn btn-outline" id="btnCloseDetailFooter" data-i18n="common.close">Close</button>
            <?php if ($canAudit): ?>
            <button class="btn btn-warning" id="btnAuditFromDetail" style="display:none">
                <i class="fas fa-search"></i>
                <span data-i18n="detail.actions.audit">Audit This Request</span>
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ══ TOAST ════════════════════════════════════════════════════════════ -->
<div id="cmToast" class="cm-toast" style="display:none"></div>

<script>
window.APP_CONFIG      = window.APP_CONFIG || {};
window.APP_CONFIG.TENANT_ID = <?= (int)$tenantId ?>;
window.USER_LANGUAGE        = '<?= addslashes($lang) ?>';

window.CERT_MGMT_CFG = {
    apiRequests     : '/api/certificates_requests',
    apiAudits       : '/api/certificates_audits',
    apiPayments     : '/api/certificates_payments',
    apiIssued       : '/api/certificates_issued',
    apiLogs         : '/api/certificates_logs',
    apiItems        : '/api/certificates_request_items',
    apiCertificates : '/api/certificates',
    apiEditions     : '/api/certificate_editions',
    apiEntities     : '/api/entities',
    apiProducts     : '/api/certificates_products',
    apiProductsTrans: '/api/certificates_products_translations',
    apiVersions     : '/api/certificates_versions',
    apiTenantUsers  : '/api/tenant_users',
    apiLanguages    : '/api/languages',
    apiTenants      : '/api/tenants',
    csrfToken       : '<?= addslashes($csrf) ?>',
    lang            : '<?= addslashes($lang) ?>',
    perPage         : 25,
    perms           : <?= json_encode([
        'canAudit'     => $canAudit,
        'canVerifyPay' => $canVerifyPay,
        'canIssue'     => $canIssue,
        'isSuperAdmin' => is_super_admin(),
    ], JSON_UNESCAPED_UNICODE) ?>
};
</script>

<?php if ($isFragment): ?>
<script src="/admin/assets/js/admin_framework.js?v=<?= time() ?>"></script>
<script src="/admin/assets/js/pages/CertificateManagement.js?v=<?= time() ?>"></script>
<script>
(function(){
    let t=0;
    const iv=setInterval(()=>{
        if(window.AdminFramework&&window.CertificateManagement?.init){
            clearInterval(iv); window.CertificateManagement.init().catch(console.error);
        }else if(++t>80){clearInterval(iv);console.error('CertificateManagement timeout');}
    },100);
})();
</script>
<?php else: ?>
<script src="/admin/assets/js/pages/CertificateManagement.js?v=<?= time() ?>"></script>
<?php endif; ?>

<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>
