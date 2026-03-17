<?php
declare(strict_types=1);

/**
 * /admin/fragments/ads.php
 * Ads Management - Production Ready
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
// AUTH
// ════════════════════════════════════════════════════════════
if (!is_admin_logged_in()) {
    if ($isFragment) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }
    header('Location: /admin/login.php');
    exit;
}

// ════════════════════════════════════════════════════════════
// USER / TENANT CONTEXT
// ════════════════════════════════════════════════════════════
$user     = admin_user();
$lang     = admin_lang();
$dir      = in_array($lang, ['ar', 'he', 'fa', 'ur']) ? 'rtl' : 'ltr';
$csrf     = admin_csrf();
$tenantId = admin_tenant_id();

// ════════════════════════════════════════════════════════════
// PERMISSIONS
// ════════════════════════════════════════════════════════════
$canManageAds = can('manage_ads') || is_super_admin();
$canCreate    = $canManageAds;
$canEdit      = $canManageAds;
$canDelete    = $canManageAds;

if (!$canManageAds) {
    http_response_code(403);
    die('Access denied');
}

// ════════════════════════════════════════════════════════════
// TRANSLATIONS
// ════════════════════════════════════════════════════════════
$_adsStrings    = [];
$_allowedLangs  = ['ar', 'en', 'fr', 'tr', 'ur', 'de', 'es', 'fa', 'he', 'hi', 'zh', 'ja', 'ko', 'pt', 'ru', 'it', 'nl', 'sv', 'pl', 'th', 'vi', 'id', 'ms'];
$_safeLang      = in_array($lang, $_allowedLangs, true) ? $lang : 'en';
$_langFile      = __DIR__ . '/../../languages/Ads/' . $_safeLang . '.json';
if (!file_exists($_langFile)) {
    $_langFile = __DIR__ . '/../../languages/Ads/en.json';
}
if (file_exists($_langFile)) {
    $_json = json_decode(file_get_contents($_langFile), true);
    if (isset($_json['strings'])) {
        $_adsStrings = $_json['strings'];
    }
}

if (!function_exists('_adst')) {
    function _adst(string $key, string $fallback = ''): string {
        global $_adsStrings;
        $keys = explode('.', $key);
        $val  = $_adsStrings;
        foreach ($keys as $k) {
            if (is_array($val) && isset($val[$k])) {
                $val = $val[$k];
            } else {
                return $fallback ?: $key;
            }
        }
        return is_string($val) ? $val : ($fallback ?: $key);
    }
}

$apiBase = '/api';
?>

<link rel="stylesheet" href="/admin/assets/css/pages/ads.css?v=<?= time() ?>">
<meta data-page="ads"
      data-i18n-files="/languages/Ads/<?= rawurlencode($lang) ?>.json">

<div class="page-container" id="adsPageContainer" dir="<?= htmlspecialchars($dir) ?>">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 data-i18n="title"><?= htmlspecialchars(_adst('title', 'Ads Management'), ENT_QUOTES, 'UTF-8') ?></h1>
            <p data-i18n="subtitle"><?= htmlspecialchars(_adst('subtitle', 'Manage advertising units linked to campaigns'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="page-header-actions">
            <?php if ($canCreate): ?>
                <button id="btnAddAd" class="btn btn-primary" data-i18n="add_ad">
                    <?= htmlspecialchars(_adst('add_ad', 'Add Ad'), ENT_QUOTES, 'UTF-8') ?>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card">
        <div class="card-body filter-bar">
            <input type="text" id="filterSearch" class="form-control"
                   placeholder="<?= htmlspecialchars(_adst('filter.search_placeholder', 'Search ads...'), ENT_QUOTES, 'UTF-8') ?>"
                   data-i18n-placeholder="filter.search_placeholder">

            <select id="filterStatus" class="form-control">
                <option value=""><?= htmlspecialchars(_adst('filter.all_statuses', 'All Statuses'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="active"><?= htmlspecialchars(_adst('status.active', 'Active'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="paused"><?= htmlspecialchars(_adst('status.paused', 'Paused'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="rejected"><?= htmlspecialchars(_adst('status.rejected', 'Rejected'), ENT_QUOTES, 'UTF-8') ?></option>
            </select>

            <select id="filterTargetType" class="form-control">
                <option value=""><?= htmlspecialchars(_adst('filter.all_target_types', 'All Target Types'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="url"><?= htmlspecialchars(_adst('target_type.url', 'URL'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="entity"><?= htmlspecialchars(_adst('target_type.entity', 'Entity'), ENT_QUOTES, 'UTF-8') ?></option>
            </select>

            <select id="filterCampaign" class="form-control">
                <option value=""><?= htmlspecialchars(_adst('filter.all_campaigns', 'All Campaigns'), ENT_QUOTES, 'UTF-8') ?></option>
            </select>

            <button id="btnFilter" class="btn btn-primary" data-i18n="filter.apply">
                <?= htmlspecialchars(_adst('filter.apply', 'Filter'), ENT_QUOTES, 'UTF-8') ?>
            </button>
            <button id="btnClearFilters" class="btn btn-secondary" data-i18n="filter.clear">
                <?= htmlspecialchars(_adst('filter.clear', 'Clear Filters'), ENT_QUOTES, 'UTF-8') ?>
            </button>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card">
        <div class="card-body" style="padding:0;">
            <table class="data-table" id="adsTable">
                <thead>
                    <tr>
                        <th data-i18n="table.id"><?= htmlspecialchars(_adst('table.id', 'ID'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th data-i18n="table.campaign"><?= htmlspecialchars(_adst('table.campaign', 'Campaign'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th data-i18n="table.target_type"><?= htmlspecialchars(_adst('table.target_type', 'Target Type'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th data-i18n="table.target_value"><?= htmlspecialchars(_adst('table.target_value', 'Target Value'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th data-i18n="table.status"><?= htmlspecialchars(_adst('table.status', 'Status'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th data-i18n="table.views"><?= htmlspecialchars(_adst('table.views', 'Views'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th data-i18n="table.clicks"><?= htmlspecialchars(_adst('table.clicks', 'Clicks'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th data-i18n="table.created_at"><?= htmlspecialchars(_adst('table.created_at', 'Created At'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th data-i18n="table.actions"><?= htmlspecialchars(_adst('table.actions', 'Actions'), ENT_QUOTES, 'UTF-8') ?></th>
                    </tr>
                </thead>
                <tbody id="adsTableBody">
                    <tr>
                        <td colspan="9" class="text-center">
                            <?= htmlspecialchars(_adst('table.no_records', 'No ads found'), ENT_QUOTES, 'UTF-8') ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="pagination-wrapper">
            <div class="pagination-info">
                <span data-i18n="pagination.showing"><?= htmlspecialchars(_adst('pagination.showing', 'Showing'), ENT_QUOTES, 'UTF-8') ?></span>
                <span id="adsPaginationInfo">0-0 <?= htmlspecialchars(_adst('pagination.of', 'of'), ENT_QUOTES, 'UTF-8') ?> 0</span>
            </div>
            <div class="pagination" id="adsPagination"></div>
        </div>
    </div>

    <!-- Add / Edit Modal -->
    <div id="adModal" class="modal" style="display:none;">
        <div class="modal-content">
            <h3 id="adModalTitle" data-i18n="modal.add_title">
                <?= htmlspecialchars(_adst('modal.add_title', 'Add Ad'), ENT_QUOTES, 'UTF-8') ?>
            </h3>
            <form id="adForm" onsubmit="return false;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" id="adId" name="id" value="">

                <!-- Campaign -->
                <div class="form-group">
                    <label for="adCampaignId" data-i18n="form.campaign_id">
                        <?= htmlspecialchars(_adst('form.campaign_id', 'Campaign'), ENT_QUOTES, 'UTF-8') ?> *
                    </label>
                    <select id="adCampaignId" name="campaign_id" class="form-control" required>
                        <option value="">
                            <?= htmlspecialchars(_adst('form.select_campaign', '-- Select Campaign --'), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    </select>
                </div>

                <!-- Target Type -->
                <div class="form-group">
                    <label for="adTargetType" data-i18n="form.target_type">
                        <?= htmlspecialchars(_adst('form.target_type', 'Target Type'), ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <select id="adTargetType" name="target_type" class="form-control">
                        <option value="url"><?= htmlspecialchars(_adst('target_type.url', 'URL'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="entity"><?= htmlspecialchars(_adst('target_type.entity', 'Entity'), ENT_QUOTES, 'UTF-8') ?></option>
                    </select>
                </div>

                <!-- Target Value -->
                <div class="form-group">
                    <label for="adTargetValue" data-i18n="form.target_value">
                        <?= htmlspecialchars(_adst('form.target_value', 'Target Value'), ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <input type="text" id="adTargetValue" name="target_value" class="form-control"
                           placeholder="<?= htmlspecialchars(_adst('form.target_value_placeholder_url', 'https://example.com'), ENT_QUOTES, 'UTF-8') ?>"
                           maxlength="500">
                </div>

                <!-- Status -->
                <div class="form-group">
                    <label for="adStatus" data-i18n="form.status">
                        <?= htmlspecialchars(_adst('form.status', 'Status'), ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <select id="adStatus" name="status" class="form-control">
                        <option value="active"><?= htmlspecialchars(_adst('status.active', 'Active'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="paused"><?= htmlspecialchars(_adst('status.paused', 'Paused'), ENT_QUOTES, 'UTF-8') ?></option>
                        <option value="rejected"><?= htmlspecialchars(_adst('status.rejected', 'Rejected'), ENT_QUOTES, 'UTF-8') ?></option>
                    </select>
                </div>

                <!-- Views Count (admin only) -->
                <?php if (is_super_admin()): ?>
                <div class="form-group">
                    <label for="adViewsCount" data-i18n="form.views_count">
                        <?= htmlspecialchars(_adst('form.views_count', 'Views Count'), ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <input type="number" id="adViewsCount" name="views_count" class="form-control" value="0" min="0">
                </div>

                <!-- Clicks Count (admin only) -->
                <div class="form-group">
                    <label for="adClicksCount" data-i18n="form.clicks_count">
                        <?= htmlspecialchars(_adst('form.clicks_count', 'Clicks Count'), ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <input type="number" id="adClicksCount" name="clicks_count" class="form-control" value="0" min="0">
                </div>
                <?php else: ?>
                <input type="hidden" id="adViewsCount"  name="views_count"  value="0">
                <input type="hidden" id="adClicksCount" name="clicks_count" value="0">
                <?php endif; ?>

                <div class="form-actions">
                    <button type="button" id="adSaveBtn" class="btn btn-primary" data-i18n="form.save">
                        <?= htmlspecialchars(_adst('form.save', 'Save'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                    <button type="button" class="btn btn-secondary btn-close-ads-modal"
                            data-modal="adModal" data-i18n="form.cancel">
                        <?= htmlspecialchars(_adst('form.cancel', 'Cancel'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
window.ADS_CONFIG = {
    apiBase:   <?= json_encode($apiBase) ?>,
    csrfToken: <?= json_encode($csrf) ?>,
    tenantId:  <?= (int)$tenantId ?>,
    lang:      <?= json_encode($_safeLang) ?>,
    dir:       <?= json_encode($dir) ?>,
    strings:   <?= json_encode($_adsStrings, JSON_UNESCAPED_UNICODE) ?>,
    canCreate: <?= json_encode($canCreate) ?>,
    canEdit:   <?= json_encode($canEdit) ?>,
    canDelete: <?= json_encode($canDelete) ?>
};
</script>
<script src="/admin/assets/js/pages/ads.js?v=<?= time() ?>"></script>

<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>
