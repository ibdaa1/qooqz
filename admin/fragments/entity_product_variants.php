<?php
declare(strict_types=1);

/**
 * /admin/fragments/entity_product_variants.php
 * Standalone Entity Products & Variants Management
 */

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$isEmbedded = isset($_GET['embedded']) || isset($_POST['embedded']);
$isFragment = $isAjax || $isEmbedded;

if ($isFragment) {
    require_once __DIR__ . '/../includes/admin_context.php';
} else {
    require_once __DIR__ . '/../includes/header.php';
}

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

$user     = admin_user();
$lang     = admin_lang();
$dir      = in_array($lang, ['ar', 'he', 'fa', 'ur']) ? 'rtl' : 'ltr';
$csrf     = admin_csrf();
$tenantId = admin_tenant_id();
$userId   = admin_user_id();

$canManage = can('manage_entities') || can('manage_entity_products') || is_super_admin();
$canView   = can_view_all('entities') || can_view_own('entities') || can_view_tenant('entities') || is_super_admin();

if (!$canView) {
    http_response_code(403);
    die('Access denied');
}

$entityId = isset($_GET['entity_id']) && (int)$_GET['entity_id'] > 0
    ? (int)$_GET['entity_id']
    : (isset($_SESSION['entity_id']) && (int)$_SESSION['entity_id'] > 0
        ? (int)$_SESSION['entity_id']
        : 0);

$apiBase = '/api';

// Translation helpers
if (!function_exists('__t')) {
    function __t($key, $fallback = '') {
        if (function_exists('i18n_get')) {
            $v = i18n_get($key);
            return $v ?? ($fallback ?? $key);
        }
        return $fallback ?? $key;
    }
}

$_epvStrings = [];
$_allowedLangs = ['en', 'ar', 'fa', 'he', 'ur', 'tr', 'fr', 'de', 'es'];
$_safeLang = in_array($lang, $_allowedLangs, true) ? $lang : 'en';
$_langFile = __DIR__ . '/../../languages/EntityProductVariants/' . $_safeLang . '.json';
if (file_exists($_langFile)) {
    $_json = json_decode(file_get_contents($_langFile), true);
    if (isset($_json['strings'])) {
        $_epvStrings = $_json['strings'];
    }
}

function _epvt($key, $fallback = '') {
    global $_epvStrings;
    $keys = explode('.', $key);
    $val = $_epvStrings;
    foreach ($keys as $k) {
        if (is_array($val) && isset($val[$k])) {
            $val = $val[$k];
        } else {
            return $fallback ?: $key;
        }
    }
    return is_string($val) ? $val : ($fallback ?: $key);
}
?>

<link rel="stylesheet" href="/admin/assets/css/pages/entity_product_variants.css?v=<?= time() ?>">
<meta data-page="entity_product_variants"
      data-i18n-files="/languages/EntityProductVariants/<?= rawurlencode($lang) ?>.json">

