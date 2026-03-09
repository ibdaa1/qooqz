<?php
declare(strict_types=1);

/**
 * /toro/admin/fragments/users.php
 * Production-ready Users management fragment for the admin panel.
 *
 * Super admins and users with 'users.manage' permission can access this page.
 * Roles are loaded from the roles API for the create/edit modal dropdown.
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
$canManageUsers = can('users.manage') || is_super_admin();

$canViewAll    = can_view_all('users');
$canViewOwn    = can_view_own('users');
$canViewTenant = can_view_tenant('users');
$canView       = $canViewAll || $canViewOwn || $canViewTenant || $canManageUsers;

$canCreate   = can_create('users') || $canManageUsers;
$canEditAll  = can_edit_all('users');
$canEditOwn  = can_edit_own('users');
$canEdit     = $canEditAll || $canEditOwn || $canManageUsers;
$canDeleteAll = can_delete_all('users');
$canDeleteOwn = can_delete_own('users');
$canDelete   = $canDeleteAll || $canDeleteOwn || $canManageUsers;

if (!($canView || $canManageUsers)) {
    if ($isFragment) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    } else {
        http_response_code(403);
        die('Access denied: You do not have permission to view users.');
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
<style id="db-theme-vars-users">
<?php renderFragmentThemeVars($GLOBALS['ADMIN_UI']['theme'] ?? []); ?>
</style>
<link rel="stylesheet" href="/toro/admin/assets/css/pages/users.css?v=<?= time() ?>">
<?php
// ════════════════════════════════════════════════════════════
// HTML OUTPUT
// ════════════════════════════════════════════════════════════
?>
<div id="users-page"
     class="page-container"
     dir="<?= htmlspecialchars($dir, ENT_QUOTES) ?>"
     data-page="users"
     data-lang="<?= htmlspecialchars($lang, ENT_QUOTES) ?>"
     data-tenant="<?= (int)$tenantId ?>">

    <!-- ── Page Header ────────────────────────────────────── -->
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <div>
            <h1 class="page-title mb-0">Users</h1>
            <p class="page-subtitle text-muted mb-0">Manage user accounts, roles, and access.</p>
        </div>
        <?php if ($canCreate): ?>
        <button type="button" id="btn-add-user" class="btn btn-primary">
            <i class="bi bi-person-plus-fill me-1"></i>Add User
        </button>
        <?php endif; ?>
    </div>

    <!-- ── Filter Card ────────────────────────────────────── -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-2 align-items-end">

                <!-- Search -->
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-semibold" for="filter-search">Search</label>
                    <input type="text"
                           id="filter-search"
                           class="form-control form-control-sm"
                           placeholder="Name or email…">
                </div>

                <!-- Role filter -->
                <div class="col-6 col-md-3">
                    <label class="form-label small fw-semibold" for="filter-role">Role</label>
                    <select id="filter-role" class="form-select form-select-sm">
                        <option value="">All Roles</option>
                        <!-- Populated by JS from roles API -->
                    </select>
                </div>

                <!-- Status filter -->
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

    <!-- ── Users Table ───────────────────────────────────── -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="users-table">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width:56px">Avatar</th>
                            <th>Name / Email</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="users-tbody">
                        <tr id="users-loading-row">
                            <td colspan="7" class="text-center py-5 text-muted">
                                <div class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></div>
                                Loading users…
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <!-- Pagination -->
        <div class="card-footer bg-transparent d-flex justify-content-between align-items-center flex-wrap gap-2">
            <small class="text-muted" id="users-pagination-info"></small>
            <nav aria-label="Users pagination">
                <ul class="pagination pagination-sm mb-0" id="users-pagination"></ul>
            </nav>
        </div>
    </div>

</div><!-- /#users-page -->

<!-- ══════════════════════════════════════════════════════════
     CREATE / EDIT USER MODAL
═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="user-modal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="user-form" novalidate autocomplete="off">
                <input type="hidden" id="user-id" name="id" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="userModalLabel">Add User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">

                        <!-- Name -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold" for="user-name">
                                Full Name <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   id="user-name"
                                   name="name"
                                   class="form-control"
                                   placeholder="John Doe"
                                   required>
                            <div class="invalid-feedback">Please enter the user's full name.</div>
                        </div>

                        <!-- Email -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold" for="user-email">
                                Email <span class="text-danger">*</span>
                            </label>
                            <input type="email"
                                   id="user-email"
                                   name="email"
                                   class="form-control"
                                   placeholder="user@example.com"
                                   autocomplete="off"
                                   required>
                            <div class="invalid-feedback">Please enter a valid email address.</div>
                        </div>

                        <!-- Phone -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold" for="user-phone">Phone</label>
                            <input type="tel"
                                   id="user-phone"
                                   name="phone"
                                   class="form-control"
                                   placeholder="+1 555 000 0000">
                        </div>

                        <!-- Password -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold" for="user-password">
                                Password
                                <span class="text-danger" id="password-required-star">*</span>
                            </label>
                            <div class="input-group">
                                <input type="password"
                                       id="user-password"
                                       name="password"
                                       class="form-control"
                                       placeholder="Leave blank to keep current"
                                       autocomplete="new-password">
                                <button type="button"
                                        id="btn-toggle-password"
                                        class="btn btn-outline-secondary"
                                        tabindex="-1"
                                        title="Show / hide password">
                                    <i class="bi bi-eye" id="password-eye-icon"></i>
                                </button>
                            </div>
                            <div class="form-text" id="password-hint">Required when creating a new user.</div>
                            <div class="invalid-feedback" id="password-invalid">Please enter a password.</div>
                        </div>

                        <!-- Role -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold" for="user-role-id">
                                Role <span class="text-danger">*</span>
                            </label>
                            <select id="user-role-id" name="role_id" class="form-select" required>
                                <option value="">— Select role —</option>
                                <!-- Populated dynamically from roles API -->
                            </select>
                            <div class="invalid-feedback">Please assign a role.</div>
                        </div>

                        <!-- Is Active -->
                        <div class="col-12 col-md-6 d-flex align-items-end pb-1">
                            <div class="form-check form-switch">
                                <input class="form-check-input"
                                       type="checkbox"
                                       id="user-is-active"
                                       name="is_active"
                                       value="1"
                                       checked>
                                <label class="form-check-label fw-semibold" for="user-is-active">
                                    Active account
                                </label>
                            </div>
                        </div>

                    </div><!-- /.row -->

                    <!-- Form error banner -->
                    <div id="user-form-error" class="alert alert-danger mt-3 d-none" role="alert"></div>
                </div><!-- /.modal-body -->

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="btn-save-user" class="btn btn-primary">
                        <span id="btn-save-user-text">Save User</span>
                        <span id="btn-save-user-spinner" class="spinner-border spinner-border-sm ms-1 d-none" role="status" aria-hidden="true"></span>
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     CONFIRM DELETE MODAL
═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="user-confirm-modal" tabindex="-1" aria-labelledby="userConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title text-danger" id="userConfirmModalLabel">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Delete User
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1">Are you sure you want to delete <strong id="confirm-user-name"></strong>?</p>
                <p class="text-muted small mb-0">This action is permanent and cannot be undone.</p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="btn-confirm-delete" class="btn btn-danger" data-user-id="">
                    <span id="btn-confirm-delete-text">Delete</span>
                    <span id="btn-confirm-delete-spinner" class="spinner-border spinner-border-sm ms-1 d-none" role="status" aria-hidden="true"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── Toast Container ────────────────────────────────────── -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="users-toast-container" style="z-index:1100"></div>

<!-- ════════════════════════════════════════════════════════
     CONFIG + SCRIPTS
═══════════════════════════════════════════════════════════ -->
<script>
window.USERS_CONFIG = {
    apiUrl:       '<?= $apiBase ?>/users',
    rolesApiUrl:  '<?= $apiBase ?>/roles',
    csrfToken:    '<?= addslashes($csrf) ?>',
    lang:         '<?= addslashes($lang) ?>',
    dir:          '<?= addslashes($dir) ?>',
    canCreate:    <?= $canCreate ? 'true' : 'false' ?>,
    canEdit:      <?= $canEdit   ? 'true' : 'false' ?>,
    canDelete:    <?= $canDelete ? 'true' : 'false' ?>,
    isSuperAdmin: <?= is_super_admin() ? 'true' : 'false' ?>,
    tenantId:     <?= (int)$tenantId ?>
};
</script>
<?php if ($isFragment): ?>
<script src="/toro/admin/assets/js/admin_core.js?v=<?= time() ?>"></script>
<script src="/toro/admin/assets/js/pages/users.js?v=<?= time() ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.UsersModule && typeof window.UsersModule.init === 'function') {
        window.UsersModule.init();
    }
});
</script>
<?php else: ?>
<script src="/toro/admin/assets/js/pages/users.js?v=<?= time() ?>"></script>
<script>
(function () {
    function tryInit() {
        if (window.UsersModule && typeof window.UsersModule.init === 'function') {
            window.UsersModule.init();
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
