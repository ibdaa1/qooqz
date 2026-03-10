<?php
declare(strict_types=1);

/**
 * /admin/fragments/categories.php
 * Production Version — Categories Management
 *
 * ✅ Fragment / standalone dual-mode
 * ✅ Role-based + resource-based permission system
 * ✅ Multi-language / RTL support
 * ✅ Full CRUD UI with create/edit modal (parent category selector) and delete confirmation
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
$canViewAll   = can_view_all('categories');
$canViewOwn   = can_view_own('categories');
$canCreate    = can_create('categories');
$canEditAll   = can_edit_all('categories');
$canEditOwn   = can_edit_own('categories');
$canDeleteAll = can_delete_all('categories');
$canDeleteOwn = can_delete_own('categories');

$canView   = $canViewAll || $canViewOwn || is_super_admin();
$canEdit   = $canEditAll || $canEditOwn;
$canDelete = $canDeleteAll || $canDeleteOwn;

if (!$canView && !is_super_admin()) {
    if ($isFragment) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    } else {
        http_response_code(403);
        die('Access denied: You do not have permission to view categories.');
    }
}

// ════════════════════════════════════════════════════════════
// TRANSLATION HELPERS
// ════════════════════════════════════════════════════════════
if (!function_exists('__t')) {
    function __t(string $key, string $fallback = ''): string {
        if (function_exists('i18n_get')) {
            $v = i18n_get($key);
            return $v ?? ($fallback !== '' ? $fallback : $key);
        }
        return $fallback !== '' ? $fallback : $key;
    }
}

// ════════════════════════════════════════════════════════════
// API BASE
// ════════════════════════════════════════════════════════════
$apiBase = '/toro/api/v1';

// ════════════════════════════════════════════════════════════
// DB-DRIVEN CSS VARS HELPER
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
        foreach ($theme['font_settings'] ?? [] as $f) {
            if (empty($f['setting_key'])) continue;
            $sk = htmlspecialchars($f['setting_key'], ENT_QUOTES);
            $sh = htmlspecialchars(str_replace('_', '-', $f['setting_key']), ENT_QUOTES);
            if (!empty($f['font_family'])) {
                $ff = htmlspecialchars($f['font_family'], ENT_QUOTES);
                echo "    --{$sk}-family: {$ff};" . PHP_EOL;
                if ($sh !== $sk) echo "    --{$sh}-family: {$ff};" . PHP_EOL;
            }
            if (!empty($f['font_size'])) {
                $fs = htmlspecialchars($f['font_size'], ENT_QUOTES);
                echo "    --{$sk}-size: {$fs};" . PHP_EOL;
                if ($sh !== $sk) echo "    --{$sh}-size: {$fs};" . PHP_EOL;
            }
            if (!empty($f['font_weight'])) {
                $fw = htmlspecialchars($f['font_weight'], ENT_QUOTES);
                echo "    --{$sk}-weight: {$fw};" . PHP_EOL;
                if ($sh !== $sk) echo "    --{$sh}-weight: {$fw};" . PHP_EOL;
            }
        }
        foreach ($theme['design_settings'] ?? [] as $d) {
            if (empty($d['setting_key']) || !isset($d['setting_value'])) continue;
            $dk = htmlspecialchars($d['setting_key'], ENT_QUOTES);
            $dh = htmlspecialchars(str_replace('_', '-', $d['setting_key']), ENT_QUOTES);
            $dv = htmlspecialchars($d['setting_value'], ENT_QUOTES);
            echo "    --{$dk}: {$dv};" . PHP_EOL;
            if ($dh !== $dk) echo "    --{$dh}: {$dv};" . PHP_EOL;
        }
        foreach ($theme['button_styles'] ?? [] as $b) {
            if (empty($b['slug'])) continue;
            $slug = preg_replace('/[^a-z0-9_-]/', '-', strtolower((string)$b['slug']));
            if (!empty($b['background_color'])) echo "    --btn-{$slug}-bg: " . htmlspecialchars($b['background_color'], ENT_QUOTES) . ';' . PHP_EOL;
            if (!empty($b['text_color']))        echo "    --btn-{$slug}-color: " . htmlspecialchars($b['text_color'], ENT_QUOTES) . ';' . PHP_EOL;
            if (!empty($b['border_color']))      echo "    --btn-{$slug}-border: " . htmlspecialchars($b['border_color'], ENT_QUOTES) . ';' . PHP_EOL;
            if (!empty($b['border_radius']))     echo "    --btn-{$slug}-radius: " . htmlspecialchars((string)$b['border_radius'], ENT_QUOTES) . 'px;' . PHP_EOL;
        }
        foreach ($theme['card_styles'] ?? [] as $cs) {
            if (empty($cs['slug'])) continue;
            $slug = preg_replace('/[^a-z0-9_-]/', '-', strtolower((string)$cs['slug']));
            if (!empty($cs['background_color'])) echo "    --card-{$slug}-bg: " . htmlspecialchars($cs['background_color'], ENT_QUOTES) . ';' . PHP_EOL;
            if (!empty($cs['border_color']))     echo "    --card-{$slug}-border: " . htmlspecialchars($cs['border_color'], ENT_QUOTES) . ';' . PHP_EOL;
            if (!empty($cs['border_radius']))    echo "    --card-{$slug}-radius: " . htmlspecialchars((string)$cs['border_radius'], ENT_QUOTES) . 'px;' . PHP_EOL;
            if (!empty($cs['shadow_style']))     echo "    --card-{$slug}-shadow: " . htmlspecialchars($cs['shadow_style'], ENT_QUOTES) . ';' . PHP_EOL;
            if (!empty($cs['padding']))          echo "    --card-{$slug}-padding: " . htmlspecialchars($cs['padding'], ENT_QUOTES) . ';' . PHP_EOL;
        }
        echo '}' . PHP_EOL;
    }
}

?>
<!-- DB-driven CSS vars -->
<style id="db-theme-vars-categories">
<?php renderFragmentThemeVars($GLOBALS['ADMIN_UI']['theme'] ?? []); ?>
<?php if (!empty($GLOBALS['ADMIN_UI']['theme']['generated_css'])): ?>
<?= $GLOBALS['ADMIN_UI']['theme']['generated_css'] ?>
<?php endif; ?>
</style>
<link rel="stylesheet" href="/toro/admin/assets/css/pages/categories.css?v=<?= time() ?>">

<!-- Toast Container -->
<div id="toastContainer" class="toast-container" aria-live="polite" aria-atomic="true"
     style="position:fixed; bottom:24px; <?= $dir === 'rtl' ? 'left' : 'right' ?>:24px; z-index:99999; display:flex; flex-direction:column; gap:10px; pointer-events:none;"></div>

<!-- ═══════════════════════════════════════════════════════════════════
     PAGE CONTAINER
     ═══════════════════════════════════════════════════════════════════ -->
<div class="page-container"
     id="categories-page"
     dir="<?= htmlspecialchars($dir) ?>"
     data-lang="<?= htmlspecialchars($lang) ?>"
     data-dir="<?= htmlspecialchars($dir) ?>"
     data-csrf="<?= htmlspecialchars($csrf) ?>"
     data-tenant="<?= $tenantId ?>">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title" data-i18n="categories.title"><?= __t('categories.title', 'Categories') ?></h1>
            <p class="page-subtitle" data-i18n="categories.subtitle"><?= __t('categories.subtitle', 'Manage your product categories') ?></p>
        </div>
        <div class="page-header-actions">
            <?php if ($canCreate): ?>
            <button id="btnAddCategory" class="btn btn-primary" aria-label="<?= htmlspecialchars(__t('categories.add_new', 'Add Category')) ?>">
                <i class="fas fa-plus" aria-hidden="true"></i>
                <span data-i18n="categories.add_new"><?= __t('categories.add_new', 'Add Category') ?></span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card filter-card" role="search" aria-label="<?= htmlspecialchars(__t('filters.label', 'Filter categories')) ?>">
        <div class="card-body">
            <div class="filter-row">
                <!-- Search -->
                <div class="filter-group filter-group--search">
                    <label for="filter-search" class="sr-only"><?= __t('filters.search', 'Search') ?></label>
                    <div class="input-icon-wrapper">
                        <i class="fas fa-search input-icon" aria-hidden="true"></i>
                        <input type="search"
                               id="filter-search"
                               class="form-control form-control--icon"
                               data-i18n-placeholder="categories.filter.search_placeholder"
                               placeholder="<?= htmlspecialchars(__t('categories.filter.search_placeholder', 'Search categories…')) ?>"
                               autocomplete="off">
                    </div>
                </div>

                <!-- Status Filter -->
                <div class="filter-group">
                    <label for="filter-status" class="sr-only"><?= __t('filters.status', 'Status') ?></label>
                    <select id="filter-status" class="form-control" aria-label="<?= htmlspecialchars(__t('filters.status', 'Status')) ?>">
                        <option value=""  data-i18n="filters.all_statuses"><?= __t('filters.all_statuses', 'All Statuses') ?></option>
                        <option value="1" data-i18n="filters.active"><?= __t('filters.active', 'Active') ?></option>
                        <option value="0" data-i18n="filters.inactive"><?= __t('filters.inactive', 'Inactive') ?></option>
                    </select>
                </div>

                <!-- Parent Category Filter -->
                <div class="filter-group">
                    <label for="filter-parent" class="sr-only"><?= __t('categories.filter.parent', 'Parent Category') ?></label>
                    <select id="filter-parent" class="form-control" aria-label="<?= htmlspecialchars(__t('categories.filter.parent', 'Parent Category')) ?>">
                        <option value=""  data-i18n="categories.filter.all_parents"><?= __t('categories.filter.all_parents', 'All Parents') ?></option>
                        <option value="0" data-i18n="categories.filter.top_level"><?= __t('categories.filter.top_level', 'Top Level Only') ?></option>
                    </select>
                </div>

                <!-- Apply / Reset -->
                <div class="filter-group filter-group--actions">
                    <button id="btnApplyFilter" class="btn btn-primary btn-sm" data-i18n="filters.apply">
                        <?= __t('filters.apply', 'Apply') ?>
                    </button>
                    <button id="btnResetFilter" class="btn btn-outline btn-sm" data-i18n="filters.reset">
                        <?= __t('filters.reset', 'Reset') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Data Card -->
    <div class="card data-card">
        <div class="card-body p-0">
            <div class="table-responsive" role="region" aria-label="<?= htmlspecialchars(__t('categories.table.label', 'Categories table')) ?>">
                <table class="data-table" aria-label="<?= htmlspecialchars(__t('categories.title', 'Categories')) ?>">
                    <thead>
                        <tr>
                            <th scope="col" class="col-id"     data-i18n="table.id"><?= __t('table.id', 'ID') ?></th>
                            <th scope="col" class="col-image"  data-i18n="categories.table.image"><?= __t('categories.table.image', 'Image') ?></th>
                            <th scope="col" class="col-name"   data-i18n="categories.table.name"><?= __t('categories.table.name', 'Name') ?></th>
                            <th scope="col" class="col-slug"   data-i18n="categories.table.slug"><?= __t('categories.table.slug', 'Slug') ?></th>
                            <th scope="col" class="col-parent" data-i18n="categories.table.parent"><?= __t('categories.table.parent', 'Parent') ?></th>
                            <th scope="col" class="col-sort"   data-i18n="table.sort"><?= __t('table.sort', 'Sort') ?></th>
                            <th scope="col" class="col-status" data-i18n="table.status"><?= __t('table.status', 'Status') ?></th>
                            <th scope="col" class="col-actions" data-i18n="table.actions"><?= __t('table.actions', 'Actions') ?></th>
                        </tr>
                    </thead>
                    <tbody id="categories-tbody" aria-live="polite" aria-relevant="additions removals">
                        <tr class="loading-row">
                            <td colspan="8" style="text-align:center; padding:40px; color:var(--text-secondary,#94a3b8);">
                                <i class="fas fa-spinner fa-spin" aria-hidden="true"></i>
                                <span style="margin-<?= $dir === 'rtl' ? 'right' : 'left' ?>:10px;"
                                      data-i18n="table.loading"><?= __t('table.loading', 'Loading…') ?></span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <div class="card-footer">
            <div id="categories-pagination" class="pagination-wrapper" role="navigation" aria-label="<?= htmlspecialchars(__t('pagination.label', 'Pagination')) ?>">
                <div class="pagination-info">
                    <span data-i18n="pagination.showing"><?= __t('pagination.showing', 'Showing') ?></span>
                    <span id="categoriesPageInfo">0–0 of 0</span>
                </div>
                <div class="pagination" id="categoriesPagination" aria-label="<?= htmlspecialchars(__t('pagination.pages', 'Pages')) ?>"></div>
            </div>
        </div>
    </div>

</div><!-- /#categories-page -->

<!-- ═══════════════════════════════════════════════════════════════════
     CREATE / EDIT MODAL
     ═══════════════════════════════════════════════════════════════════ -->
<div id="category-modal"
     class="modal"
     role="dialog"
     aria-modal="true"
     aria-labelledby="categoryModalTitle"
     style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.72); align-items:center; justify-content:center;">
    <div class="modal-content"
         style="background:var(--card-bg,#081127); border:1px solid var(--border-color,#263044); border-radius:12px; padding:0; width:min(580px,95vw); max-height:90vh; overflow-y:auto; position:relative; box-shadow:0 24px 64px rgba(0,0,0,0.5);">

        <!-- Modal Header -->
        <div style="display:flex; align-items:center; justify-content:space-between; padding:20px 24px 16px; border-bottom:1px solid var(--border-color,#263044); position:sticky; top:0; background:var(--card-bg,#081127); z-index:1;">
            <h3 id="categoryModalTitle"
                style="margin:0; font-size:1.1rem; font-weight:600; color:var(--text-primary,#fff);"
                data-i18n="categories.modal.add_title">
                <?= __t('categories.modal.add_title', 'Add Category') ?>
            </h3>
            <button type="button"
                    id="btnCloseCategoryModal"
                    class="btn-icon"
                    aria-label="<?= htmlspecialchars(__t('accessibility.close', 'Close')) ?>"
                    style="background:none; border:none; color:var(--text-secondary,#94a3b8); font-size:1.4rem; cursor:pointer; padding:4px; line-height:1;">
                &times;
            </button>
        </div>

        <!-- Modal Body -->
        <div style="padding:24px;">
            <form id="category-form" novalidate autocomplete="off">
                <input type="hidden" id="categoryId"      name="id">
                <input type="hidden" name="csrf_token"    value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="tenant_id"     value="<?= $tenantId ?>">

                <!-- Arabic Name -->
                <div class="form-group" style="margin-bottom:18px;">
                    <label for="categoryNameAr"
                           style="display:block; margin-bottom:6px; font-size:0.875rem; color:var(--text-secondary,#94a3b8);"
                           data-i18n="categories.form.name_ar">
                        <?= __t('categories.form.name_ar', 'Arabic Name') ?>
                    </label>
                    <input type="text"
                           id="categoryNameAr"
                           name="name_ar"
                           class="form-control"
                           dir="rtl"
                           data-i18n-placeholder="categories.form.name_ar_placeholder"
                           placeholder="<?= htmlspecialchars(__t('categories.form.name_ar_placeholder', 'اسم الفئة بالعربية')) ?>"
                           style="background:var(--input-bg,#061021); border:1px solid var(--border-color,#263044); border-radius:8px; padding:10px 14px; color:var(--text-primary,#fff); width:100%; box-sizing:border-box;">
                </div>

                <!-- English Name -->
                <div class="form-group" style="margin-bottom:18px;">
                    <label for="categoryNameEn"
                           style="display:block; margin-bottom:6px; font-size:0.875rem; color:var(--text-secondary,#94a3b8);"
                           data-i18n="categories.form.name_en">
                        <?= __t('categories.form.name_en', 'English Name') ?> <span style="color:#f87171;">*</span>
                    </label>
                    <input type="text"
                           id="categoryNameEn"
                           name="name_en"
                           class="form-control"
                           required
                           dir="ltr"
                           data-i18n-placeholder="categories.form.name_en_placeholder"
                           placeholder="<?= htmlspecialchars(__t('categories.form.name_en_placeholder', 'Category name in English')) ?>"
                           style="background:var(--input-bg,#061021); border:1px solid var(--border-color,#263044); border-radius:8px; padding:10px 14px; color:var(--text-primary,#fff); width:100%; box-sizing:border-box;">
                    <div class="invalid-feedback" style="color:#f87171; font-size:0.8rem; margin-top:4px; display:none;"
                         data-i18n="validation.required"><?= __t('validation.required', 'This field is required.') ?></div>
                </div>

                <!-- Slug -->
                <div class="form-group" style="margin-bottom:18px;">
                    <label for="categorySlug"
                           style="display:block; margin-bottom:6px; font-size:0.875rem; color:var(--text-secondary,#94a3b8);"
                           data-i18n="categories.form.slug">
                        <?= __t('categories.form.slug', 'Slug') ?>
                    </label>
                    <input type="text"
                           id="categorySlug"
                           name="slug"
                           class="form-control"
                           dir="ltr"
                           data-i18n-placeholder="categories.form.slug_placeholder"
                           placeholder="<?= htmlspecialchars(__t('categories.form.slug_placeholder', 'category-slug (auto-generated)')) ?>"
                           style="background:var(--input-bg,#061021); border:1px solid var(--border-color,#263044); border-radius:8px; padding:10px 14px; color:var(--text-primary,#fff); width:100%; box-sizing:border-box;">
                </div>

                <!-- Parent Category -->
                <div class="form-group" style="margin-bottom:18px;">
                    <label for="categoryParentId"
                           style="display:block; margin-bottom:6px; font-size:0.875rem; color:var(--text-secondary,#94a3b8);"
                           data-i18n="categories.form.parent">
                        <?= __t('categories.form.parent', 'Parent Category') ?>
                    </label>
                    <select id="categoryParentId"
                            name="parent_id"
                            class="form-control"
                            aria-label="<?= htmlspecialchars(__t('categories.form.parent', 'Parent Category')) ?>"
                            style="background:var(--input-bg,#061021); border:1px solid var(--border-color,#263044); border-radius:8px; padding:10px 14px; color:var(--text-primary,#fff); width:100%; box-sizing:border-box;">
                        <option value="" data-i18n="categories.form.no_parent"><?= __t('categories.form.no_parent', '— None (Top Level) —') ?></option>
                        <!-- Options populated by JS from API -->
                    </select>
                </div>

                <!-- Image URL -->
                <div class="form-group" style="margin-bottom:18px;">
                    <label for="categoryImageUrl"
                           style="display:block; margin-bottom:6px; font-size:0.875rem; color:var(--text-secondary,#94a3b8);"
                           data-i18n="categories.form.image_url">
                        <?= __t('categories.form.image_url', 'Image URL') ?>
                    </label>
                    <input type="url"
                           id="categoryImageUrl"
                           name="image_url"
                           class="form-control"
                           dir="ltr"
                           data-i18n-placeholder="categories.form.image_url_placeholder"
                           placeholder="<?= htmlspecialchars(__t('categories.form.image_url_placeholder', 'https://cdn.example.com/category.jpg')) ?>"
                           style="background:var(--input-bg,#061021); border:1px solid var(--border-color,#263044); border-radius:8px; padding:10px 14px; color:var(--text-primary,#fff); width:100%; box-sizing:border-box;">
                    <!-- Live preview -->
                    <div id="categoryImagePreview" style="margin-top:10px; display:none;">
                        <img id="categoryImageImg"
                             src=""
                             alt="<?= htmlspecialchars(__t('categories.form.image_preview_alt', 'Category image preview')) ?>"
                             style="max-height:80px; max-width:200px; border-radius:6px; border:1px solid var(--border-color,#263044); object-fit:cover;">
                    </div>
                </div>

                <!-- Sort Order & Is Active -->
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:18px;">
                    <div class="form-group">
                        <label for="categorySortOrder"
                               style="display:block; margin-bottom:6px; font-size:0.875rem; color:var(--text-secondary,#94a3b8);"
                               data-i18n="categories.form.sort_order">
                            <?= __t('categories.form.sort_order', 'Sort Order') ?>
                        </label>
                        <input type="number"
                               id="categorySortOrder"
                               name="sort_order"
                               class="form-control"
                               value="0"
                               min="0"
                               style="background:var(--input-bg,#061021); border:1px solid var(--border-color,#263044); border-radius:8px; padding:10px 14px; color:var(--text-primary,#fff); width:100%; box-sizing:border-box;">
                    </div>

                    <div class="form-group">
                        <label style="display:block; margin-bottom:6px; font-size:0.875rem; color:var(--text-secondary,#94a3b8);"
                               data-i18n="categories.form.is_active">
                            <?= __t('categories.form.is_active', 'Active') ?>
                        </label>
                        <label class="toggle-switch" style="display:inline-flex; align-items:center; gap:10px; cursor:pointer; margin-top:4px;">
                            <input type="checkbox"
                                   id="categoryIsActive"
                                   name="is_active"
                                   value="1"
                                   checked
                                   style="width:auto;"
                                   aria-label="<?= htmlspecialchars(__t('categories.form.is_active', 'Active')) ?>">
                            <span style="color:var(--text-primary,#fff); font-size:0.9rem;"
                                  data-i18n="categories.form.active_label"><?= __t('categories.form.active_label', 'Published') ?></span>
                        </label>
                    </div>
                </div>

                <!-- Modal Footer Actions -->
                <div style="display:flex; gap:12px; justify-content:flex-end; padding-top:16px; border-top:1px solid var(--border-color,#263044); margin-top:8px;">
                    <button type="button"
                            id="btnCancelCategoryModal"
                            class="btn btn-outline"
                            data-i18n="form.cancel">
                        <?= __t('form.cancel', 'Cancel') ?>
                    </button>
                    <button type="submit"
                            id="btnSaveCategory"
                            class="btn btn-primary"
                            data-i18n="form.save">
                        <i class="fas fa-save" aria-hidden="true"></i>
                        <span><?= __t('form.save', 'Save Category') ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════
     DELETE CONFIRMATION MODAL
     ═══════════════════════════════════════════════════════════════════ -->
<div id="category-confirm-modal"
     class="modal"
     role="alertdialog"
     aria-modal="true"
     aria-labelledby="categoryConfirmTitle"
     aria-describedby="categoryConfirmMessage"
     style="display:none; position:fixed; inset:0; z-index:10000; background:rgba(0,0,0,0.8); align-items:center; justify-content:center;">
    <div style="background:var(--card-bg,#081127); border:1px solid var(--border-color,#263044); border-radius:12px; padding:28px; width:min(440px,90vw); box-shadow:0 24px 64px rgba(0,0,0,0.6);">
        <div style="display:flex; align-items:center; gap:14px; margin-bottom:16px;">
            <div style="width:44px; height:44px; border-radius:50%; background:rgba(239,68,68,0.15); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <i class="fas fa-exclamation-triangle" style="color:#ef4444; font-size:1.1rem;" aria-hidden="true"></i>
            </div>
            <h3 id="categoryConfirmTitle"
                style="margin:0; font-size:1rem; font-weight:600; color:var(--text-primary,#fff);"
                data-i18n="categories.confirm.title">
                <?= __t('categories.confirm.title', 'Delete Category') ?>
            </h3>
        </div>
        <p id="categoryConfirmMessage"
           style="margin:0 0 8px; color:var(--text-secondary,#94a3b8); font-size:0.9rem; line-height:1.6;"
           data-i18n="categories.confirm.message">
            <?= __t('categories.confirm.message', 'Are you sure you want to delete this category?') ?>
        </p>
        <p style="margin:0 0 24px; color:#fbbf24; font-size:0.82rem; line-height:1.5;"
           data-i18n="categories.confirm.warning">
            <?= __t('categories.confirm.warning', 'Child categories and associated products may be affected.') ?>
        </p>
        <input type="hidden" id="categoryDeleteId" value="">
        <div style="display:flex; gap:12px; justify-content:flex-end;">
            <button type="button"
                    id="btnCancelCategoryDelete"
                    class="btn btn-outline"
                    data-i18n="form.cancel">
                <?= __t('form.cancel', 'Cancel') ?>
            </button>
            <button type="button"
                    id="btnConfirmCategoryDelete"
                    class="btn btn-danger"
                    style="background:#ef4444; border-color:#ef4444; color:#fff;"
                    data-i18n="categories.confirm.delete_btn">
                <i class="fas fa-trash" aria-hidden="true"></i>
                <span><?= __t('categories.confirm.delete_btn', 'Delete') ?></span>
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════
     CLIENT-SIDE GLOBALS & CONFIG
     ═══════════════════════════════════════════════════════════════════ -->
<script type="text/javascript">
window.APP_CONFIG = window.APP_CONFIG || {};
window.APP_CONFIG.API_BASE   = window.APP_CONFIG.API_BASE   || '<?= $apiBase ?>';
window.APP_CONFIG.TENANT_ID  = window.APP_CONFIG.TENANT_ID  || <?= $tenantId ?>;
window.APP_CONFIG.CSRF_TOKEN = window.APP_CONFIG.CSRF_TOKEN || '<?= addslashes($csrf) ?>';
window.APP_CONFIG.USER_ID    = window.APP_CONFIG.USER_ID    || <?= admin_user_id() ?>;

window.USER_LANGUAGE  = window.USER_LANGUAGE  || '<?= addslashes($lang) ?>';
window.USER_DIRECTION = window.USER_DIRECTION || '<?= addslashes($dir) ?>';
window.CSRF_TOKEN     = window.CSRF_TOKEN     || '<?= addslashes($csrf) ?>';

if (!window.ADMIN_UI) {
    window.ADMIN_UI = <?= json_encode($GLOBALS['ADMIN_UI'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
}

window.PAGE_PERMISSIONS = <?= json_encode([
    'canCreate'   => $canCreate,
    'canEdit'     => $canEdit,
    'canDelete'   => $canDelete,
    'canViewAll'  => $canViewAll,
    'canViewOwn'  => $canViewOwn,
    'canEditAll'  => $canEditAll,
    'canEditOwn'  => $canEditOwn,
    'canDeleteAll'=> $canDeleteAll,
    'canDeleteOwn'=> $canDeleteOwn,
    'isSuperAdmin'=> is_super_admin(),
], JSON_UNESCAPED_UNICODE) ?>;

window.CATEGORIES_CONFIG = {
    apiUrl: '<?= $apiBase ?>/categories',
    csrfToken:        '<?= addslashes($csrf) ?>',
    lang:             '<?= addslashes($lang) ?>',
    dir:              '<?= addslashes($dir) ?>',
    canCreate:        <?= $canCreate    ? 'true' : 'false' ?>,
    canEdit:          <?= $canEdit      ? 'true' : 'false' ?>,
    canDelete:        <?= $canDelete    ? 'true' : 'false' ?>,
    isSuperAdmin:     <?= is_super_admin() ? 'true' : 'false' ?>,
    tenantId:         <?= $tenantId ?>
};
</script>

<?php if ($isFragment): ?>
<script src="/toro/admin/assets/js/admin_framework.js?v=<?= time() ?>"></script>
<script src="/toro/admin/assets/js/pages/categories.js?v=<?= time() ?>"></script>
<script type="text/javascript">
(function () {
    var attempts = 0, maxAttempts = 50;
    var iv = setInterval(function () {
        attempts++;
        if (window.Categories && typeof window.Categories.init === 'function') {
            clearInterval(iv);
            try {
                var p = window.Categories.init();
                if (p && typeof p.then === 'function') {
                    p.catch(function (e) { console.error('[Categories] init error:', e); });
                }
            } catch (e) {
                console.error('[Categories] init threw:', e);
            }
        } else if (attempts >= maxAttempts) {
            clearInterval(iv);
            console.error('[Categories] Module not ready after ' + (maxAttempts * 100) + 'ms');
        }
    }, 100);
})();
</script>
<?php else: ?>
<script src="/toro/admin/assets/js/pages/categories.js?v=<?= time() ?>"></script>
<script type="text/javascript">
(function () {
    function tryInit() {
        if (window.Categories && typeof window.Categories.init === 'function') {
            window.Categories.init().catch(function (e) { console.error('[Categories] init error:', e); });
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
