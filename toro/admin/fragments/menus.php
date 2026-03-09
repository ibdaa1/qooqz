<?php
declare(strict_types=1);

/**
 * /toro/admin/fragments/menus.php
 * Production-ready Menus management fragment for the admin panel.
 *
 * Two-panel layout: left = menus list, right = menu items for selected menu.
 * Supports drag-handle ordering and nested (parent) items.
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
$canViewAll    = can_view_all('menus');
$canViewOwn    = can_view_own('menus');
$canViewTenant = can_view_tenant('menus');
$canView       = $canViewAll || $canViewOwn || $canViewTenant || is_super_admin();

$canCreate   = can_create('menus') || is_super_admin();
$canEditAll  = can_edit_all('menus');
$canEditOwn  = can_edit_own('menus');
$canEdit     = $canEditAll || $canEditOwn || is_super_admin();
$canDeleteAll = can_delete_all('menus');
$canDeleteOwn = can_delete_own('menus');
$canDelete   = $canDeleteAll || $canDeleteOwn || is_super_admin();

if (!$canView) {
    if ($isFragment) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    } else {
        http_response_code(403);
        die('Access denied: You do not have permission to view menus.');
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
<style id="db-theme-vars-menus">
<?php renderFragmentThemeVars($GLOBALS['ADMIN_UI']['theme'] ?? []); ?>
</style>
<link rel="stylesheet" href="/toro/admin/assets/css/pages/menus.css?v=<?= time() ?>">
<?php
// ════════════════════════════════════════════════════════════
// HTML OUTPUT
// ════════════════════════════════════════════════════════════
?>
<div id="menus-page"
     class="page-container"
     dir="<?= htmlspecialchars($dir, ENT_QUOTES) ?>"
     data-page="menus"
     data-lang="<?= htmlspecialchars($lang, ENT_QUOTES) ?>"
     data-tenant="<?= (int)$tenantId ?>">

    <!-- ── Page Header ────────────────────────────────────── -->
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <div>
            <h1 class="page-title mb-0">Menus</h1>
            <p class="page-subtitle text-muted mb-0">Build and organise navigation menus for your site.</p>
        </div>
    </div>

    <!-- ── Two-Panel Layout ──────────────────────────────── -->
    <div class="menus-two-panel"
         style="display:grid; grid-template-columns:300px 1fr; gap:18px; align-items:start;">

        <!-- ════════════════════════════════════════════════
             LEFT PANEL — All Menus
        ═══════════════════════════════════════════════════ -->
        <div class="menus-panel card h-100">

            <div class="card-header d-flex align-items-center justify-content-between py-2 px-3">
                <span class="fw-semibold">All Menus</span>
                <?php if ($canCreate): ?>
                <button type="button"
                        id="btn-add-menu"
                        class="btn btn-primary btn-sm"
                        title="Add new menu">
                    <i class="bi bi-plus-lg"></i>
                </button>
                <?php endif; ?>
            </div>

            <div class="card-body p-0">
                <!-- Loading state -->
                <div id="menus-list-loading" class="text-center py-4 text-muted small">
                    <div class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></div>
                    Loading menus…
                </div>

                <!-- Empty state -->
                <div id="menus-list-empty" class="text-center py-4 text-muted small d-none">
                    <i class="bi bi-collection d-block fs-3 mb-2 opacity-50"></i>
                    No menus yet.
                    <?php if ($canCreate): ?>
                    <br>Click <strong>+</strong> to create one.
                    <?php endif; ?>
                </div>

                <!-- Menus list -->
                <ul class="list-unstyled mb-0" id="menus-list">
                    <!-- Items injected by JS:
                         <li class="menu-list-item [active]" data-menu-id="…">
                           <div class="d-flex align-items-center px-3 py-2 gap-2">
                             <span class="menu-list-name flex-grow-1">Name</span>
                             <span class="badge bg-secondary">location</span>
                             [edit / delete icon buttons if permitted]
                           </div>
                         </li>
                    -->
                </ul>
            </div>

        </div><!-- /.menus-panel -->

        <!-- ════════════════════════════════════════════════
             RIGHT PANEL — Menu Items
        ═══════════════════════════════════════════════════ -->
        <div class="menu-items-panel card h-100">

            <div class="card-header d-flex align-items-center justify-content-between py-2 px-3">
                <span class="fw-semibold" id="selected-menu-name">Menu Items</span>
                <?php if ($canCreate): ?>
                <button type="button"
                        id="btn-add-menu-item"
                        class="btn btn-primary btn-sm d-none"
                        title="Add item to menu">
                    <i class="bi bi-plus-lg me-1"></i>Add Item
                </button>
                <?php endif; ?>
            </div>

            <div class="card-body">

                <!-- No menu selected — empty state -->
                <div id="menu-items-empty-state" class="text-center py-5 text-muted">
                    <i class="bi bi-arrow-left-circle d-block fs-2 mb-2 opacity-50"></i>
                    Select a menu to manage its items.
                </div>

                <!-- Items loading -->
                <div id="menu-items-loading" class="text-center py-4 text-muted small d-none">
                    <div class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></div>
                    Loading items…
                </div>

                <!-- No items yet -->
                <div id="menu-items-no-items" class="text-center py-4 text-muted small d-none">
                    <i class="bi bi-list-ul d-block fs-3 mb-2 opacity-50"></i>
                    This menu has no items yet.
                    <?php if ($canCreate): ?>
                    Click <strong>Add Item</strong> to get started.
                    <?php endif; ?>
                </div>

                <!-- Items list -->
                <div id="menu-items-list">
                    <!-- Items injected by JS.  Each row template:
                    <div class="menu-item-row d-flex align-items-center gap-2 py-2 px-1 border-bottom"
                         data-item-id="…" data-sort="…" data-parent="…">
                      <span class="drag-handle text-muted me-1" style="cursor:grab">⠿</span>
                      <span class="indent-indicator text-muted"></span>
                      <span class="item-label flex-grow-1 fw-medium"></span>
                      <span class="item-url small text-muted text-truncate" style="max-width:180px"></span>
                      [if canEdit:]
                      <button class="btn btn-sm btn-outline-secondary btn-move-up" title="Move up">↑</button>
                      <button class="btn btn-sm btn-outline-secondary btn-move-down" title="Move down">↓</button>
                      <button class="btn btn-sm btn-outline-secondary btn-edit-item" title="Edit">✎</button>
                      [if canDelete:]
                      <button class="btn btn-sm btn-outline-danger btn-delete-item" title="Delete">✕</button>
                    </div>
                    -->
                </div>

            </div><!-- /.card-body -->

        </div><!-- /.menu-items-panel -->

    </div><!-- /.menus-two-panel -->

</div><!-- /#menus-page -->

<!-- ══════════════════════════════════════════════════════════
     MENU MODAL (Create / Edit Menu)
═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="menu-modal" tabindex="-1" aria-labelledby="menuModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <form id="menu-form" novalidate>
                <input type="hidden" id="menu-id" name="id" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="menuModalLabel">Add Menu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">

                        <!-- Name -->
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="menu-name">
                                Menu Name <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   id="menu-name"
                                   name="name"
                                   class="form-control"
                                   placeholder="e.g. Main Navigation"
                                   required>
                            <div class="invalid-feedback">Please enter a menu name.</div>
                        </div>

                        <!-- Slug -->
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="menu-slug">Slug</label>
                            <input type="text"
                                   id="menu-slug"
                                   name="slug"
                                   class="form-control"
                                   placeholder="main-navigation">
                            <div class="form-text">Auto-generated from name if left blank.</div>
                        </div>

                        <!-- Location -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold" for="menu-location">Location</label>
                            <select id="menu-location" name="location" class="form-select">
                                <option value="">— None —</option>
                                <option value="header">Header</option>
                                <option value="footer">Footer</option>
                                <option value="sidebar">Sidebar</option>
                                <option value="mobile">Mobile</option>
                            </select>
                        </div>

                        <!-- Is Active -->
                        <div class="col-12 col-md-6 d-flex align-items-end pb-1">
                            <div class="form-check form-switch">
                                <input class="form-check-input"
                                       type="checkbox"
                                       id="menu-is-active"
                                       name="is_active"
                                       value="1"
                                       checked>
                                <label class="form-check-label fw-semibold" for="menu-is-active">Active</label>
                            </div>
                        </div>

                    </div>

                    <!-- Form error -->
                    <div id="menu-form-error" class="alert alert-danger mt-3 d-none" role="alert"></div>
                </div><!-- /.modal-body -->

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="btn-save-menu" class="btn btn-primary">
                        <span id="btn-save-menu-text">Save Menu</span>
                        <span id="btn-save-menu-spinner" class="spinner-border spinner-border-sm ms-1 d-none" role="status" aria-hidden="true"></span>
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MENU ITEM MODAL (Create / Edit Menu Item)
═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="menu-item-modal" tabindex="-1" aria-labelledby="menuItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="menu-item-form" novalidate>
                <input type="hidden" id="menu-item-id" name="id" value="">
                <input type="hidden" id="menu-item-menu-id" name="menu_id" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="menuItemModalLabel">Add Menu Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">

                        <!-- Label AR -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold" for="item-label-ar">
                                Label (Arabic) <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   id="item-label-ar"
                                   name="label_ar"
                                   class="form-control"
                                   dir="rtl"
                                   placeholder="التسمية"
                                   required>
                            <div class="invalid-feedback">Please enter the Arabic label.</div>
                        </div>

                        <!-- Label EN -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold" for="item-label-en">
                                Label (English) <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   id="item-label-en"
                                   name="label_en"
                                   class="form-control"
                                   placeholder="Label"
                                   required>
                            <div class="invalid-feedback">Please enter the English label.</div>
                        </div>

                        <!-- URL -->
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="item-url">
                                URL <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   id="item-url"
                                   name="url"
                                   class="form-control"
                                   placeholder="https://example.com/page or /relative/path"
                                   required>
                            <div class="invalid-feedback">Please enter a URL.</div>
                        </div>

                        <!-- Icon -->
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold" for="item-icon">Icon</label>
                            <input type="text"
                                   id="item-icon"
                                   name="icon"
                                   class="form-control"
                                   placeholder="bi bi-house or fa fa-home">
                            <div class="form-text">CSS icon class (optional).</div>
                        </div>

                        <!-- Target -->
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold" for="item-target">Open In</label>
                            <select id="item-target" name="target" class="form-select">
                                <option value="_self">Same Tab (_self)</option>
                                <option value="_blank">New Tab (_blank)</option>
                            </select>
                        </div>

                        <!-- Sort Order -->
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold" for="item-sort-order">Sort Order</label>
                            <input type="number"
                                   id="item-sort-order"
                                   name="sort_order"
                                   class="form-control"
                                   min="0"
                                   step="1"
                                   value="0">
                        </div>

                        <!-- Parent -->
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="item-parent-id">Parent Item</label>
                            <select id="item-parent-id" name="parent_id" class="form-select">
                                <option value="">— Top Level (no parent) —</option>
                                <!-- Populated dynamically by JS from current menu items -->
                            </select>
                            <div class="form-text">Nest this item under an existing item.</div>
                        </div>

                    </div>

                    <!-- Form error -->
                    <div id="menu-item-form-error" class="alert alert-danger mt-3 d-none" role="alert"></div>
                </div><!-- /.modal-body -->

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="btn-save-menu-item" class="btn btn-primary">
                        <span id="btn-save-menu-item-text">Save Item</span>
                        <span id="btn-save-menu-item-spinner" class="spinner-border spinner-border-sm ms-1 d-none" role="status" aria-hidden="true"></span>
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     CONFIRM DELETE MODAL (shared for menus & items)
═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="menu-confirm-modal" tabindex="-1" aria-labelledby="menuConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title text-danger" id="menuConfirmModalLabel">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Confirm Delete
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1" id="menu-confirm-message">Are you sure you want to delete this item?</p>
                <p class="text-muted small mb-0">This action cannot be undone.</p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button"
                        id="btn-confirm-delete"
                        class="btn btn-danger"
                        data-delete-type=""
                        data-delete-id="">
                    <span id="btn-confirm-delete-text">Delete</span>
                    <span id="btn-confirm-delete-spinner" class="spinner-border spinner-border-sm ms-1 d-none" role="status" aria-hidden="true"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── Toast Container ────────────────────────────────────── -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="menus-toast-container" style="z-index:1100"></div>

<!-- ════════════════════════════════════════════════════════
     CONFIG + SCRIPTS
═══════════════════════════════════════════════════════════ -->
<script>
window.MENUS_CONFIG = {
    apiUrl:          '<?= $apiBase ?>/menus',
    itemsApiUrl:     '<?= $apiBase ?>/menu_items',
    csrfToken:       '<?= addslashes($csrf) ?>',
    lang:            '<?= addslashes($lang) ?>',
    dir:             '<?= addslashes($dir) ?>',
    canCreate:       <?= $canCreate ? 'true' : 'false' ?>,
    canEdit:         <?= $canEdit   ? 'true' : 'false' ?>,
    canDelete:       <?= $canDelete ? 'true' : 'false' ?>,
    isSuperAdmin:    <?= is_super_admin() ? 'true' : 'false' ?>,
    tenantId:        <?= (int)$tenantId ?>,
    locationOptions: ['header','footer','sidebar','mobile']
};
</script>
<?php if ($isFragment): ?>
<script src="/toro/admin/assets/js/admin_core.js?v=<?= time() ?>"></script>
<script src="/toro/admin/assets/js/pages/menus.js?v=<?= time() ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.MenusModule && typeof window.MenusModule.init === 'function') {
        window.MenusModule.init();
    }
});
</script>
<?php else: ?>
<script src="/toro/admin/assets/js/pages/menus.js?v=<?= time() ?>"></script>
<script>
(function () {
    function tryInit() {
        if (window.MenusModule && typeof window.MenusModule.init === 'function') {
            window.MenusModule.init();
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
