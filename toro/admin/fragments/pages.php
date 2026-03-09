<?php
declare(strict_types=1);

/**
 * /toro/admin/fragments/pages.php
 * Production-ready Pages management fragment for the admin panel.
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
// CHECK PERMISSIONS
// ════════════════════════════════════════════════════════════
$canView   = can_view_all('pages') || can_view_own('pages') || is_super_admin();
$canCreate = can_create('pages') || is_super_admin();
$canEdit   = can_edit_all('pages') || can_edit_own('pages') || is_super_admin();
$canDelete = can_delete_all('pages') || can_delete_own('pages') || is_super_admin();

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
<style id="db-theme-vars-pages">
<?php renderFragmentThemeVars($GLOBALS['ADMIN_UI']['theme'] ?? []); ?>
</style>
<link rel="stylesheet" href="/toro/admin/assets/css/pages/pages.css?v=<?= time() ?>">

<?php
// ════════════════════════════════════════════════════════════
// HTML OUTPUT
// ════════════════════════════════════════════════════════════
?>
<div id="pages-page"
     class="page-container"
     dir="<?= htmlspecialchars($dir, ENT_QUOTES) ?>"
     data-page="pages"
     data-lang="<?= htmlspecialchars($lang, ENT_QUOTES) ?>"
     data-dir="<?= htmlspecialchars($dir, ENT_QUOTES) ?>"
     data-csrf="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>"
     data-tenant="<?= (int)$tenantId ?>">

    <!-- ── Page Header ──────────────────────────────────── -->
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <div>
            <h1 class="page-title mb-0">Pages</h1>
            <p class="page-subtitle text-muted mb-0">Create and manage static content pages.</p>
        </div>
        <?php if ($canCreate): ?>
        <button type="button" id="btn-add-page" class="btn btn-primary">
            <i class="bi bi-file-earmark-plus-fill me-1"></i>Add Page
        </button>
        <?php endif; ?>
    </div>

    <!-- ── Filter Card ──────────────────────────────────── -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-2 align-items-end">

                <div class="col-12 col-md-5">
                    <label class="form-label small fw-semibold" for="search-pages">Search</label>
                    <input type="text"
                           id="search-pages"
                           class="form-control form-control-sm"
                           placeholder="Title or slug…">
                </div>

                <div class="col-6 col-md-3">
                    <label class="form-label small fw-semibold" for="filter-status">Status</label>
                    <select id="filter-status" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                    </select>
                </div>

                <div class="col-6 col-md-auto d-flex gap-2">
                    <button type="button" id="btn-filter-apply" class="btn btn-primary btn-sm">Apply</button>
                    <button type="button" id="btn-filter-reset" class="btn btn-outline-secondary btn-sm">Reset</button>
                </div>

            </div>
        </div>
    </div>

    <!-- ── Pages Table ──────────────────────────────────── -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="pages-table">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width:70px">ID</th>
                            <th>Title</th>
                            <th>Slug</th>
                            <th style="width:110px">Status</th>
                            <th style="width:150px">Updated</th>
                            <th class="text-end pe-3" style="width:110px">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="pages-tbody">
                        <tr id="pages-loading-row">
                            <td colspan="6" class="text-center py-5 text-muted">
                                <div class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></div>
                                Loading pages…
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-transparent">
            <div id="pages-pagination" class="d-flex justify-content-center"></div>
        </div>
    </div>

</div><!-- /#pages-page -->

<!-- ════════════════════════════════════════════════════════
     CREATE / EDIT MODAL
════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="page-modal" tabindex="-1" aria-labelledby="page-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-lg" style="max-width:900px">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="page-modal-label">Add Page</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="page-id">

                <!-- Title -->
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="page-title">
                        Title <span class="text-danger">*</span>
                    </label>
                    <input type="text"
                           id="page-title"
                           class="form-control"
                           placeholder="Page title"
                           required>
                </div>

                <!-- Slug -->
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="page-slug">Slug</label>
                    <input type="text"
                           id="page-slug"
                           class="form-control"
                           placeholder="page-slug">
                    <div class="form-text text-muted">Auto-generated from title if left blank.</div>
                </div>

                <!-- Content -->
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="page-content">Content (HTML)</label>
                    <textarea id="page-content"
                              class="form-control font-monospace"
                              rows="12"
                              placeholder="Enter page HTML content…"></textarea>
                </div>

                <!-- SEO (collapsible) -->
                <details class="mb-3 border rounded p-3">
                    <summary class="fw-semibold text-secondary" style="cursor:pointer;user-select:none">
                        SEO Settings
                    </summary>
                    <div class="mt-3">
                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="page-meta-title">Meta Title</label>
                            <input type="text"
                                   id="page-meta-title"
                                   class="form-control"
                                   placeholder="SEO title (defaults to page title)">
                        </div>
                        <div class="mb-0">
                            <label class="form-label fw-semibold" for="page-meta-description">Meta Description</label>
                            <textarea id="page-meta-description"
                                      class="form-control"
                                      rows="3"
                                      placeholder="Brief description for search engines…"></textarea>
                        </div>
                    </div>
                </details>

                <!-- Status -->
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="page-status">Status</label>
                    <select id="page-status" class="form-select">
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                    </select>
                </div>

                <!-- Alert -->
                <div id="page-alert" class="alert d-none" role="alert"></div>

            </div><!-- /.modal-body -->

            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="btn-save-page" class="btn btn-primary">
                    <span id="btn-save-page-text">Save</span>
                    <span id="btn-save-page-spinner" class="spinner-border spinner-border-sm ms-1 d-none" role="status" aria-hidden="true"></span>
                </button>
            </div>

        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════
     CONFIRM DELETE MODAL
════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="page-confirm-modal" tabindex="-1" aria-labelledby="page-confirm-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header border-0">
                <h5 class="modal-title text-danger" id="page-confirm-label">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Delete Page
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body pt-0">
                <p class="mb-0">Are you sure you want to delete this page? This action cannot be undone.</p>
            </div>

            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="btn-confirm-delete-page" class="btn btn-danger" data-page-id="">
                    <span id="btn-confirm-delete-page-text">Delete</span>
                    <span id="btn-confirm-delete-page-spinner" class="spinner-border spinner-border-sm ms-1 d-none" role="status" aria-hidden="true"></span>
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
window.PAGES_CONFIG = {
    apiUrl:       '<?= $apiBase ?>/pages',
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
<script src="/toro/admin/assets/js/pages/pages.js?v=<?= time() ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.PagesModule && typeof window.PagesModule.init === 'function') {
        window.PagesModule.init();
    }
});
</script>
<?php else: ?>
<script src="/toro/admin/assets/js/pages/pages.js?v=<?= time() ?>"></script>
<script>
(function () {
    function tryInit() {
        if (window.PagesModule && typeof window.PagesModule.init === 'function') {
            window.PagesModule.init();
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
