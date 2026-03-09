<?php
declare(strict_types=1);

/**
 * /toro/admin/fragments/roles.php
 * Production-ready Roles & Permissions management fragment for the admin panel.
 *
 * Accessible to super admins and users with the 'roles.manage' permission.
 * Only super admins may delete roles.
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
// GET USER CONTEXT
// ════════════════════════════════════════════════════════════
$user     = admin_user();
$lang     = admin_lang();
$dir      = admin_dir();
$csrf     = admin_csrf();
$tenantId = admin_tenant_id();

// ════════════════════════════════════════════════════════════
// CHECK PERMISSIONS  (roles-specific logic)
// ════════════════════════════════════════════════════════════
$canManageRoles = can('roles.manage') || is_super_admin();

if (!$canManageRoles) {
    if ($isFragment) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }
    http_response_code(403);
    die('Access denied');
}

$canView   = true;             // already passed the gate above
$canCreate = $canManageRoles;
$canEdit   = $canManageRoles;
$canDelete = is_super_admin(); // only super admin may delete roles

// ════════════════════════════════════════════════════════════
// API BASE + THEME HELPER
// ════════════════════════════════════════════════════════════
$apiBase = '/api';

if (!function_exists('renderFragmentThemeVars')) {
    function renderFragmentThemeVars(array $theme): void {
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
?>
<style id="db-theme-vars-roles">
<?php renderFragmentThemeVars($GLOBALS['ADMIN_UI']['theme'] ?? []); ?>
</style>
<link rel="stylesheet" href="/toro/admin/assets/css/pages/roles.css?v=<?= time() ?>">

<?php
// ════════════════════════════════════════════════════════════
// HTML OUTPUT
// ════════════════════════════════════════════════════════════
?>
<div id="roles-page"
     class="page-container"
     dir="<?= htmlspecialchars($dir, ENT_QUOTES) ?>"
     data-page="roles"
     data-lang="<?= htmlspecialchars($lang, ENT_QUOTES) ?>"
     data-dir="<?= htmlspecialchars($dir, ENT_QUOTES) ?>"
     data-csrf="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>"
     data-tenant="<?= (int)$tenantId ?>">

    <!-- ── Page Header ──────────────────────────────────── -->
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <div>
            <h1 class="page-title mb-0">Roles &amp; Permissions</h1>
            <p class="page-subtitle text-muted mb-0">Manage roles, their permissions, and user assignments.</p>
        </div>
        <?php if ($canCreate): ?>
        <button type="button" id="btn-add-role" class="btn btn-primary">
            <i class="bi bi-shield-plus-fill me-1"></i>Add Role
        </button>
        <?php endif; ?>
    </div>

    <!-- ── Tab Navigation ──────────────────────────────── -->
    <div style="display:flex; gap:8px; margin-bottom:18px;">
        <button type="button" class="tab-btn btn btn-primary btn-sm" data-tab="roles" id="tab-roles">
            <i class="bi bi-shield-shaded me-1"></i>Roles
        </button>
        <button type="button" class="tab-btn btn btn-outline-secondary btn-sm" data-tab="user-roles" id="tab-user-roles">
            <i class="bi bi-people-fill me-1"></i>User Roles
        </button>
    </div>

    <!-- ════════════════════════════════════════════════════
         TAB 1 – ROLES & PERMISSIONS
    ════════════════════════════════════════════════════════ -->
    <div id="roles-tab-panel" class="tab-panel">

        <!-- Two-column layout: roles list | permissions panel -->
        <div style="display:grid; grid-template-columns:280px 1fr; gap:20px; align-items:start;">

            <!-- LEFT: Roles List ─────────────────────────── -->
            <div id="roles-list-panel" class="card" style="min-height:420px;">
                <div class="card-header bg-transparent d-flex align-items-center justify-content-between py-2">
                    <span class="fw-semibold small">Roles</span>
                    <span id="roles-count-badge" class="badge bg-secondary rounded-pill">0</span>
                </div>
                <div class="card-body p-0">
                    <ul id="roles-list" class="list-group list-group-flush" style="min-height:320px;">
                        <li class="list-group-item text-center text-muted py-4" id="roles-list-loading">
                            <div class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></div>
                            Loading roles…
                        </li>
                    </ul>
                </div>
                <?php if ($canCreate): ?>
                <div class="card-footer bg-transparent text-center py-2">
                    <button type="button" id="btn-add-role-list" class="btn btn-sm btn-outline-primary w-100">
                        <i class="bi bi-plus-circle me-1"></i>Add Role
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <!-- RIGHT: Permissions Panel ─────────────────── -->
            <div id="role-permissions-panel" class="card" style="min-height:420px;">
                <div class="card-header bg-transparent d-flex align-items-center justify-content-between py-2">
                    <span class="fw-semibold small" id="permissions-panel-title">Permissions</span>
                    <?php if ($canEdit): ?>
                    <button type="button" id="btn-save-permissions" class="btn btn-sm btn-primary d-none">
                        <i class="bi bi-save-fill me-1"></i>Save Permissions
                        <span id="btn-save-permissions-spinner" class="spinner-border spinner-border-sm ms-1 d-none" role="status" aria-hidden="true"></span>
                    </button>
                    <?php endif; ?>
                </div>
                <div class="card-body">

                    <!-- Empty state (default) -->
                    <div id="permissions-empty-state" class="text-center text-muted py-5">
                        <i class="bi bi-shield-lock display-6 d-block mb-2 opacity-50"></i>
                        Select a role from the list to manage its permissions.
                    </div>

                    <!-- Permission groups container (JS-rendered checkboxes) -->
                    <div id="permissions-groups-container" class="d-none"></div>

                </div>
            </div>

        </div><!-- /grid -->
    </div><!-- /#roles-tab-panel -->

    <!-- ════════════════════════════════════════════════════
         TAB 2 – USER ROLES
    ════════════════════════════════════════════════════════ -->
    <div id="user-roles-tab" class="tab-panel d-none">

        <!-- Search -->
        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-semibold" for="search-users-roles">Search Users</label>
                        <input type="text"
                               id="search-users-roles"
                               class="form-control form-control-sm"
                               placeholder="Name or email…">
                    </div>
                    <div class="col-auto d-flex gap-2">
                        <button type="button" id="btn-user-roles-search" class="btn btn-primary btn-sm">Search</button>
                        <button type="button" id="btn-user-roles-reset" class="btn btn-outline-secondary btn-sm">Reset</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- User Roles Table -->
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="user-roles-table">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">User</th>
                                <th>Current Roles</th>
                                <th class="text-end pe-3" style="width:110px">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="user-roles-tbody">
                            <tr id="user-roles-loading-row">
                                <td colspan="3" class="text-center py-5 text-muted">
                                    <div class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></div>
                                    Loading users…
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-transparent">
                <div id="user-roles-pagination" class="d-flex justify-content-center"></div>
            </div>
        </div>

    </div><!-- /#user-roles-tab -->

</div><!-- /#roles-page -->

<!-- ════════════════════════════════════════════════════════
     CREATE / EDIT ROLE MODAL
════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="role-modal" tabindex="-1" aria-labelledby="role-modal-label" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="role-modal-label">Add Role</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="role-id">

                <!-- Name -->
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="role-name">
                        Name <span class="text-danger">*</span>
                    </label>
                    <input type="text"
                           id="role-name"
                           class="form-control"
                           placeholder="e.g. Content Editor"
                           required>
                </div>

                <!-- Slug -->
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="role-slug">Slug</label>
                    <input type="text"
                           id="role-slug"
                           class="form-control font-monospace"
                           placeholder="e.g. content_editor">
                    <div class="form-text text-muted">Auto-generated from name. Used internally; avoid changing after creation.</div>
                </div>

                <!-- Description -->
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="role-description">Description</label>
                    <textarea id="role-description"
                              class="form-control"
                              rows="3"
                              placeholder="Describe what this role is for…"></textarea>
                </div>

                <!-- System role notice (read-only) -->
                <div id="role-system-notice" class="d-none">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="role-is-system" disabled>
                        <label class="form-check-label text-muted" for="role-is-system">
                            System Role
                        </label>
                    </div>
                    <div class="alert alert-warning py-2 small">
                        <i class="bi bi-info-circle me-1"></i>
                        System roles cannot be deleted. Their slug is locked after creation.
                    </div>
                </div>

                <!-- Alert -->
                <div id="role-alert" class="alert d-none" role="alert"></div>

            </div><!-- /.modal-body -->

            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="btn-save-role" class="btn btn-primary">
                    <span id="btn-save-role-text">Save</span>
                    <span id="btn-save-role-spinner" class="spinner-border spinner-border-sm ms-1 d-none" role="status" aria-hidden="true"></span>
                </button>
            </div>

        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════
     MANAGE USER ROLES MODAL
════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="user-roles-modal" tabindex="-1" aria-labelledby="user-roles-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="user-roles-modal-label">Manage User Roles</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="user-roles-user-id">

                <!-- User info summary -->
                <div id="user-roles-user-info" class="d-flex align-items-center gap-3 mb-4 p-3 rounded bg-light">
                    <div id="user-roles-avatar"
                         class="rounded-circle bg-secondary d-flex align-items-center justify-content-center text-white fw-bold"
                         style="width:48px;height:48px;flex-shrink:0;font-size:1.1rem;">
                        ?
                    </div>
                    <div>
                        <div id="user-roles-user-name" class="fw-semibold"></div>
                        <div id="user-roles-user-email" class="text-muted small"></div>
                    </div>
                </div>

                <!-- Role checkboxes (JS-rendered) -->
                <p class="fw-semibold mb-2">Assign Roles</p>
                <div id="user-roles-checkboxes" class="row g-2">
                    <div class="col-12 text-muted small">
                        <div class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></div>
                        Loading roles…
                    </div>
                </div>

                <!-- Alert -->
                <div id="user-roles-alert" class="alert d-none mt-3" role="alert"></div>

            </div><!-- /.modal-body -->

            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="btn-save-user-roles" class="btn btn-primary">
                    <span id="btn-save-user-roles-text">Save</span>
                    <span id="btn-save-user-roles-spinner" class="spinner-border spinner-border-sm ms-1 d-none" role="status" aria-hidden="true"></span>
                </button>
            </div>

        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════
     CONFIRM DELETE ROLE MODAL
════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="role-confirm-modal" tabindex="-1" aria-labelledby="role-confirm-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header border-0">
                <h5 class="modal-title text-danger" id="role-confirm-label">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Delete Role
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body pt-0">
                <p>Are you sure you want to delete the role
                   <strong id="role-confirm-name"></strong>?
                   Users assigned this role will lose the associated permissions.
                   This action cannot be undone.</p>
            </div>

            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="btn-confirm-delete-role" class="btn btn-danger" data-role-id="">
                    <span id="btn-confirm-delete-role-text">Delete</span>
                    <span id="btn-confirm-delete-role-spinner" class="spinner-border spinner-border-sm ms-1 d-none" role="status" aria-hidden="true"></span>
                </button>
            </div>

        </div>
    </div>
</div>

<!-- ── Toast Container ──────────────────────────────────── -->
<div id="toastContainer" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:1100"></div>

<!-- ════════════════════════════════════════════════════════
     CONFIG + SCRIPTS
════════════════════════════════════════════════════════════ -->
<script>
window.ROLES_CONFIG = {
    apiUrl:            '<?= $apiBase ?>/roles',
    permissionsApiUrl: '<?= $apiBase ?>/permissions',
    userRolesApiUrl:   '<?= $apiBase ?>/user_roles',
    usersApiUrl:       '<?= $apiBase ?>/users',
    csrfToken:         '<?= addslashes($csrf) ?>',
    lang:              '<?= addslashes($lang) ?>',
    dir:               '<?= addslashes($dir) ?>',
    canCreate:         <?= $canCreate ? 'true' : 'false' ?>,
    canEdit:           <?= $canEdit   ? 'true' : 'false' ?>,
    canDelete:         <?= $canDelete ? 'true' : 'false' ?>,
    isSuperAdmin:      <?= is_super_admin() ? 'true' : 'false' ?>,
    tenantId:          <?= (int)$tenantId ?>
};
</script>
<?php if ($isFragment): ?>
<script src="/toro/admin/assets/js/admin_core.js?v=<?= time() ?>"></script>
<script src="/toro/admin/assets/js/pages/roles.js?v=<?= time() ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.RolesModule && typeof window.RolesModule.init === 'function') {
        window.RolesModule.init();
    }
});
</script>
<?php else: ?>
<script src="/toro/admin/assets/js/pages/roles.js?v=<?= time() ?>"></script>
<script>
(function () {
    function tryInit() {
        if (window.RolesModule && typeof window.RolesModule.init === 'function') {
            window.RolesModule.init();
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
