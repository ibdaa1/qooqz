<?php
declare(strict_types=1);

/**
 * /admin/fragments/banners.php
 * Production Version — Banners Management
 *
 * ✅ Fragment / standalone dual-mode
 * ✅ Role-based + resource-based permission system
 * ✅ Multi-language / RTL support
 * ✅ Full CRUD: position filter, date range, live image preview
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
$canViewAll   = can_view_all('banners');
$canViewOwn   = can_view_own('banners');
$canCreate    = can_create('banners');
$canEditAll   = can_edit_all('banners');
$canEditOwn   = can_edit_own('banners');
$canDeleteAll = can_delete_all('banners');
$canDeleteOwn = can_delete_own('banners');

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
        die('Access denied: You do not have permission to view banners.');
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
$apiBase = '/api';

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
<style id="db-theme-vars-banners">
<?php renderFragmentThemeVars($GLOBALS['ADMIN_UI']['theme'] ?? []); ?>
<?php if (!empty($GLOBALS['ADMIN_UI']['theme']['generated_css'])): ?>
<?= $GLOBALS['ADMIN_UI']['theme']['generated_css'] ?>
<?php endif; ?>
</style>
<link rel="stylesheet" href="/toro/admin/assets/css/pages/banners.css?v=<?= time() ?>">

<!-- Toast Container -->
<div id="toastContainer" class="toast-container" aria-live="polite" aria-atomic="true"
     style="position:fixed; bottom:24px; <?= $dir === 'rtl' ? 'left' : 'right' ?>:24px; z-index:99999; display:flex; flex-direction:column; gap:10px; pointer-events:none;"></div>

<!-- ═══════════════════════════════════════════════════════════════════
     PAGE CONTAINER
     ═══════════════════════════════════════════════════════════════════ -->
<div class="page-container"
     id="banners-page"
     dir="<?= htmlspecialchars($dir) ?>"
     data-lang="<?= htmlspecialchars($lang) ?>"
     data-dir="<?= htmlspecialchars($dir) ?>"
     data-csrf="<?= htmlspecialchars($csrf) ?>"
     data-tenant="<?= $tenantId ?>">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title" data-i18n="banners.title"><?= __t('banners.title', 'Banners') ?></h1>
            <p class="page-subtitle" data-i18n="banners.subtitle"><?= __t('banners.subtitle', 'Manage promotional banners and hero images') ?></p>
        </div>
        <div class="page-header-actions">
            <?php if ($canCreate): ?>
            <button id="btnAddBanner" class="btn btn-primary" aria-label="<?= htmlspecialchars(__t('banners.add_new', 'Add Banner')) ?>">
                <i class="fas fa-plus" aria-hidden="true"></i>
                <span data-i18n="banners.add_new"><?= __t('banners.add_new', 'Add Banner') ?></span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card filter-card" role="search" aria-label="<?= htmlspecialchars(__t('filters.label', 'Filter banners')) ?>">
        <div class="card-body">
            <div class="filter-row">
                <!-- Search by title -->
                <div class="filter-group filter-group--search">
                    <label for="filter-search" class="sr-only"><?= __t('filters.search', 'Search') ?></label>
                    <div class="input-icon-wrapper">
                        <i class="fas fa-search input-icon" aria-hidden="true"></i>
                        <input type="search"
                               id="filter-search"
                               class="form-control form-control--icon"
                               data-i18n-placeholder="banners.filter.search_placeholder"
                               placeholder="<?= htmlspecialchars(__t('banners.filter.search_placeholder', 'Search by title…')) ?>"
                               autocomplete="off">
                    </div>
                </div>

                <!-- Position Filter -->
                <div class="filter-group">
                    <label for="filter-position" class="sr-only"><?= __t('banners.filter.position', 'Position') ?></label>
                    <select id="filter-position" class="form-control" aria-label="<?= htmlspecialchars(__t('banners.filter.position', 'Position')) ?>">
                        <option value=""             data-i18n="banners.filter.all_positions"><?= __t('banners.filter.all_positions', 'All Positions') ?></option>
                        <option value="home_hero"    data-i18n="banners.position.home_hero"><?= __t('banners.position.home_hero', 'Home Hero') ?></option>
                        <option value="sidebar"      data-i18n="banners.position.sidebar"><?= __t('banners.position.sidebar', 'Sidebar') ?></option>
                        <option value="category_top" data-i18n="banners.position.category_top"><?= __t('banners.position.category_top', 'Category Top') ?></option>
                        <option value="footer"       data-i18n="banners.position.footer"><?= __t('banners.position.footer', 'Footer') ?></option>
                    </select>
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
            <div class="table-responsive" role="region" aria-label="<?= htmlspecialchars(__t('banners.table.label', 'Banners table')) ?>">
                <table class="data-table" aria-label="<?= htmlspecialchars(__t('banners.title', 'Banners')) ?>">
                    <thead>
                        <tr>
                            <th scope="col" class="col-id"       data-i18n="table.id"><?= __t('table.id', 'ID') ?></th>
                            <th scope="col" class="col-preview"  data-i18n="banners.table.preview"><?= __t('banners.table.preview', 'Preview') ?></th>
                            <th scope="col" class="col-title"    data-i18n="banners.table.title"><?= __t('banners.table.title', 'Title') ?></th>
                            <th scope="col" class="col-position" data-i18n="banners.table.position"><?= __t('banners.table.position', 'Position') ?></th>
                            <th scope="col" class="col-sort"     data-i18n="table.sort"><?= __t('table.sort', 'Sort') ?></th>
                            <th scope="col" class="col-active"   data-i18n="table.active"><?= __t('table.active', 'Active') ?></th>
                            <th scope="col" class="col-starts"   data-i18n="banners.table.starts"><?= __t('banners.table.starts', 'Starts') ?></th>
                            <th scope="col" class="col-ends"     data-i18n="banners.table.ends"><?= __t('banners.table.ends', 'Ends') ?></th>
                            <th scope="col" class="col-actions"  data-i18n="table.actions"><?= __t('table.actions', 'Actions') ?></th>
                        </tr>
                    </thead>
                    <tbody id="banners-tbody" aria-live="polite" aria-relevant="additions removals">
                        <tr class="loading-row">
                            <td colspan="9" style="text-align:center; padding:40px; color:var(--text-secondary,#94a3b8);">
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
            <div id="banners-pagination" class="pagination-wrapper" role="navigation" aria-label="<?= htmlspecialchars(__t('pagination.label', 'Pagination')) ?>">
                <div class="pagination-info">
                    <span data-i18n="pagination.showing"><?= __t('pagination.showing', 'Showing') ?></span>
                    <span id="bannersPageInfo">0–0 of 0</span>
                </div>
                <div class="pagination" id="bannersPagination" aria-label="<?= htmlspecialchars(__t('pagination.pages', 'Pages')) ?>"></div>
            </div>
        </div>
    </div>

</div><!-- /#banners-page -->

<!-- ═══════════════════════════════════════════════════════════════════
     CREATE / EDIT MODAL
     ═══════════════════════════════════════════════════════════════════ -->
<div id="banner-modal"
     class="modal"
     role="dialog"
     aria-modal="true"
     aria-labelledby="bannerModalTitle"
     style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.72); align-items:center; justify-content:center;">
    <div class="modal-content"
         style="background:var(--card-bg,#081127); border:1px solid var(--border-color,#263044); border-radius:12px; padding:0; width:min(600px,95vw); max-height:90vh; overflow-y:auto; position:relative; box-shadow:0 24px 64px rgba(0,0,0,0.5);">

        <!-- Modal Header -->
        <div style="display:flex; align-items:center; justify-content:space-between; padding:20px 24px 16px; border-bottom:1px solid var(--border-color,#263044); position:sticky; top:0; background:var(--card-bg,#081127); z-index:1;">
            <h3 id="bannerModalTitle"
                style="margin:0; font-size:1.1rem; font-weight:600; color:var(--text-primary,#fff);"
                data-i18n="banners.modal.add_title">
                <?= __t('banners.modal.add_title', 'Add Banner') ?>
            </h3>
            <button type="button"
                    id="btnCloseBannerModal"
                    aria-label="<?= htmlspecialchars(__t('accessibility.close', 'Close')) ?>"
                    style="background:none; border:none; color:var(--text-secondary,#94a3b8); font-size:1.4rem; cursor:pointer; padding:4px; line-height:1;">
                &times;
            </button>
        </div>

        <!-- Modal Body -->
        <div style="padding:24px;">
            <form id="banner-form" novalidate autocomplete="off">
                <input type="hidden" id="bannerId"      name="id">
                <input type="hidden" name="csrf_token"  value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="tenant_id"   value="<?= $tenantId ?>">

                <!-- Title -->
                <div class="form-group" style="margin-bottom:18px;">
                    <label for="bannerTitle"
                           style="display:block; margin-bottom:6px; font-size:0.875rem; color:var(--text-secondary,#94a3b8);"
                           data-i18n="banners.form.title">
                        <?= __t('banners.form.title', 'Title') ?> <span style="color:#f87171;">*</span>
                    </label>
                    <input type="text"
                           id="bannerTitle"
                           name="title"
                           class="form-control"
                           required
                           data-i18n-placeholder="banners.form.title_placeholder"
                           placeholder="<?= htmlspecialchars(__t('banners.form.title_placeholder', 'Summer Sale 2025')) ?>"
                           style="background:var(--input-bg,#061021); border:1px solid var(--border-color,#263044); border-radius:8px; padding:10px 14px; color:var(--text-primary,#fff); width:100%; box-sizing:border-box;">
                    <div class="invalid-feedback" style="color:#f87171; font-size:0.8rem; margin-top:4px; display:none;"
                         data-i18n="validation.required"><?= __t('validation.required', 'This field is required.') ?></div>
                </div>

                <!-- Image URL with live preview -->
                <div class="form-group" style="margin-bottom:18px;">
                    <label for="bannerImageUrl"
                           style="display:block; margin-bottom:6px; font-size:0.875rem; color:var(--text-secondary,#94a3b8);"
                           data-i18n="banners.form.image_url">
                        <?= __t('banners.form.image_url', 'Image URL') ?> <span style="color:#f87171;">*</span>
                    </label>
                    <input type="url"
                           id="bannerImageUrl"
                           name="image_url"
                           class="form-control"
                           required
                           dir="ltr"
                           data-i18n-placeholder="banners.form.image_url_placeholder"
                           placeholder="<?= htmlspecialchars(__t('banners.form.image_url_placeholder', 'https://cdn.example.com/banner.jpg')) ?>"
                           style="background:var(--input-bg,#061021); border:1px solid var(--border-color,#263044); border-radius:8px; padding:10px 14px; color:var(--text-primary,#fff); width:100%; box-sizing:border-box;">
                    <div class="invalid-feedback" style="color:#f87171; font-size:0.8rem; margin-top:4px; display:none;"
                         data-i18n="validation.required"><?= __t('validation.required', 'This field is required.') ?></div>

                    <!-- Live image preview -->
                    <div id="bannerImagePreview"
                         style="margin-top:12px; display:none; background:var(--background-secondary,#0f1724); border:1px solid var(--border-color,#263044); border-radius:8px; overflow:hidden; max-width:100%;">
                        <img id="bannerImagePreviewImg"
                             src=""
                             alt="<?= htmlspecialchars(__t('banners.form.preview_alt', 'Banner preview')) ?>"
                             style="width:100%; max-height:160px; object-fit:cover; display:block;">
                        <div style="padding:6px 10px; font-size:0.75rem; color:var(--text-secondary,#94a3b8);"
                             data-i18n="banners.form.preview_label">
                            <?= __t('banners.form.preview_label', 'Preview') ?>
                        </div>
                    </div>
                </div>

                <!-- Link URL -->
                <div class="form-group" style="margin-bottom:18px;">
                    <label for="bannerLinkUrl"
                           style="display:block; margin-bottom:6px; font-size:0.875rem; color:var(--text-secondary,#94a3b8);"
                           data-i18n="banners.form.link_url">
                        <?= __t('banners.form.link_url', 'Link URL') ?>
                    </label>
                    <input type="url"
                           id="bannerLinkUrl"
                           name="link_url"
                           class="form-control"
                           dir="ltr"
                           data-i18n-placeholder="banners.form.link_url_placeholder"
                           placeholder="<?= htmlspecialchars(__t('banners.form.link_url_placeholder', 'https://example.com/sale')) ?>"
                           style="background:var(--input-bg,#061021); border:1px solid var(--border-color,#263044); border-radius:8px; padding:10px 14px; color:var(--text-primary,#fff); width:100%; box-sizing:border-box;">
                </div>

                <!-- Position -->
                <div class="form-group" style="margin-bottom:18px;">
                    <label for="bannerPosition"
                           style="display:block; margin-bottom:6px; font-size:0.875rem; color:var(--text-secondary,#94a3b8);"
                           data-i18n="banners.form.position">
                        <?= __t('banners.form.position', 'Position') ?> <span style="color:#f87171;">*</span>
                    </label>
                    <select id="bannerPosition"
                            name="position"
                            class="form-control"
                            required
                            aria-label="<?= htmlspecialchars(__t('banners.form.position', 'Position')) ?>"
                            style="background:var(--input-bg,#061021); border:1px solid var(--border-color,#263044); border-radius:8px; padding:10px 14px; color:var(--text-primary,#fff); width:100%; box-sizing:border-box;">
                        <option value=""             data-i18n="banners.form.select_position"><?= __t('banners.form.select_position', '— Select Position —') ?></option>
                        <option value="home_hero"    data-i18n="banners.position.home_hero"><?= __t('banners.position.home_hero', 'Home Hero') ?></option>
                        <option value="sidebar"      data-i18n="banners.position.sidebar"><?= __t('banners.position.sidebar', 'Sidebar') ?></option>
                        <option value="category_top" data-i18n="banners.position.category_top"><?= __t('banners.position.category_top', 'Category Top') ?></option>
                        <option value="footer"       data-i18n="banners.position.footer"><?= __t('banners.position.footer', 'Footer') ?></option>
                    </select>
                    <div class="invalid-feedback" style="color:#f87171; font-size:0.8rem; margin-top:4px; display:none;"
                         data-i18n="validation.required"><?= __t('validation.required', 'Please select a position.') ?></div>
                </div>

                <!-- Sort Order & Is Active -->
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:18px;">
                    <div class="form-group">
                        <label for="bannerSortOrder"
                               style="display:block; margin-bottom:6px; font-size:0.875rem; color:var(--text-secondary,#94a3b8);"
                               data-i18n="banners.form.sort_order">
                            <?= __t('banners.form.sort_order', 'Sort Order') ?>
                        </label>
                        <input type="number"
                               id="bannerSortOrder"
                               name="sort_order"
                               class="form-control"
                               value="0"
                               min="0"
                               style="background:var(--input-bg,#061021); border:1px solid var(--border-color,#263044); border-radius:8px; padding:10px 14px; color:var(--text-primary,#fff); width:100%; box-sizing:border-box;">
                    </div>

                    <div class="form-group">
                        <label style="display:block; margin-bottom:6px; font-size:0.875rem; color:var(--text-secondary,#94a3b8);"
                               data-i18n="banners.form.is_active">
                            <?= __t('banners.form.is_active', 'Active') ?>
                        </label>
                        <label class="toggle-switch" style="display:inline-flex; align-items:center; gap:10px; cursor:pointer; margin-top:4px;">
                            <input type="checkbox"
                                   id="bannerIsActive"
                                   name="is_active"
                                   value="1"
                                   checked
                                   style="width:auto;"
                                   aria-label="<?= htmlspecialchars(__t('banners.form.is_active', 'Active')) ?>">
                            <span style="color:var(--text-primary,#fff); font-size:0.9rem;"
                                  data-i18n="banners.form.active_label"><?= __t('banners.form.active_label', 'Published') ?></span>
                        </label>
                    </div>
                </div>

                <!-- Date Range: Starts At / Ends At -->
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:18px;">
                    <div class="form-group">
                        <label for="bannerStartsAt"
                               style="display:block; margin-bottom:6px; font-size:0.875rem; color:var(--text-secondary,#94a3b8);"
                               data-i18n="banners.form.starts_at">
                            <?= __t('banners.form.starts_at', 'Starts At') ?>
                        </label>
                        <input type="date"
                               id="bannerStartsAt"
                               name="starts_at"
                               class="form-control"
                               style="background:var(--input-bg,#061021); border:1px solid var(--border-color,#263044); border-radius:8px; padding:10px 14px; color:var(--text-primary,#fff); width:100%; box-sizing:border-box; color-scheme:dark;">
                        <p style="margin:4px 0 0; font-size:0.75rem; color:var(--text-secondary,#94a3b8);"
                           data-i18n="banners.form.starts_hint">
                            <?= __t('banners.form.starts_hint', 'Leave empty to start immediately') ?>
                        </p>
                    </div>

                    <div class="form-group">
                        <label for="bannerEndsAt"
                               style="display:block; margin-bottom:6px; font-size:0.875rem; color:var(--text-secondary,#94a3b8);"
                               data-i18n="banners.form.ends_at">
                            <?= __t('banners.form.ends_at', 'Ends At') ?>
                        </label>
                        <input type="date"
                               id="bannerEndsAt"
                               name="ends_at"
                               class="form-control"
                               style="background:var(--input-bg,#061021); border:1px solid var(--border-color,#263044); border-radius:8px; padding:10px 14px; color:var(--text-primary,#fff); width:100%; box-sizing:border-box; color-scheme:dark;">
                        <p style="margin:4px 0 0; font-size:0.75rem; color:var(--text-secondary,#94a3b8);"
                           data-i18n="banners.form.ends_hint">
                            <?= __t('banners.form.ends_hint', 'Leave empty to run indefinitely') ?>
                        </p>
                    </div>
                </div>

                <!-- Modal Footer Actions -->
                <div style="display:flex; gap:12px; justify-content:flex-end; padding-top:16px; border-top:1px solid var(--border-color,#263044); margin-top:8px;">
                    <button type="button"
                            id="btnCancelBannerModal"
                            class="btn btn-outline"
                            data-i18n="form.cancel">
                        <?= __t('form.cancel', 'Cancel') ?>
                    </button>
                    <button type="submit"
                            id="btnSaveBanner"
                            class="btn btn-primary">
                        <i class="fas fa-save" aria-hidden="true"></i>
                        <span data-i18n="banners.modal.save"><?= __t('banners.modal.save', 'Save Banner') ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════
     DELETE CONFIRMATION MODAL
     ═══════════════════════════════════════════════════════════════════ -->
<div id="banner-confirm-modal"
     class="modal"
     role="alertdialog"
     aria-modal="true"
     aria-labelledby="bannerConfirmTitle"
     aria-describedby="bannerConfirmMessage"
     style="display:none; position:fixed; inset:0; z-index:10000; background:rgba(0,0,0,0.8); align-items:center; justify-content:center;">
    <div style="background:var(--card-bg,#081127); border:1px solid var(--border-color,#263044); border-radius:12px; padding:28px; width:min(420px,90vw); box-shadow:0 24px 64px rgba(0,0,0,0.6);">
        <div style="display:flex; align-items:center; gap:14px; margin-bottom:16px;">
            <div style="width:44px; height:44px; border-radius:50%; background:rgba(239,68,68,0.15); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <i class="fas fa-exclamation-triangle" style="color:#ef4444; font-size:1.1rem;" aria-hidden="true"></i>
            </div>
            <h3 id="bannerConfirmTitle"
                style="margin:0; font-size:1rem; font-weight:600; color:var(--text-primary,#fff);"
                data-i18n="banners.confirm.title">
                <?= __t('banners.confirm.title', 'Delete Banner') ?>
            </h3>
        </div>
        <p id="bannerConfirmMessage"
           style="margin:0 0 24px; color:var(--text-secondary,#94a3b8); font-size:0.9rem; line-height:1.6;"
           data-i18n="banners.confirm.message">
            <?= __t('banners.confirm.message', 'Are you sure you want to delete this banner? This action cannot be undone.') ?>
        </p>
        <input type="hidden" id="bannerDeleteId" value="">
        <div style="display:flex; gap:12px; justify-content:flex-end;">
            <button type="button"
                    id="btnCancelBannerDelete"
                    class="btn btn-outline"
                    data-i18n="form.cancel">
                <?= __t('form.cancel', 'Cancel') ?>
            </button>
            <button type="button"
                    id="btnConfirmBannerDelete"
                    class="btn btn-danger"
                    style="background:#ef4444; border-color:#ef4444; color:#fff;"
                    data-i18n="banners.confirm.delete_btn">
                <i class="fas fa-trash" aria-hidden="true"></i>
                <span><?= __t('banners.confirm.delete_btn', 'Delete') ?></span>
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

window.BANNERS_CONFIG = {
    apiUrl:       '<?= $apiBase ?>/banners',
    csrfToken:    '<?= addslashes($csrf) ?>',
    lang:         '<?= addslashes($lang) ?>',
    dir:          '<?= addslashes($dir) ?>',
    canCreate:    <?= $canCreate    ? 'true' : 'false' ?>,
    canEdit:      <?= $canEdit      ? 'true' : 'false' ?>,
    canDelete:    <?= $canDelete    ? 'true' : 'false' ?>,
    isSuperAdmin: <?= is_super_admin() ? 'true' : 'false' ?>,
    tenantId:     <?= $tenantId ?>,
    positions: ['home_hero', 'sidebar', 'category_top', 'footer']
};
</script>

<?php if ($isFragment): ?>
<script src="/toro/admin/assets/js/admin_framework.js?v=<?= time() ?>"></script>
<script src="/toro/admin/assets/js/pages/banners.js?v=<?= time() ?>"></script>
<script type="text/javascript">
(function () {
    var attempts = 0, maxAttempts = 50;
    var iv = setInterval(function () {
        attempts++;
        if (window.Banners && typeof window.Banners.init === 'function') {
            clearInterval(iv);
            try {
                var p = window.Banners.init();
                if (p && typeof p.then === 'function') {
                    p.catch(function (e) { console.error('[Banners] init error:', e); });
                }
            } catch (e) {
                console.error('[Banners] init threw:', e);
            }
        } else if (attempts >= maxAttempts) {
            clearInterval(iv);
            console.error('[Banners] Module not ready after ' + (maxAttempts * 100) + 'ms');
        }
    }, 100);
})();
</script>
<?php else: ?>
<script src="/toro/admin/assets/js/pages/banners.js?v=<?= time() ?>"></script>
<script type="text/javascript">
(function () {
    function tryInit() {
        if (window.Banners && typeof window.Banners.init === 'function') {
            window.Banners.init().catch(function (e) { console.error('[Banners] init error:', e); });
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
