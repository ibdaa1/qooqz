<?php
declare(strict_types=1);

/**
 * /admin/fragments/pos.php
 * POS Cashier System – Tenant Employee Interface
 * Allows cashiers to: select products, manage order items, process payments,
 * and manage cashier sessions linked to pos_sessions table.
 */

// ════════════════════════════════════════════════════════════
// DETECT REQUEST TYPE
// ════════════════════════════════════════════════════════════
$isAjax     = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
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
    }
    header('Location: /admin/login.php');
    exit;
}

$user     = admin_user();
$lang     = admin_lang();
$dir      = admin_dir();
$csrf     = admin_csrf();
$tenantId = admin_tenant_id();

$isSuperAdmin = is_super_admin();
// Allow cashiers, store managers, and admins
$canAccess = $isSuperAdmin || can('pos.access') || can('pos.cashier') || can('manage_pos');
// Fallback: any logged-in tenant user can access POS
if (!$canAccess) {
    $canAccess = !empty($tenantId);
}

if (!$canAccess) {
    if ($isFragment) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }
    http_response_code(403);
    die('Access denied');
}

// ════════════════════════════════════════════════════════════
// TRANSLATION HELPER
// ════════════════════════════════════════════════════════════
if (!function_exists('__pos_t')) {
    function __pos_t(string $key, string $fallback = ''): string {
        if (function_exists('i18n_get')) {
            $v = i18n_get($key);
            if ($v !== null) return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        }
        return htmlspecialchars($fallback ?: $key, ENT_QUOTES, 'UTF-8');
    }
}

// Get current user's entity_id if set
$userEntityId = $user['entity_id'] ?? null;

// ════════════════════════════════════════════════════════════
// THEME VARS INJECTION (DB-driven CSS variables)
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
            if (!empty($b['text_color']))       echo "    --btn-{$slug}-color: " . htmlspecialchars($b['text_color'], ENT_QUOTES) . ';' . PHP_EOL;
            if (!empty($b['border_color']))     echo "    --btn-{$slug}-border: " . htmlspecialchars($b['border_color'], ENT_QUOTES) . ';' . PHP_EOL;
            if (!empty($b['border_radius']))    echo "    --btn-{$slug}-radius: " . htmlspecialchars((string)$b['border_radius'], ENT_QUOTES) . 'px;' . PHP_EOL;
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
<style id="db-theme-vars-pos">
<?php renderFragmentThemeVars($GLOBALS['ADMIN_UI']['theme'] ?? []); ?>
<?php if (!empty($GLOBALS['ADMIN_UI']['theme']['generated_css'])): ?>
<?= $GLOBALS['ADMIN_UI']['theme']['generated_css'] ?>
<?php endif; ?>
</style>

<?php if ($isFragment): ?>
<link rel="stylesheet" href="/admin/assets/css/pages/pos.css">
<?php endif; ?>

<!-- Page Meta (fragment loader uses these) -->
<meta data-page="pos"
      data-assets-css="/admin/assets/css/pages/pos.css"
      data-i18n-files="/languages/POS/<?= rawurlencode($lang) ?>.json">

<!-- POS Config -->
<script>
window.POS_CONFIG = {
    TENANT_ID:      <?= (int)$tenantId ?>,
    ENTITY_ID:      <?= $userEntityId ? (int)$userEntityId : 'null' ?>,
    USER_ID:        <?= (int)($user['id'] ?? 0) ?>,
    IS_SUPER_ADMIN: <?= $isSuperAdmin ? 'true' : 'false' ?>,
    LANG:           '<?= htmlspecialchars($lang, ENT_QUOTES) ?>',
    DIR:            '<?= htmlspecialchars($dir, ENT_QUOTES) ?>',
    CSRF:           '<?= htmlspecialchars($csrf, ENT_QUOTES) ?>',
    CURRENCY:       'SAR',
};
</script>

<!-- ══════════════════════════════════════
     POS PAGE CONTAINER
     ══════════════════════════════════════ -->
