<?php
declare(strict_types=1);

/**
 * /toro/admin/fragments/coupons.php
 * Production-ready Coupons management fragment for the admin panel.
 *
 * Full CRUD: list, create, edit, delete coupons with code generation support.
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
$canViewAll    = can_view_all('coupons');
$canViewOwn    = can_view_own('coupons');
$canViewTenant = can_view_tenant('coupons');
$canView       = $canViewAll || $canViewOwn || $canViewTenant || is_super_admin();

$canCreate   = can_create('coupons') || is_super_admin();
$canEditAll  = can_edit_all('coupons');
$canEditOwn  = can_edit_own('coupons');
$canEdit     = $canEditAll || $canEditOwn || is_super_admin();
$canDeleteAll = can_delete_all('coupons');
$canDeleteOwn = can_delete_own('coupons');
$canDelete   = $canDeleteAll || $canDeleteOwn || is_super_admin();

if (!$canView) {
    if ($isFragment) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    } else {
        http_response_code(403);
        die('Access denied: You do not have permission to view coupons.');
    }
}

// ════════════════════════════════════════════════════════════
// API BASE
// ════════════════════════════════════════════════════════════
$apiBase = '/api';

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
<style id="db-theme-vars-coupons">
<?php renderFragmentThemeVars($GLOBALS['ADMIN_UI']['theme'] ?? []); ?>
</style>
<link rel="stylesheet" href="/toro/admin/assets/css/pages/coupons.css?v=<?= time() ?>">
<?php
// ════════════════════════════════════════════════════════════
// HTML OUTPUT
// ════════════════════════════════════════════════════════════
?>
<div id="coupons-page"
     class="page-container"
     dir="<?= htmlspecialchars($dir, ENT_QUOTES) ?>"
     data-page="coupons"
     data-lang="<?= htmlspecialchars($lang, ENT_QUOTES) ?>"
     data-tenant="<?= (int)$tenantId ?>">

    <!-- ── Page Header ────────────────────────────────────── -->
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <div>
            <h1 class="page-title mb-0">Coupons</h1>
            <p class="page-subtitle text-muted mb-0">Manage discount coupons and promotional codes.</p>
        </div>
        <?php if ($canCreate): ?>
        <button type="button" id="btn-add-coupon" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Add Coupon
        </button>
        <?php endif; ?>
    </div>

    <!-- ── Filter Card ────────────────────────────────────── -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-2 align-items-end">

                <!-- Search -->
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-semibold" for="filter-search">Search by Code</label>
                    <input type="text"
                           id="filter-search"
                           class="form-control form-control-sm"
                           placeholder="e.g. SAVE10">
                </div>

                <!-- Type -->
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-semibold" for="filter-type">Type</label>
                    <select id="filter-type" class="form-select form-select-sm">
                        <option value="">All Types</option>
                        <option value="percentage">Percentage</option>
                        <option value="fixed">Fixed Amount</option>
                    </select>
                </div>

                <!-- Status -->
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-semibold" for="filter-status">Status</label>
                    <select id="filter-status" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>

                <!-- Actions -->
                <div class="col-12 col-md-auto d-flex gap-2">
                    <button type="button" id="btn-filter-apply" class="btn btn-primary btn-sm">Apply</button>
                    <button type="button" id="btn-filter-reset" class="btn btn-outline-secondary btn-sm">Reset</button>
                </div>

            </div>
        </div>
    </div>

    <!-- ── Coupons Table ──────────────────────────────────── -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="coupons-table">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width:60px">ID</th>
                            <th>Code</th>
                            <th>Type</th>
                            <th>Discount</th>
                            <th>Min Order</th>
                            <th>Uses</th>
                            <th>Expires</th>
                            <th>Status</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="coupons-tbody">
                        <tr id="coupons-loading-row">
                            <td colspan="9" class="text-center py-5 text-muted">
                                <div class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></div>
                                Loading coupons…
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <!-- Pagination -->
        <div class="card-footer bg-transparent d-flex justify-content-between align-items-center flex-wrap gap-2">
            <small class="text-muted" id="coupons-pagination-info"></small>
            <nav aria-label="Coupons pagination">
                <ul class="pagination pagination-sm mb-0" id="coupons-pagination"></ul>
            </nav>
        </div>
    </div>

</div><!-- /#coupons-page -->

<!-- ══════════════════════════════════════════════════════════
     CREATE / EDIT COUPON MODAL
═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="coupon-modal" tabindex="-1" aria-labelledby="couponModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="coupon-form" novalidate>
                <input type="hidden" id="coupon-id" name="id" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="couponModalLabel">Add Coupon</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">

                        <!-- Code + Generate -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold" for="coupon-code">
                                Coupon Code <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="text"
                                       id="coupon-code"
                                       name="code"
                                       class="form-control text-uppercase"
                                       placeholder="e.g. SAVE10"
                                       autocomplete="off"
                                       required>
                                <button type="button"
                                        id="btn-generate-code"
                                        class="btn btn-outline-secondary"
                                        title="Generate random code">
                                    <i class="bi bi-shuffle me-1"></i>Generate
                                </button>
                            </div>
                            <div class="invalid-feedback">Please enter a coupon code.</div>
                        </div>

                        <!-- Type -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold" for="coupon-type">
                                Type <span class="text-danger">*</span>
                            </label>
                            <select id="coupon-type" name="type" class="form-select" required>
                                <option value="">— Select type —</option>
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed Amount</option>
                            </select>
                            <div class="invalid-feedback">Please select a discount type.</div>
                        </div>

                        <!-- Discount Value -->
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold" for="coupon-discount-value">
                                Discount Value <span class="text-danger">*</span>
                            </label>
                            <input type="number"
                                   id="coupon-discount-value"
                                   name="discount_value"
                                   class="form-control"
                                   min="0"
                                   step="0.01"
                                   placeholder="e.g. 15"
                                   required>
                            <div class="form-text" id="discount-value-hint">Enter amount or percentage.</div>
                            <div class="invalid-feedback">Please enter a discount value.</div>
                        </div>

                        <!-- Min Order Amount -->
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold" for="coupon-min-order">
                                Min Order Amount
                            </label>
                            <input type="number"
                                   id="coupon-min-order"
                                   name="min_order_amount"
                                   class="form-control"
                                   min="0"
                                   step="0.01"
                                   placeholder="0.00">
                            <div class="form-text">Leave blank or 0 for no minimum.</div>
                        </div>

                        <!-- Max Uses -->
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold" for="coupon-max-uses">
                                Max Uses
                            </label>
                            <input type="number"
                                   id="coupon-max-uses"
                                   name="max_uses"
                                   class="form-control"
                                   min="0"
                                   step="1"
                                   placeholder="0">
                            <div class="form-text">0 = unlimited uses.</div>
                        </div>

                        <!-- Starts At -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold" for="coupon-starts-at">Starts At</label>
                            <input type="date"
                                   id="coupon-starts-at"
                                   name="starts_at"
                                   class="form-control">
                        </div>

                        <!-- Expires At -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold" for="coupon-expires-at">Expires At</label>
                            <input type="date"
                                   id="coupon-expires-at"
                                   name="expires_at"
                                   class="form-control">
                        </div>

                        <!-- Is Active -->
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input"
                                       type="checkbox"
                                       id="coupon-is-active"
                                       name="is_active"
                                       value="1"
                                       checked>
                                <label class="form-check-label fw-semibold" for="coupon-is-active">
                                    Active
                                </label>
                            </div>
                        </div>

                    </div><!-- /.row -->

                    <!-- Form error banner -->
                    <div id="coupon-form-error" class="alert alert-danger mt-3 d-none" role="alert"></div>
                </div><!-- /.modal-body -->

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="btn-save-coupon" class="btn btn-primary">
                        <span id="btn-save-coupon-text">Save Coupon</span>
                        <span id="btn-save-coupon-spinner" class="spinner-border spinner-border-sm ms-1 d-none" role="status" aria-hidden="true"></span>
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     CONFIRM DELETE MODAL
═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="coupon-confirm-modal" tabindex="-1" aria-labelledby="couponConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title text-danger" id="couponConfirmModalLabel">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Delete Coupon
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1">Are you sure you want to delete coupon <strong id="confirm-coupon-code"></strong>?</p>
                <p class="text-muted small mb-0">This action cannot be undone.</p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="btn-confirm-delete" class="btn btn-danger" data-coupon-id="">
                    <span id="btn-confirm-delete-text">Delete</span>
                    <span id="btn-confirm-delete-spinner" class="spinner-border spinner-border-sm ms-1 d-none" role="status" aria-hidden="true"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── Toast Container ────────────────────────────────────── -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="coupons-toast-container" style="z-index:1100"></div>

<!-- ════════════════════════════════════════════════════════
     CONFIG + SCRIPTS
═══════════════════════════════════════════════════════════ -->
<script>
window.COUPONS_CONFIG = {
    apiUrl:             '<?= $apiBase ?>/coupons',
    csrfToken:          '<?= addslashes($csrf) ?>',
    lang:               '<?= addslashes($lang) ?>',
    dir:                '<?= addslashes($dir) ?>',
    canCreate:          <?= $canCreate ? 'true' : 'false' ?>,
    canEdit:            <?= $canEdit   ? 'true' : 'false' ?>,
    canDelete:          <?= $canDelete ? 'true' : 'false' ?>,
    isSuperAdmin:       <?= is_super_admin() ? 'true' : 'false' ?>,
    tenantId:           <?= (int)$tenantId ?>,
    includeGenerateBtn: true
};
</script>
<?php if ($isFragment): ?>
<script src="/toro/admin/assets/js/admin_core.js?v=<?= time() ?>"></script>
<script src="/toro/admin/assets/js/pages/coupons.js?v=<?= time() ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.CouponsModule && typeof window.CouponsModule.init === 'function') {
        window.CouponsModule.init();
    }
});
</script>
<?php else: ?>
<script src="/toro/admin/assets/js/pages/coupons.js?v=<?= time() ?>"></script>
<script>
(function () {
    function tryInit() {
        if (window.CouponsModule && typeof window.CouponsModule.init === 'function') {
            window.CouponsModule.init();
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
