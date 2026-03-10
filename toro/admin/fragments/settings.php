<?php
declare(strict_types=1);

/**
 * /toro/admin/fragments/settings.php
 * Production-ready Settings management fragment for the admin panel.
 *
 * Accessible to super admins and users with the 'settings.view' permission.
 * Only super admins may add or delete setting keys; others with 'settings.edit'
 * can update values.
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
// CHECK PERMISSIONS  (settings-specific logic)
// ════════════════════════════════════════════════════════════
$canViewSettings = can('settings.view') || is_super_admin();
$canEditSettings = can('settings.edit') || is_super_admin();

if (!$canViewSettings) {
    if ($isFragment) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }
    http_response_code(403);
    die('Access denied');
}

$canView   = $canViewSettings;
$canCreate = false;           // settings are managed by key, not freely created
$canEdit   = $canEditSettings;
$canDelete = is_super_admin(); // only super admin can remove keys

// ════════════════════════════════════════════════════════════
// API BASE + THEME HELPER
// ════════════════════════════════════════════════════════════
$apiBase = '/toro/api/v1';

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
<style id="db-theme-vars-settings">
<?php renderFragmentThemeVars($GLOBALS['ADMIN_UI']['theme'] ?? []); ?>
</style>
<link rel="stylesheet" href="/toro/admin/assets/css/pages/settings.css?v=<?= time() ?>">

<?php
// ════════════════════════════════════════════════════════════
// HTML OUTPUT
// ════════════════════════════════════════════════════════════
?>
<div id="settings-page"
     class="page-container"
     dir="<?= htmlspecialchars($dir, ENT_QUOTES) ?>"
     data-page="settings"
     data-lang="<?= htmlspecialchars($lang, ENT_QUOTES) ?>"
     data-dir="<?= htmlspecialchars($dir, ENT_QUOTES) ?>"
     data-csrf="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>"
     data-tenant="<?= (int)$tenantId ?>">

    <!-- ── Page Header ──────────────────────────────────── -->
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <div>
            <h1 class="page-title mb-0">Settings</h1>
            <p class="page-subtitle text-muted mb-0">Manage system settings and configuration.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <?php if (is_super_admin()): ?>
            <button type="button" id="btn-add-setting" class="btn btn-outline-secondary">
                <i class="bi bi-plus-circle me-1"></i>Add Key
            </button>
            <?php endif; ?>
            <?php if ($canEdit): ?>
            <button type="button" id="btn-save-all" class="btn btn-primary">
                <i class="bi bi-save-fill me-1"></i>Save All
                <span id="btn-save-all-spinner" class="spinner-border spinner-border-sm ms-1 d-none" role="status" aria-hidden="true"></span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Filter Card ──────────────────────────────────── -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-2 align-items-end">

                <div class="col-12 col-md-5">
                    <label class="form-label small fw-semibold" for="search-settings">Search</label>
                    <input type="text"
                           id="search-settings"
                           class="form-control form-control-sm"
                           placeholder="Search by key or description…">
                </div>

                <div class="col-6 col-md-3">
                    <label class="form-label small fw-semibold" for="filter-group">Group</label>
                    <select id="filter-group" class="form-select form-select-sm">
                        <option value="">All Groups</option>
                        <!-- Populated by JS from settings API -->
                    </select>
                </div>

                <div class="col-6 col-md-auto d-flex gap-2">
                    <button type="button" id="btn-filter-apply" class="btn btn-primary btn-sm">Apply</button>
                    <button type="button" id="btn-filter-reset" class="btn btn-outline-secondary btn-sm">Reset</button>
                </div>

            </div>
        </div>
    </div>

    <!-- ── Settings Container (JS-rendered grouped cards) ── -->
    <div id="settings-container">
        <!-- Empty / loading state -->
        <div id="settings-empty-state" class="text-center py-5 text-muted">
            <div class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></div>
            Loading settings…
        </div>
    </div>

    <!-- Alert for bulk-save feedback -->
    <div id="settings-alert" class="alert d-none mt-3" role="alert"></div>

</div><!-- /#settings-page -->

<!-- ════════════════════════════════════════════════════════
     ADD SETTING MODAL (super admin only)
════════════════════════════════════════════════════════════ -->
<?php if (is_super_admin()): ?>
<div class="modal fade" id="setting-modal" tabindex="-1" aria-labelledby="setting-modal-label" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="setting-modal-label">Add Setting Key</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">

                <!-- Key -->
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="setting-key">
                        Key <span class="text-danger">*</span>
                    </label>
                    <input type="text"
                           id="setting-key"
                           class="form-control font-monospace"
                           placeholder="e.g. site_name"
                           required>
                    <div class="form-text text-muted">Use snake_case. Cannot be changed after creation.</div>
                </div>

                <!-- Value -->
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="setting-value">Value</label>
                    <textarea id="setting-value"
                              class="form-control font-monospace"
                              rows="3"
                              placeholder="Setting value…"></textarea>
                </div>

                <!-- Type -->
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="setting-type">Type</label>
                    <select id="setting-type" class="form-select">
                        <option value="text">Text</option>
                        <option value="number">Number</option>
                        <option value="boolean">Boolean</option>
                        <option value="json">JSON</option>
                        <option value="color">Color</option>
                        <option value="image">Image</option>
                    </select>
                </div>

                <!-- Group -->
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="setting-group">Group</label>
                    <input type="text"
                           id="setting-group"
                           class="form-control"
                           placeholder="e.g. general, email, appearance">
                </div>

                <!-- Description -->
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="setting-description">Description</label>
                    <input type="text"
                           id="setting-description"
                           class="form-control"
                           placeholder="Human-readable description">
                </div>

                <!-- Alert -->
                <div id="setting-alert" class="alert d-none" role="alert"></div>

            </div><!-- /.modal-body -->

            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="btn-save-setting" class="btn btn-primary">
                    <span id="btn-save-setting-text">Save</span>
                    <span id="btn-save-setting-spinner" class="spinner-border spinner-border-sm ms-1 d-none" role="status" aria-hidden="true"></span>
                </button>
            </div>

        </div>
    </div>
</div>
<?php endif; ?>

<!-- ════════════════════════════════════════════════════════
     CONFIRM DELETE MODAL
════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="setting-confirm-modal" tabindex="-1" aria-labelledby="setting-confirm-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header border-0">
                <h5 class="modal-title text-danger" id="setting-confirm-label">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Delete Setting Key
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body pt-0">
                <p>Are you sure you want to permanently delete the key
                   <strong id="setting-confirm-key" class="font-monospace"></strong>?
                   This action cannot be undone.</p>
            </div>

            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="btn-confirm-delete-setting" class="btn btn-danger" data-setting-key="">
                    <span id="btn-confirm-delete-setting-text">Delete</span>
                    <span id="btn-confirm-delete-setting-spinner" class="spinner-border spinner-border-sm ms-1 d-none" role="status" aria-hidden="true"></span>
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
window.SETTINGS_CONFIG = {
    apiUrl:       '<?= $apiBase ?>/settings',
    csrfToken:    '<?= addslashes($csrf) ?>',
    lang:         '<?= addslashes($lang) ?>',
    dir:          '<?= addslashes($dir) ?>',
    canEdit:      <?= $canEdit   ? 'true' : 'false' ?>,
    canDelete:    <?= $canDelete ? 'true' : 'false' ?>,
    isSuperAdmin: <?= is_super_admin() ? 'true' : 'false' ?>,
    canAddKeys:   <?= is_super_admin() ? 'true' : 'false' ?>,
    tenantId:     <?= (int)$tenantId ?>
};
</script>
<?php if ($isFragment): ?>
<script src="/toro/admin/assets/js/admin_core.js?v=<?= time() ?>"></script>
<script src="/toro/admin/assets/js/pages/settings.js?v=<?= time() ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.SettingsModule && typeof window.SettingsModule.init === 'function') {
        window.SettingsModule.init();
    }
});
</script>
<?php else: ?>
<script src="/toro/admin/assets/js/pages/settings.js?v=<?= time() ?>"></script>
<script>
(function () {
    function tryInit() {
        if (window.SettingsModule && typeof window.SettingsModule.init === 'function') {
            window.SettingsModule.init();
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