<div class="page-container" id="epvPageContainer" dir="<?= htmlspecialchars($dir) ?>">

    <!-- Page Header -->
    <div class="page-header">
        <h1 data-i18n="title"><?= htmlspecialchars(_epvt('title', 'Entity Product Variants')) ?></h1>
        <p data-i18n="subtitle"><?= htmlspecialchars(_epvt('subtitle', 'Manage entity products and their variants')) ?></p>
    </div>

    <!-- Super Admin: Tenant → Entity Cascade -->
    <?php if (is_super_admin()): ?>
    <div class="card" id="epvEntityFilterCard">
        <div class="card-body" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
            <div class="form-group" style="min-width:150px;">
                <label data-i18n="filter.tenant_id"><?= htmlspecialchars(_epvt('filter.tenant_id', 'Tenant ID')) ?></label>
                <div style="display:flex;gap:5px;">
                    <input type="number" id="epvTenantIdInput" class="form-control"
                           placeholder="<?= htmlspecialchars(_epvt('filter.enter_tenant_id', 'Enter Tenant ID')) ?>"
                           min="1" style="width:120px;"
                           value="<?= $tenantId ? (int)$tenantId : '' ?>">
                    <button id="epvBtnVerifyTenant" class="btn btn-secondary" style="white-space:nowrap;"
                            data-i18n="filter.verify"><?= htmlspecialchars(_epvt('filter.verify', 'Verify')) ?></button>
                </div>
                <small id="epvTenantNameDisplay" style="display:none;margin-top:4px;"></small>
            </div>
            <div class="form-group" style="flex:1;min-width:250px;">
                <label data-i18n="filter.entity"><?= htmlspecialchars(_epvt('filter.entity', 'Entity')) ?></label>
                <select id="epvEntityFilter" class="form-control">
                    <option value=""><?= htmlspecialchars(_epvt('filter.select_entity', 'Select Entity...')) ?></option>
                </select>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- Tenant Admin: Entity Selector -->
    <div class="card">
        <div class="card-body" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
            <div class="form-group" style="flex:1;min-width:250px;">
                <label data-i18n="filter.entity"><?= htmlspecialchars(_epvt('filter.entity', 'Entity')) ?></label>
                <select id="epvEntityFilter" class="form-control">
                    <option value=""><?= htmlspecialchars(_epvt('filter.select_entity', 'Select Entity...')) ?></option>
                </select>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ═══════════════════════════════════ -->
    <!-- Unified: Products & Variants       -->
    <!-- ═══════════════════════════════════ -->
    <div id="epvUnifiedContent" style="display:none;">
        <div class="section-header">
            <div class="section-search">
                <input type="text" id="epvProductSearch" class="form-control"
                       placeholder="<?= htmlspecialchars(_epvt('search_placeholder', 'Search products or variants...')) ?>">
            </div>
            <?php if ($canManage): ?>
            <button id="epvBtnAddProduct" class="btn btn-primary" data-i18n="products.add_product">
                <?= htmlspecialchars(_epvt('products.add_product', 'Add Products')) ?>
            </button>
            <?php endif; ?>
        </div>

        <div id="epvUnifiedList" class="items-list"></div>
        <div id="epvUnifiedEmpty" class="empty-state" style="display:none;">
            <p data-i18n="products.no_products"><?= htmlspecialchars(_epvt('products.no_products', 'No entity products yet. Add products to get started.')) ?></p>
        </div>

        <?php if ($canManage): ?>
        <div class="section-footer" id="epvUnifiedFooter" style="display:none;">
            <button id="epvBtnSaveAll" class="btn btn-success" data-i18n="save_all">
                <?= htmlspecialchars(_epvt('save_all', 'Save All')) ?>
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- ═══════════════════════════════════ -->
    <!-- Modal: Product Selection            -->
    <!-- ═══════════════════════════════════ -->
    <div class="modal-overlay" id="epvProductsModal" style="display:none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 data-i18n="products.select_products"><?= htmlspecialchars(_epvt('products.select_products', 'Select Products')) ?></h3>
                <button class="modal-close" id="epvCloseProductsModal">&times;</button>
            </div>
            <div class="modal-body">
                <input type="text" id="epvModalProductSearch" class="form-control"
                       placeholder="<?= htmlspecialchars(_epvt('products.search_products', 'Search products...')) ?>">
                <div class="modal-actions-bar">
                    <button class="btn btn-sm btn-secondary" id="epvSelectAllProducts"
                            data-i18n="products.select_all"><?= htmlspecialchars(_epvt('products.select_all', 'Select All')) ?></button>
                    <button class="btn btn-sm btn-secondary" id="epvDeselectAllProducts"
                            data-i18n="products.deselect_all"><?= htmlspecialchars(_epvt('products.deselect_all', 'Deselect All')) ?></button>
                    <span id="epvProductSelectedCount" class="selected-count">0 <?= htmlspecialchars(_epvt('products.selected_count', 'selected')) ?></span>
                </div>
                <div id="epvModalProductsList" class="modal-items-list">
                    <div class="loading-text" data-i18n="products.loading_products">
                        <?= htmlspecialchars(_epvt('products.loading_products', 'Loading products...')) ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" id="epvConfirmProductSelection"
                        data-i18n="products.add_selected"><?= htmlspecialchars(_epvt('products.add_selected', 'Add Selected')) ?></button>
                <button class="btn btn-secondary" id="epvCancelProductSelection"
                        data-i18n="cancel"><?= htmlspecialchars(_epvt('cancel', 'Cancel')) ?></button>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════ -->
    <!-- Modal: Variant Selection            -->
    <!-- ═══════════════════════════════════ -->
    <div class="modal-overlay" id="epvVariantsModal" style="display:none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 data-i18n="variants.select_variants"><?= htmlspecialchars(_epvt('variants.select_variants', 'Select Variants')) ?></h3>
                <button class="modal-close" id="epvCloseVariantsModal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group" style="margin-bottom:10px;">
                    <label data-i18n="filter.product"><?= htmlspecialchars(_epvt('filter.product', 'Product')) ?></label>
                    <select id="epvModalVariantProductFilter" class="form-control">
                        <option value=""><?= htmlspecialchars(_epvt('variants.select_product_first', 'Select a product first')) ?></option>
                    </select>
                </div>
                <div class="modal-actions-bar">
                    <button class="btn btn-sm btn-secondary" id="epvSelectAllVariants"
                            data-i18n="products.select_all"><?= htmlspecialchars(_epvt('products.select_all', 'Select All')) ?></button>
                    <button class="btn btn-sm btn-secondary" id="epvDeselectAllVariants"
                            data-i18n="products.deselect_all"><?= htmlspecialchars(_epvt('products.deselect_all', 'Deselect All')) ?></button>
                    <span id="epvVariantSelectedCount" class="selected-count">0 <?= htmlspecialchars(_epvt('variants.selected_count', 'selected')) ?></span>
                </div>
                <div id="epvModalVariantsList" class="modal-items-list">
                    <div class="loading-text" data-i18n="variants.select_product_to_see_variants">
                        <?= htmlspecialchars(_epvt('variants.select_product_to_see_variants', 'Select a product to see its variants')) ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" id="epvConfirmVariantSelection"
                        data-i18n="variants.add_selected"><?= htmlspecialchars(_epvt('variants.add_selected', 'Add Selected')) ?></button>
                <button class="btn btn-secondary" id="epvCancelVariantSelection"
                        data-i18n="cancel"><?= htmlspecialchars(_epvt('cancel', 'Cancel')) ?></button>
            </div>
        </div>
    </div>

</div>

<!-- Hidden data -->
<input type="hidden" id="epvCsrfToken" value="<?= htmlspecialchars($csrf) ?>">
<input type="hidden" id="epvTenantId" value="<?= (int)$tenantId ?>">
<input type="hidden" id="epvEntityId" value="<?= (int)$entityId ?>">
<input type="hidden" id="epvLang" value="<?= htmlspecialchars($lang) ?>">
<input type="hidden" id="epvCanManage" value="<?= $canManage ? '1' : '0' ?>">
<input type="hidden" id="epvIsSuperAdmin" value="<?= is_super_admin() ? '1' : '0' ?>">

<script src="/admin/assets/js/pages/entity_product_variants.js?v=<?= time() ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.EntityProductVariants !== 'undefined') {
        window.EntityProductVariants.init();
    }
});
</script>

<?php if (!$isFragment): ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<?php endif; ?>
