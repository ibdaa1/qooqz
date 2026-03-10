<?php
declare(strict_types=1);

/**
 * /admin/fragments/attributes.php
 * Production Version — Attribute Groups & Values Management
 *
 * ✅ Fragment / standalone dual-mode
 * ✅ Role-based + resource-based permission system
 * ✅ Multi-language / RTL support
 * ✅ Two-level CRUD: attribute groups (with type) + expandable attribute values
 * ✅ Color hex field shown/hidden based on group type
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
$canViewAll   = can_view_all('attributes');
$canViewOwn   = can_view_own('attributes');
$canCreate    = can_create('attributes');
$canEditAll   = can_edit_all('attributes');
$canEditOwn   = can_edit_own('attributes');
$canDeleteAll = can_delete_all('attributes');
$canDeleteOwn = can_delete_own('attributes');

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
        die('Access denied: You do not have permission to view attributes.');
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
<style id="db-theme-vars-attributes">
<?php renderFragmentThemeVars($GLOBALS['ADMIN_UI']['theme'] ?? []); ?>
<?php if (!empty($GLOBALS['ADMIN_UI']['theme']['generated_css'])): ?>
<?= $GLOBALS['ADMIN_UI']['theme']['generated_css'] ?>
<?php endif; ?>
</style>
<link rel="stylesheet" href="/toro/admin/assets/css/pages/attributes.css?v=<?= time() ?>">

<!-- Toast Container -->
<div id="toastContainer" class="toast-container" aria-live="polite" aria-atomic="true"
     style="position:fixed; bottom:24px; <?= $dir === 'rtl' ? 'left' : 'right' ?>:24px; z-index:99999; display:flex; flex-direction:column; gap:10px; pointer-events:none;"></div>

<!-- ═══════════════════════════════════════════════════════════════════
     PAGE CONTAINER
     ═══════════════════════════════════════════════════════════════════ -->
<div class="page-container"
     id="attributes-page"
     dir="<?= htmlspecialchars($dir) ?>"
     data-lang="<?= htmlspecialchars($lang) ?>"
     data-dir="<?= htmlspecialchars($dir) ?>"
     data-csrf="<?= htmlspecialchars($csrf) ?>"
     data-tenant="<?= $tenantId ?>">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title" data-i18n="attributes.title"><?= __t('attributes.title', 'Attributes') ?></h1>
            <p class="page-subtitle" data-i18n="attributes.subtitle"><?= __t('attributes.subtitle', 'Manage attribute groups and their values') ?></p>
        </div>
        <div class="page-header-actions">
            <?php if ($canCreate): ?>
            <button id="btnAddGroup" class="btn btn-primary" aria-label="<?= htmlspecialchars(__t('attributes.add_group', 'Add Group')) ?>">
                <i class="fas fa-plus" aria-hidden="true"></i>
                <span data-i18n="attributes.add_group"><?= __t('attributes.add_group', 'Add Group') ?></span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card filter-card" role="search" aria-label="<?= htmlspecialchars(__t('filters.label', 'Filter attribute groups')) ?>">
        <div class="card-body">
            <div class="filter-row">
                <div class="filter-group filter-group--search">
                    <label for="filter-search" class="sr-only"><?= __t('filters.search', 'Search') ?></label>
                    <div class="input-icon-wrapper">
                        <i class="fas fa-search input-icon" aria-hidden="true"></i>
                        <input type="search"
                               id="filter-search"
                               class="form-control form-control--icon"
                               data-i18n-placeholder="attributes.filter.search_placeholder"
                               placeholder="<?= htmlspecialchars(__t('attributes.filter.search_placeholder', 'Search attribute groups…')) ?>"
                               autocomplete="off">
                    </div>
                </div>

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
            <div class="table-responsive" role="region" aria-label="<?= htmlspecialchars(__t('attributes.table.label', 'Attribute groups table')) ?>">
                <table class="data-table attributes-table" aria-label="<?= htmlspecialchars(__t('attributes.title', 'Attributes')) ?>">
                    <thead>
                        <tr>
                            <th scope="col" class="col-expand" aria-label="<?= htmlspecialchars(__t('attributes.table.expand', 'Expand')) ?>"></th>
                            <th scope="col" class="col-id"     data-i18n="table.id"><?= __t('table.id', 'ID') ?></th>
                            <th scope="col" class="col-name"   data-i18n="attributes.table.name"><?= __t('attributes.table.name', 'Name') ?></th>
                            <th scope="col" class="col-type"   data-i18n="attributes.table.type"><?= __t('attributes.table.type', 'Type') ?></th>
                            <th scope="col" class="col-slug"   data-i18n="attributes.table.slug"><?= __t('attributes.table.slug', 'Slug') ?></th>
                            <th scope="col" class="col-sort"   data-i18n="table.sort"><?= __t('table.sort', 'Sort') ?></th>
                            <th scope="col" class="col-actions" data-i18n="table.actions"><?= __t('table.actions', 'Actions') ?></th>
                        </tr>
                    </thead>
                    <tbody id="attributes-tbody" aria-live="polite" aria-relevant="additions removals">
                        <tr class="loading-row">
                            <td colspan="7" style="text-align:center; padding:40px; color:var(--text-secondary,#94a3b8);">
                                <i class="fas fa-spinner fa-spin" aria-hidden="true"></i>
                                <span style="margin-<?= $dir === 'rtl' ? 'right' : 'left' ?>:10px;"
                                      data-i18n="table.loading"><?= __t('table.loading', 'Loading…') ?></span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!--
                 JS-rendered expandable sub-rows for attribute values are injected into
                 #attributes-tbody by AttributesModule as <tr class="values-row"> elements.
                 Each sub-row contains a nested table with columns:
                 Value ID | Label | Value | Color | Sort | Actions (edit / delete value)
                 and an "Add Value" button rendered at the bottom of the sub-row.
            -->
        </div>
    </div>

</div><!-- /#attributes-page -->

<!-- ═══════════════════════════════════════════════════════════════════
     GROUP CREATE / EDIT MODAL
     ═══════════════════════════════════════════════════════════════════ -->
<div id="group-modal"
     class="modal"
     role="dialog"
     aria-modal="true"
     aria-labelledby="groupModalTitle"
     style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.72); align-items:center; justify-content:center;">
    <div class="modal-content"
         style="background:var(--card-bg,#081127); border:1px solid var(--border-color,#263044); border-radius:12px; padding:0; width:min(520px,95vw); max-height:90vh; overflow-y:auto; position:relative; box-shadow:0 24px 64px rgba(0,0,0,0.5);">

        <!-- Modal Header -->
        <div style="display:flex; align-items:center; justify-content:space-between; padding:20px 24px 16px; border-bottom:1px solid var(--border-color,#263044); position:sticky; top:0; background:var(--card-bg,#081127); z-index:1;">
            <h3 id="groupModalTitle"
                style="margin:0; font-size:1.1rem; font-weight:600; color:var(--text-primary,#fff);"
                data-i18n="attributes.group_modal.add_title">
                <?= __t('attributes.group_modal.add_title', 'Add Attribute Group') ?>
            </h3>
            <button type="button"
                    id="btnCloseGroupModal"
                    aria-label="<?= htmlspecialchars(__t('accessibility.close', 'Close')) ?>"
                    style="background:none; border:none; color:var(--text-secondary,#94a3b8); font-size:1.4rem; cursor:pointer; padding:4px; line-height:1;">
                &times;
            </button>
        </div>

        <!-- Modal Body -->
        <div style="padding:24px;">
            <form id="group-form" novalidate autocomplete="off">
                <input type="hidden" id="groupId"       name="id">
                <input type="hidden" name="csrf_token"  value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="tenant_id"   value="<?= $tenantId ?>">

                <!-- Arabic Name -->
                <div class="form-group" style="margin-bottom:18px;">
                    <label for="groupNameAr"
                           style="display:block; margin-bottom:6px; font-size:0.875rem; color:var(--text-secondary,#94a3b8);"
                           data-i18n="attributes.group_modal.name_ar">
                        <?= __t('attributes.group_modal.name_ar', 'Arabic Name') ?>
                    </label>
                    <input type="text"
                           id="groupNameAr"
                           name="name_ar"
                           class="form-control"
                           dir="rtl"
                           data-i18n-placeholder="attributes.group_modal.name_ar_placeholder"
                           placeholder="<?= htmlspecialchars(__t('attributes.group_modal.name_ar_placeholder', 'اسم المجموعة')) ?>"
                           style="background:var(--input-bg,#061021); border:1px solid var(--border-color,#263044); border-radius:8px; padding:10px 14px; color:var(--text-primary,#fff); width:100%; box-sizing:border-box;">
                </div>

                <!-- English Name -->
                <div class="form-group" style="margin-bottom:18px;">
                    <label for="groupNameEn"
                           style="display:block; margin-bottom:6px; font-size:0.875rem; color:var(--text-secondary,#94a3b8);"
                           data-i18n="attributes.group_modal.name_en">
                        <?= __t('attributes.group_modal.name_en', 'English Name') ?> <span style="color:#f87171;">*</span>
                    </label>
                    <input type="text"
                           id="groupNameEn"
                           name="name_en"
                           class="form-control"
                           required
                           dir="ltr"
                           data-i18n-placeholder="attributes.group_modal.name_en_placeholder"
                           placeholder="<?= htmlspecialchars(__t('attributes.group_modal.name_en_placeholder', 'e.g. Color, Size, Material')) ?>"
                           style="background:var(--input-bg,#061021); border:1px solid var(--border-color,#263044); border-radius:8px; padding:10px 14px; color:var(--text-primary,#fff); width:100%; box-sizing:border-box;">
                    <div class="invalid-feedback" style="color:#f87171; font-size:0.8rem; margin-top:4px; display:none;"
                         data-i18n="validation.required"><?= __t('validation.required', 'This field is required.') ?></div>
                </div>

                <!-- Slug -->
                <div class="form-group" style="margin-bottom:18px;">
                    <label for="groupSlug"
                           style="display:block; margin-bottom:6px; font-size:0.875rem; color:var(--text-secondary,#94a3b8);"
                           data-i18n="attributes.group_modal.slug">
                        <?= __t('attributes.group_modal.slug', 'Slug') ?>
                    </label>
                    <input type="text"
                           id="groupSlug"
                           name="slug"
                           class="form-control"
                           dir="ltr"
                           data-i18n-placeholder="attributes.group_modal.slug_placeholder"
                           placeholder="<?= htmlspecialchars(__t('attributes.group_modal.slug_placeholder', 'attribute-group-slug (auto-generated)')) ?>"
                           style="background:var(--input-bg,#061021); border:1px solid var(--border-color,#263044); border-radius:8px; padding:10px 14px; color:var(--text-primary,#fff); width:100%; box-sizing:border-box;">
                </div>

                <!-- Type -->
                <div class="form-group" style="margin-bottom:18px;">
                    <label for="groupType"
                           style="display:block; margin-bottom:6px; font-size:0.875rem; color:var(--text-secondary,#94a3b8);"
                           data-i18n="attributes.group_modal.type">
                        <?= __t('attributes.group_modal.type', 'Type') ?> <span style="color:#f87171;">*</span>
                    </label>
                    <select id="groupType"
                            name="type"
                            class="form-control"
                            required
                            aria-label="<?= htmlspecialchars(__t('attributes.group_modal.type', 'Type')) ?>"
                            style="background:var(--input-bg,#061021); border:1px solid var(--border-color,#263044); border-radius:8px; padding:10px 14px; color:var(--text-primary,#fff); width:100%; box-sizing:border-box;">
                        <option value="select"   data-i18n="attributes.type.select"><?= __t('attributes.type.select', 'Select (Dropdown)') ?></option>
                        <option value="checkbox" data-i18n="attributes.type.checkbox"><?= __t('attributes.type.checkbox', 'Checkbox (Multi-select)') ?></option>
                        <option value="radio"    data-i18n="attributes.type.radio"><?= __t('attributes.type.radio', 'Radio (Single choice)') ?></option>
                        <option value="color"    data-i18n="attributes.type.color"><?= __t('attributes.type.color', 'Color Swatch') ?></option>
                        <option value="size"     data-i18n="attributes.type.size"><?= __t('attributes.type.size', 'Size') ?></option>
                    </select>
                </div>

                <!-- Sort Order -->
                <div class="form-group" style="margin-bottom:18px;">
                    <label for="groupSortOrder"
                           style="display:block; margin-bottom:6px; font-size:0.875rem; color:var(--text-secondary,#94a3b8);"
                           data-i18n="attributes.group_modal.sort_order">
                        <?= __t('attributes.group_modal.sort_order', 'Sort Order') ?>
                    </label>
                    <input type="number"
                           id="groupSortOrder"
                           name="sort_order"
                           class="form-control"
                           value="0"
                           min="0"
                           style="background:var(--input-bg,#061021); border:1px solid var(--border-color,#263044); border-radius:8px; padding:10px 14px; color:var(--text-primary,#fff); width:100%; box-sizing:border-box;">
                </div>

                <!-- Modal Footer Actions -->
                <div style="display:flex; gap:12px; justify-content:flex-end; padding-top:16px; border-top:1px solid var(--border-color,#263044); margin-top:8px;">
                    <button type="button"
                            id="btnCancelGroupModal"
                            class="btn btn-outline"
                            data-i18n="form.cancel">
                        <?= __t('form.cancel', 'Cancel') ?>
                    </button>
                    <button type="submit"
                            id="btnSaveGroup"
                            class="btn btn-primary">
                        <i class="fas fa-save" aria-hidden="true"></i>
                        <span data-i18n="attributes.group_modal.save"><?= __t('attributes.group_modal.save', 'Save Group') ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════
     VALUE CREATE / EDIT MODAL
     ═══════════════════════════════════════════════════════════════════ -->
<div id="value-modal"
     class="modal"
     role="dialog"
     aria-modal="true"
     aria-labelledby="valueModalTitle"
     style="display:none; position:fixed; inset:0; z-index:10000; background:rgba(0,0,0,0.78); align-items:center; justify-content:center;">
    <div class="modal-content"
         style="background:var(--card-bg,#081127); border:1px solid var(--border-color,#263044); border-radius:12px; padding:0; width:min(480px,95vw); max-height:90vh; overflow-y:auto; position:relative; box-shadow:0 24px 64px rgba(0,0,0,0.6);">

        <!-- Modal Header -->
        <div style="display:flex; align-items:center; justify-content:space-between; padding:20px 24px 16px; border-bottom:1px solid var(--border-color,#263044); position:sticky; top:0; background:var(--card-bg,#081127); z-index:1;">
            <h3 id="valueModalTitle"
                style="margin:0; font-size:1.1rem; font-weight:600; color:var(--text-primary,#fff);"
                data-i18n="attributes.value_modal.add_title">
                <?= __t('attributes.value_modal.add_title', 'Add Attribute Value') ?>
            </h3>
            <button type="button"
                    id="btnCloseValueModal"
                    aria-label="<?= htmlspecialchars(__t('accessibility.close', 'Close')) ?>"
                    style="background:none; border:none; color:var(--text-secondary,#94a3b8); font-size:1.4rem; cursor:pointer; padding:4px; line-height:1;">
                &times;
            </button>
        </div>

        <!-- Modal Body -->
        <div style="padding:24px;">
            <form id="value-form" novalidate autocomplete="off">
                <input type="hidden" id="valueId"       name="id">
                <input type="hidden" id="valueGroupId"  name="group_id">
                <input type="hidden" id="valueGroupType" name="group_type">
                <input type="hidden" name="csrf_token"  value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="tenant_id"   value="<?= $tenantId ?>">

                <!-- Context: group name (read-only display) -->
                <div id="valueGroupContext"
                     style="margin-bottom:18px; padding:10px 14px; background:var(--background-secondary,#0f1724); border-radius:8px; border:1px solid var(--border-color,#263044); display:none;">
                    <span style="font-size:0.8rem; color:var(--text-secondary,#94a3b8);"
                          data-i18n="attributes.value_modal.group_label"><?= __t('attributes.value_modal.group_label', 'Group:') ?></span>
                    <strong id="valueGroupName" style="color:var(--text-primary,#fff); margin-<?= $dir === 'rtl' ? 'right' : 'left' ?>:6px;"></strong>
                    <span id="valueGroupTypeBadge"
                          style="display:inline-block; margin-<?= $dir === 'rtl' ? 'right' : 'left' ?>:8px; padding:2px 8px; border-radius:20px; font-size:0.72rem; font-weight:600; background:rgba(59,130,246,0.15); color:#3b82f6;"></span>
                </div>

                <!-- Arabic Label -->
                <div class="form-group" style="margin-bottom:18px;">
                    <label for="valueLabelAr"
                           style="display:block; margin-bottom:6px; font-size:0.875rem; color:var(--text-secondary,#94a3b8);"
                           data-i18n="attributes.value_modal.label_ar">
                        <?= __t('attributes.value_modal.label_ar', 'Arabic Label') ?>
                    </label>
                    <input type="text"
                           id="valueLabelAr"
                           name="label_ar"
                           class="form-control"
                           dir="rtl"
                           data-i18n-placeholder="attributes.value_modal.label_ar_placeholder"
                           placeholder="<?= htmlspecialchars(__t('attributes.value_modal.label_ar_placeholder', 'التسمية بالعربية')) ?>"
                           style="background:var(--input-bg,#061021); border:1px solid var(--border-color,#263044); border-radius:8px; padding:10px 14px; color:var(--text-primary,#fff); width:100%; box-sizing:border-box;">
                </div>

                <!-- English Label -->
                <div class="form-group" style="margin-bottom:18px;">
                    <label for="valueLabelEn"
                           style="display:block; margin-bottom:6px; font-size:0.875rem; color:var(--text-secondary,#94a3b8);"
                           data-i18n="attributes.value_modal.label_en">
                        <?= __t('attributes.value_modal.label_en', 'English Label') ?> <span style="color:#f87171;">*</span>
                    </label>
                    <input type="text"
                           id="valueLabelEn"
                           name="label_en"
                           class="form-control"
                           required
                           dir="ltr"
                           data-i18n-placeholder="attributes.value_modal.label_en_placeholder"
                           placeholder="<?= htmlspecialchars(__t('attributes.value_modal.label_en_placeholder', 'e.g. Red, XL, Cotton')) ?>"
                           style="background:var(--input-bg,#061021); border:1px solid var(--border-color,#263044); border-radius:8px; padding:10px 14px; color:var(--text-primary,#fff); width:100%; box-sizing:border-box;">
                    <div class="invalid-feedback" style="color:#f87171; font-size:0.8rem; margin-top:4px; display:none;"
                         data-i18n="validation.required"><?= __t('validation.required', 'This field is required.') ?></div>
                </div>

                <!-- Value (slug / key) -->
                <div class="form-group" style="margin-bottom:18px;">
                    <label for="valueValue"
                           style="display:block; margin-bottom:6px; font-size:0.875rem; color:var(--text-secondary,#94a3b8);"
                           data-i18n="attributes.value_modal.value">
                        <?= __t('attributes.value_modal.value', 'Value (key)') ?>
                    </label>
                    <input type="text"
                           id="valueValue"
                           name="value"
                           class="form-control"
                           dir="ltr"
                           data-i18n-placeholder="attributes.value_modal.value_placeholder"
                           placeholder="<?= htmlspecialchars(__t('attributes.value_modal.value_placeholder', 'red, xl, cotton (auto-generated)')) ?>"
                           style="background:var(--input-bg,#061021); border:1px solid var(--border-color,#263044); border-radius:8px; padding:10px 14px; color:var(--text-primary,#fff); width:100%; box-sizing:border-box;">
                </div>

                <!-- Color Hex (shown only when group type = color) -->
                <div id="colorHexWrapper" class="form-group" style="margin-bottom:18px; display:none;">
                    <label for="valueColorHex"
                           style="display:block; margin-bottom:6px; font-size:0.875rem; color:var(--text-secondary,#94a3b8);"
                           data-i18n="attributes.value_modal.color_hex">
                        <?= __t('attributes.value_modal.color_hex', 'Color (hex)') ?>
                    </label>
                    <div style="display:flex; align-items:center; gap:12px;">
                        <input type="color"
                               id="valueColorHexPicker"
                               style="width:44px; height:40px; border:none; background:none; cursor:pointer; padding:0;"
                               aria-label="<?= htmlspecialchars(__t('attributes.value_modal.color_picker', 'Pick color')) ?>">
                        <input type="text"
                               id="valueColorHex"
                               name="color_hex"
                               class="form-control"
                               dir="ltr"
                               maxlength="7"
                               data-i18n-placeholder="attributes.value_modal.color_hex_placeholder"
                               placeholder="<?= htmlspecialchars(__t('attributes.value_modal.color_hex_placeholder', '#FF5733')) ?>"
                               style="background:var(--input-bg,#061021); border:1px solid var(--border-color,#263044); border-radius:8px; padding:10px 14px; color:var(--text-primary,#fff); flex:1; box-sizing:border-box;">
                    </div>
                    <p style="margin:6px 0 0; font-size:0.78rem; color:var(--text-secondary,#94a3b8);"
                       data-i18n="attributes.value_modal.color_hex_hint">
                        <?= __t('attributes.value_modal.color_hex_hint', 'Enter a hex color value, e.g. #FF5733') ?>
                    </p>
                </div>

                <!-- Sort Order -->
                <div class="form-group" style="margin-bottom:18px;">
                    <label for="valueSortOrder"
                           style="display:block; margin-bottom:6px; font-size:0.875rem; color:var(--text-secondary,#94a3b8);"
                           data-i18n="attributes.value_modal.sort_order">
                        <?= __t('attributes.value_modal.sort_order', 'Sort Order') ?>
                    </label>
                    <input type="number"
                           id="valueSortOrder"
                           name="sort_order"
                           class="form-control"
                           value="0"
                           min="0"
                           style="background:var(--input-bg,#061021); border:1px solid var(--border-color,#263044); border-radius:8px; padding:10px 14px; color:var(--text-primary,#fff); width:100%; box-sizing:border-box;">
                </div>

                <!-- Modal Footer Actions -->
                <div style="display:flex; gap:12px; justify-content:flex-end; padding-top:16px; border-top:1px solid var(--border-color,#263044); margin-top:8px;">
                    <button type="button"
                            id="btnCancelValueModal"
                            class="btn btn-outline"
                            data-i18n="form.cancel">
                        <?= __t('form.cancel', 'Cancel') ?>
                    </button>
                    <button type="submit"
                            id="btnSaveValue"
                            class="btn btn-primary">
                        <i class="fas fa-save" aria-hidden="true"></i>
                        <span data-i18n="attributes.value_modal.save"><?= __t('attributes.value_modal.save', 'Save Value') ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════
     DELETE CONFIRMATION MODAL (shared for groups and values)
     ═══════════════════════════════════════════════════════════════════ -->
<div id="attr-confirm-modal"
     class="modal"
     role="alertdialog"
     aria-modal="true"
     aria-labelledby="attrConfirmTitle"
     aria-describedby="attrConfirmMessage"
     style="display:none; position:fixed; inset:0; z-index:10001; background:rgba(0,0,0,0.82); align-items:center; justify-content:center;">
    <div style="background:var(--card-bg,#081127); border:1px solid var(--border-color,#263044); border-radius:12px; padding:28px; width:min(420px,90vw); box-shadow:0 24px 64px rgba(0,0,0,0.6);">
        <div style="display:flex; align-items:center; gap:14px; margin-bottom:16px;">
            <div style="width:44px; height:44px; border-radius:50%; background:rgba(239,68,68,0.15); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <i class="fas fa-exclamation-triangle" style="color:#ef4444; font-size:1.1rem;" aria-hidden="true"></i>
            </div>
            <h3 id="attrConfirmTitle"
                style="margin:0; font-size:1rem; font-weight:600; color:var(--text-primary,#fff);"
                data-i18n="attributes.confirm.title">
                <?= __t('attributes.confirm.title', 'Confirm Delete') ?>
            </h3>
        </div>
        <p id="attrConfirmMessage"
           style="margin:0 0 24px; color:var(--text-secondary,#94a3b8); font-size:0.9rem; line-height:1.6;"
           data-i18n="attributes.confirm.message">
            <?= __t('attributes.confirm.message', 'Are you sure you want to delete this item? This action cannot be undone.') ?>
        </p>
        <!-- JS sets these hidden fields before showing the modal -->
        <input type="hidden" id="attrDeleteId"   value="">
        <input type="hidden" id="attrDeleteType" value=""><!-- 'group' or 'value' -->
        <div style="display:flex; gap:12px; justify-content:flex-end;">
            <button type="button"
                    id="btnCancelAttrDelete"
                    class="btn btn-outline"
                    data-i18n="form.cancel">
                <?= __t('form.cancel', 'Cancel') ?>
            </button>
            <button type="button"
                    id="btnConfirmAttrDelete"
                    class="btn btn-danger"
                    style="background:#ef4444; border-color:#ef4444; color:#fff;"
                    data-i18n="attributes.confirm.delete_btn">
                <i class="fas fa-trash" aria-hidden="true"></i>
                <span><?= __t('attributes.confirm.delete_btn', 'Delete') ?></span>
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

window.ATTRIBUTES_CONFIG = {
    apiUrl:       '<?= $apiBase ?>/attribute_groups',
    valuesApiUrl: '<?= $apiBase ?>/attribute_values',
    csrfToken:    '<?= addslashes($csrf) ?>',
    lang:         '<?= addslashes($lang) ?>',
    dir:          '<?= addslashes($dir) ?>',
    canCreate:    <?= $canCreate    ? 'true' : 'false' ?>,
    canEdit:      <?= $canEdit      ? 'true' : 'false' ?>,
    canDelete:    <?= $canDelete    ? 'true' : 'false' ?>,
    isSuperAdmin: <?= is_super_admin() ? 'true' : 'false' ?>,
    tenantId:     <?= $tenantId ?>
};
</script>

<?php if ($isFragment): ?>
<script src="/toro/admin/assets/js/admin_framework.js?v=<?= time() ?>"></script>
<script src="/toro/admin/assets/js/pages/attributes.js?v=<?= time() ?>"></script>
<script type="text/javascript">
(function () {
    var attempts = 0, maxAttempts = 50;
    var iv = setInterval(function () {
        attempts++;
        if (window.Attributes && typeof window.Attributes.init === 'function') {
            clearInterval(iv);
            try {
                var p = window.Attributes.init();
                if (p && typeof p.then === 'function') {
                    p.catch(function (e) { console.error('[Attributes] init error:', e); });
                }
            } catch (e) {
                console.error('[Attributes] init threw:', e);
            }
        } else if (attempts >= maxAttempts) {
            clearInterval(iv);
            console.error('[Attributes] Module not ready after ' + (maxAttempts * 100) + 'ms');
        }
    }, 100);
})();
</script>
<?php else: ?>
<script src="/toro/admin/assets/js/pages/attributes.js?v=<?= time() ?>"></script>
<script type="text/javascript">
(function () {
    function tryInit() {
        if (window.Attributes && typeof window.Attributes.init === 'function') {
            window.Attributes.init().catch(function (e) { console.error('[Attributes] init error:', e); });
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