<div class="pos-page" id="posPageContainer" dir="<?= htmlspecialchars($dir) ?>">

    <!-- Alerts -->
    <div id="posAlerts" style="padding:0 16px;padding-top:8px"></div>

    <!-- ── Session Status Bar ── -->
    <div class="pos-session-bar" id="posSessionBar">
        <span style="color:var(--text-muted,#64748b)"><?= __pos_t('common.loading', 'Loading...') ?></span>
    </div>

    <!-- ════════════════════════════════════
         OPEN SESSION VIEW
         (shown when no active session)
         ════════════════════════════════════ -->
    <div class="pos-open-session" id="posOpenSession" style="display:none">
        <div class="pos-open-session-card">
            <span class="pos-icon">🏧</span>
            <h2><?= __pos_t('pos.session.open_title', 'Open Cashier Session') ?></h2>
            <p><?= __pos_t('pos.session.open_subtitle', 'Start the workday by opening a new session') ?></p>

            <form id="posOpenSessionForm" style="text-align:right">
                <!-- Entity / Branch (shown for super admin or users without a preset entity) -->
                <div id="posEntitySelectWrapper" style="margin-bottom:14px">
                    <label style="display:block;font-size:.82rem;margin-bottom:4px;color:var(--text-secondary,#94a3b8)">
                        <?= __pos_t('pos.session.entity_label', 'Branch / Entity') ?>
                    </label>
                    <select name="entity_id" id="posEntitySelect" class="form-control" required>
                        <option value=""><?= __pos_t('pos.select_entity', 'Select Entity/Branch') ?></option>
                    </select>
                </div>

                <!-- Opening balance -->
                <div style="margin-bottom:14px">
                    <label style="display:block;font-size:.82rem;margin-bottom:4px;color:var(--text-secondary,#94a3b8)">
                        <?= __pos_t('pos.session.opening_balance', 'Opening Balance') ?> (SAR)
                    </label>
                    <input type="number" name="opening_balance" class="form-control"
                           step="0.01" min="0" value="0" placeholder="0.00">
                </div>

                <!-- Cashier user (hidden, auto-set to current user) -->
                <input type="hidden" name="cashier_user_id"
                       value="<?= (int)($user['id'] ?? 0) ?>">

                <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:12px">
                    🟢 <?= __pos_t('pos.session.open_btn', 'Open Session') ?>
                </button>
            </form>
        </div>
    </div>

    <!-- ════════════════════════════════════
         MAIN POS LAYOUT
         (shown when session is active)
         ════════════════════════════════════ -->
    <div class="pos-layout" id="posMainLayout" style="display:none">

        <!-- ── Left Panel: Products ── -->
        <div class="pos-products-panel">

            <!-- Search -->
            <div class="pos-search-bar">
                <input type="search" id="posSearch"
                       placeholder="<?= __pos_t('pos.products.search', 'Search products...') ?>"
                       autocomplete="off">
            </div>

            <!-- Category Tabs -->
            <div class="pos-category-tabs" id="posCategoryTabs">
                <button class="pos-cat-tab active" data-cat="all"><?= __pos_t('pos.products.all', 'All') ?></button>
            </div>

            <!-- Products Grid -->
            <div class="pos-products-grid" id="posProductsGrid">
                <div class="pos-loading" style="grid-column:1/-1">
                    <div class="pos-spinner"></div>
                    <span><?= __pos_t('common.loading', 'Loading...') ?></span>
                </div>
            </div>
        </div>

        <!-- ── Right Panel: Cart + Payment ── -->
        <div class="pos-cart-panel">

            <!-- Cart Header -->
            <div class="pos-cart-header">
                <h3>🛒 <?= __pos_t('pos.cart.title', 'Cart') ?> <span class="pos-cart-count" id="posCartCount">0</span></h3>
            </div>

            <!-- Cart Items -->
            <div style="flex:1;overflow:hidden;display:flex;flex-direction:column">
                <!-- Empty state -->
                <div class="pos-cart-empty" id="posCartEmpty">
                    <span class="pos-cart-empty-icon">🛒</span>
                    <p><?= __pos_t('pos.cart.empty', 'Cart is empty') ?></p>
                    <small style="color:var(--text-muted,#64748b)"><?= __pos_t('pos.cart.empty_hint', 'Click a product to add it') ?></small>
                </div>
                <!-- Items list -->
                <div class="pos-cart-items" id="posCartItems"></div>
            </div>

            <!-- Cart Footer: Totals + Payment -->
            <div class="pos-cart-footer">

                <!-- Totals -->
                <div class="pos-totals">
                    <div class="pos-total-row">
                        <span><?= __pos_t('pos.subtotal', 'Subtotal') ?></span>
                        <span id="posSubtotal">0.00 SAR</span>
                    </div>
                    <div class="pos-total-row">
                        <span><?= __pos_t('pos.tax', 'Tax') ?></span>
                        <span id="posTax">0.00 SAR</span>
                    </div>
                    <!-- Discount -->
                    <div class="pos-discount-row">
                        <span style="font-size:.82rem;color:var(--text-secondary,#94a3b8);white-space:nowrap"><?= __pos_t('pos.discount', 'Discount') ?>:</span>
                        <input type="number" id="posDiscount" step="0.01" min="0" placeholder="0.00">
                    </div>
                    <div class="pos-total-row">
                        <span><?= __pos_t('pos.total', 'Total') ?></span>
                        <span id="posTotal">0.00 SAR</span>
                    </div>
                    <div class="pos-total-row grand">
                        <span><?= __pos_t('pos.grand_total', 'Grand Total') ?></span>
                        <span class="amount" id="posGrandTotal">0.00 SAR</span>
                    </div>
                </div>

                <!-- Payment Method -->
                <div style="font-size:.78rem;color:var(--text-muted,#64748b);margin-bottom:6px"><?= __pos_t('pos.payment.title', 'Payment Method') ?></div>
                <div class="pos-payment-methods">
                    <button class="pos-pay-method-btn" data-method="cash">
                        <span class="icon">💵</span>
                        <?= __pos_t('pos.payment.cash', 'Cash') ?>
                    </button>
                    <button class="pos-pay-method-btn" data-method="card">
                        <span class="icon">💳</span>
                        <?= __pos_t('pos.payment.card', 'Card') ?>
                    </button>
                </div>

                <!-- Amount paid (cash only) -->
                <div class="pos-amount-paid-row">
                    <label for="posAmountPaid"><?= __pos_t('pos.payment.amount_paid', 'Amount Paid') ?>:</label>
                    <input type="number" id="posAmountPaid" step="0.01" min="0" placeholder="0.00">
                </div>

                <!-- Change display -->
                <div class="pos-change-display" id="posChange"></div>

                <!-- Checkout button -->
                <button class="pos-checkout-btn" id="posCheckoutBtn" disabled>
                    ✅ <?= __pos_t('pos.cart.checkout', 'Checkout') ?>
                </button>

                <!-- Clear cart -->
                <button class="pos-clear-btn" id="posClearBtn">
                    🗑 <?= __pos_t('pos.cart.clear', 'Clear Cart') ?>
                </button>
            </div>
        </div>
    </div>

    <!-- ════════════════════════════════════
         MODAL BACKDROP (receipt / close session)
         ════════════════════════════════════ -->
    <div class="pos-modal-backdrop" id="posModalBackdrop" style="display:none">
        <div class="pos-modal"></div>
    </div>

</div><!-- /.pos-page -->

<!-- Scripts -->
<?php if ($isFragment): ?>
<script src="/admin/assets/js/admin_framework.js?v=<?= time() ?>"></script>
<script src="/admin/assets/js/pages/pos.js?v=<?= time() ?>"></script>
<?php else: ?>
<script src="/admin/assets/js/admin_framework.js"></script>
<script src="/admin/assets/js/pages/pos.js"></script>
<?php endif; ?>
