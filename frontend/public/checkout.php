<?php
declare(strict_types=1);
/**
 * frontend/public/checkout.php
 * QOOQZ — Checkout / Order Placement
 *
 * Cart items come from localStorage (pub_cart).
 * JS populates a hidden field before form submit.
 * POST → /api/public/orders (creates order + order_items in DB).
 */

require_once dirname(__DIR__) . '/includes/public_context.php';

$ctx      = $GLOBALS['PUB_CONTEXT'];
$lang     = $ctx['lang'];
$dir      = $ctx['dir'];
$tenantId = $ctx['tenant_id'];
$user     = $ctx['user'] ?? null;

$GLOBALS['PUB_APP_NAME']   = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH']  = '/frontend/public';
$GLOBALS['PUB_PAGE_TITLE'] = t('checkout.title') . ' — QOOQZ';

$base     = pub_api_url('');
$entityId = (int)($_GET['entity_id'] ?? 1);

/* -------------------------------------------------------
 * Load payment methods (entity-specific → global fallback)
 * ----------------------------------------------------- */
$pmResp    = pub_fetch($base . "entity_payment_methods?entity_id={$entityId}&tenant_id={$tenantId}");
$entityPMs = $pmResp['data']['items'] ?? $pmResp['items'] ?? $pmResp['data'] ?? [];
if (empty($entityPMs)) {
    $gpmResp   = pub_fetch($base . "payment_methods?tenant_id={$tenantId}&is_active=1");
    $entityPMs = $gpmResp['data']['items'] ?? $gpmResp['items'] ?? $gpmResp['data'] ?? [];
}
if (empty($entityPMs)) {
    $entityPMs = [];
}

/* -------------------------------------------------------
 * Handle form submission
 * ----------------------------------------------------- */
$checkoutError   = '';
$checkoutSuccess = false;
$orderNumber     = '';
$orderId         = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pmCode      = trim($_POST['payment_method_code'] ?? '');
    $custName    = trim($_POST['customer_name'] ?? '');
    $custPhone   = trim($_POST['customer_phone'] ?? '');
    $address     = trim($_POST['delivery_address'] ?? '');
    $notes       = trim($_POST['order_notes'] ?? '');
    $cartJson    = $_POST['cart_items_json'] ?? '[]';

    $cartItems = json_decode($cartJson, true);
    if (!is_array($cartItems)) $cartItems = [];

    if (!$custName || !$custPhone) {
        $checkoutError = t('checkout.error_fields_required');
    } elseif (empty($cartItems)) {
        $checkoutError = t('cart.empty');
    } else {
        // POST to /api/public/orders
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $apiUrl = $scheme . '://' . $host . '/api/public/orders?tenant_id=' . $tenantId;

        $payload = json_encode([
            'entity_id'        => $entityId,
            'tenant_id'        => $tenantId,
            'payment_method'   => $pmCode,
            'customer_name'    => $custName,
            'customer_phone'   => $custPhone,
            'delivery_address' => $address,
            'notes'            => $notes,
            'items'            => $cartItems,
            'user_id'          => $user['id'] ?? 0,
        ]);

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $resp     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $respData = json_decode($resp ?: '{}', true);
        if ($httpCode === 201 || $httpCode === 200) {
            $checkoutSuccess = true;
            $orderNumber     = (string)($respData['data']['order_number'] ?? '');
            $orderId         = (int)($respData['data']['id'] ?? 0);
        } else {
            $checkoutError = $respData['message'] ?? $respData['error'] ?? t('common.error');
        }
    }
}

include dirname(__DIR__) . '/partials/header.php';
?>

<div class="pub-container" style="padding-top:28px;padding-bottom:40px;">

    <!-- Breadcrumb -->
    <nav style="font-size:0.84rem;color:var(--pub-muted);margin-bottom:20px;">
        <a href="/frontend/public/index.php"><?= e(t('common.home')) ?></a>
        <span style="margin:0 6px;">›</span>
        <a href="/frontend/public/cart.php?entity_id=<?= $entityId ?>">
            🛒 <?= e(t('cart.title')) ?>
        </a>
        <span style="margin:0 6px;">›</span>
        <span>💳 <?= e(t('checkout.title')) ?></span>
    </nav>

    <?php if ($checkoutSuccess): ?>
    <!-- ✅ Success state -->
    <div style="text-align:center;padding:60px 20px;">
        <div style="font-size:4rem;margin-bottom:16px;">✅</div>
        <h1 style="font-size:1.6rem;margin:0 0 10px;color:var(--pub-text);">
            <?= e(t('checkout.success_title')) ?>
        </h1>
        <p style="color:var(--pub-muted);margin:0 0 24px;">
            <?= e(t('checkout.success_msg')) ?>
        </p>
        <?php if ($orderNumber): ?>
        <p style="font-size:0.9rem;color:var(--pub-muted);">
            <?= e(t('checkout.order_number')) ?> <strong style="color:var(--pub-primary);">#<?= e($orderNumber) ?></strong>
        </p>
        <?php endif; ?>
        <div style="display:flex;gap:12px;justify-content:center;margin-top:24px;flex-wrap:wrap;">
            <a href="/frontend/public/index.php" class="pub-btn pub-btn--primary">
                🏠 <?= e(t('common.home')) ?>
            </a>
            <a href="/frontend/public/products.php" class="pub-btn pub-btn--ghost">
                🛍️ <?= e(t('hero.browse_products')) ?>
            </a>
        </div>
    </div>
    <!-- Clear cart after success -->
    <script>try { localStorage.removeItem('pub_cart'); } catch(e){}</script>

    <?php else: ?>
    <!-- Checkout form + order summary -->
    <h1 style="font-size:1.4rem;margin:0 0 24px;">💳 <?= e(t('checkout.title')) ?></h1>

    <?php if ($checkoutError): ?>
    <div style="background:rgba(231,76,60,0.1);border:1px solid rgba(231,76,60,0.3);color:#e74c3c;
                padding:12px 16px;border-radius:var(--pub-radius);margin-bottom:20px;">
        ⚠️ <?= e($checkoutError) ?>
    </div>
    <?php endif; ?>

    <form method="post" id="checkoutForm" class="pub-checkout-layout">

        <!-- Hidden field JS populates from localStorage before submit -->
        <input type="hidden" name="cart_items_json" id="cartItemsJson" value="[]">

        <!-- Left column: Customer info + Payment method -->
        <div>
            <!-- Customer info -->
            <div class="pub-checkout-card">
                <h2 class="pub-checkout-card-title">
                    👤 <?= e(t('checkout.customer_info')) ?>
                </h2>
                <div class="pub-form-grid">
                    <div class="pub-form-field">
                        <label class="pub-form-label"><?= e(t('checkout.name')) ?> *</label>
                        <input type="text" name="customer_name" class="pub-form-input" required
                               value="<?= e($user['username'] ?? '') ?>"
                               placeholder="<?= e(t('checkout.name')) ?>">
                    </div>
                    <div class="pub-form-field">
                        <label class="pub-form-label">📞 <?= e(t('checkout.phone')) ?> *</label>
                        <input type="tel" name="customer_phone" class="pub-form-input" required
                               placeholder="<?= e(t('checkout.phone')) ?>">
                    </div>
                    <div class="pub-form-field" style="grid-column:1/-1;">
                        <label class="pub-form-label">📍 <?= e(t('checkout.address')) ?></label>
                        <textarea name="delivery_address" class="pub-form-input" rows="3"
                                  placeholder="<?= e(t('checkout.address')) ?>"
                                  style="resize:vertical;"></textarea>
                    </div>
                    <div class="pub-form-field" style="grid-column:1/-1;">
                        <label class="pub-form-label">📝 <?= e(t('checkout.notes')) ?></label>
                        <textarea name="order_notes" class="pub-form-input" rows="2"
                                  placeholder="<?= e(t('checkout.notes')) ?>"
                                  style="resize:vertical;"></textarea>
                    </div>
                </div>
            </div>

            <!-- Payment method -->
            <?php if (!empty($entityPMs)): ?>
            <div class="pub-checkout-card" style="margin-top:16px;">
                <h2 class="pub-checkout-card-title">💳 <?= e(t('checkout.payment')) ?></h2>
                <div class="pub-pm-grid">
                    <?php foreach ($entityPMs as $idx => $pm): ?>
                    <?php
                        $pmCode = $pm['code'] ?? $pm['payment_method_code'] ?? ('pm_' . $idx);
                        $pmName = $pm['name'] ?? $pm['payment_method_name'] ?? $pmCode;
                        $pmIcon = $pm['icon'] ?? ($pm['type'] === 'bank' ? '🏦' : '💳');
                    ?>
                    <label class="pub-pm-option">
                        <input type="radio" name="payment_method_code"
                               value="<?= e($pmCode) ?>" <?= $idx === 0 ? 'checked' : '' ?> required>
                        <span class="pub-pm-label">
                            <span class="pub-pm-icon"><?= e($pmIcon) ?></span>
                            <span class="pub-pm-name"><?= e($pmName) ?></span>
                        </span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <!-- Default: cash on delivery if no payment methods configured -->
            <input type="hidden" name="payment_method_code" value="cash_on_delivery">
            <?php endif; ?>
        </div>

        <!-- Right column: Order summary (filled by JS from localStorage) -->
        <div class="pub-checkout-summary">
            <div class="pub-cart-summary-inner">
                <h2 class="pub-cart-summary-title">📋 <?= e(t('cart.order_summary')) ?></h2>
                <div id="checkoutItemsList" style="margin-bottom:14px;display:grid;gap:8px;max-height:260px;overflow-y:auto;">
                    <!-- JS populates this -->
                    <p style="color:var(--pub-muted);font-size:0.85rem;" id="checkoutEmptyMsg">
                        <?= e(t('cart.empty')) ?>
                    </p>
                </div>
                <hr style="border-color:var(--pub-border);margin:10px 0;">
                <div class="pub-summary-row">
                    <span><?= e(t('cart.subtotal')) ?></span>
                    <strong id="checkoutSubtotal">0.00</strong>
                </div>
                <div class="pub-summary-row" style="color:var(--pub-muted);font-size:0.84rem;">
                    <span><?= e(t('cart.shipping')) ?></span>
                    <span>—</span>
                </div>
                <div class="pub-summary-row pub-summary-total">
                    <span><?= e(t('cart.total')) ?></span>
                    <strong id="checkoutTotal">0.00</strong>
                </div>

                <button type="submit" id="checkoutSubmitBtn"
                        class="pub-btn pub-btn--primary"
                        style="width:100%;margin-top:16px;font-size:1rem;padding:13px;display:block;">
                    ✅ <?= e(t('checkout.place_order')) ?>
                </button>
                <p style="font-size:0.75rem;text-align:center;color:var(--pub-muted);margin-top:12px;">
                    🔒 <?= e(t('checkout.secure_transaction')) ?>
                </p>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>

<style>
.pub-checkout-layout { display:grid; gap:24px; }
@media(min-width:900px){ .pub-checkout-layout { grid-template-columns:1fr 360px; align-items:start; } }

.pub-checkout-card {
    background:var(--pub-bg); border:1px solid var(--pub-border);
    border-radius:var(--pub-radius); overflow:hidden;
}
.pub-checkout-card-title {
    font-size:1rem; font-weight:700; margin:0; padding:12px 16px;
    border-bottom:1px solid var(--pub-border); color:var(--pub-text);
}
.pub-form-grid { display:grid; gap:14px; padding:16px; }
@media(min-width:600px){ .pub-form-grid { grid-template-columns:1fr 1fr; } }
.pub-form-label { display:block; font-size:0.82rem; font-weight:600; color:var(--pub-muted); margin-bottom:4px; }
.pub-form-input {
    width:100%; padding:9px 12px;
    border:1px solid var(--pub-border); border-radius:var(--pub-radius-sm);
    background:var(--pub-bg); color:var(--pub-text); font-size:0.9rem;
    transition:border-color var(--pub-transition); font-family:inherit;
    box-sizing:border-box;
}
.pub-form-input:focus { outline:none; border-color:var(--pub-primary); box-shadow:0 0 0 2px rgba(3,135,78,0.12); }

.pub-pm-grid { display:grid; gap:10px; padding:16px; }
@media(min-width:500px){ .pub-pm-grid { grid-template-columns:1fr 1fr; } }
.pub-pm-option {
    border:2px solid var(--pub-border); border-radius:var(--pub-radius);
    padding:12px; cursor:pointer; display:block;
    transition:border-color 0.2s, background 0.2s;
}
.pub-pm-option:has(input:checked) { border-color:var(--pub-primary); background:rgba(3,135,78,0.06); }
.pub-pm-option input[type=radio] { position:absolute; opacity:0; width:0; height:0; }
.pub-pm-label { display:flex; align-items:center; gap:10px; }
.pub-pm-icon  { font-size:1.4rem; }
.pub-pm-name  { font-size:0.88rem; font-weight:600; color:var(--pub-text); }

.pub-checkout-summary { position:sticky; top:calc(var(--pub-header-h, 64px) + 16px); }
</style>

<script>
(function () {
    'use strict';

    function getCart() {
        try { return JSON.parse(localStorage.getItem('pub_cart') || '[]'); } catch (e) { return []; }
    }

    function formatPrice(n) {
        return parseFloat(n || 0).toFixed(2);
    }

    function renderSummary() {
        var cart     = getCart();
        var list     = document.getElementById('checkoutItemsList');
        var emptyMsg = document.getElementById('checkoutEmptyMsg');
        var subtEl   = document.getElementById('checkoutSubtotal');
        var totEl    = document.getElementById('checkoutTotal');
        var submitBtn = document.getElementById('checkoutSubmitBtn');
        var hidden   = document.getElementById('cartItemsJson');

        if (!list) return;

        if (!cart.length) {
            if (emptyMsg) emptyMsg.style.display = '';
            if (submitBtn) submitBtn.disabled = true;
            return;
        }

        if (emptyMsg) emptyMsg.style.display = 'none';
        if (submitBtn) submitBtn.disabled = false;

        var subtotal = 0;
        var html = '';
        cart.forEach(function (item) {
            var price = parseFloat(item.price || 0);
            var qty   = parseInt(item.qty || 1, 10);
            var line  = price * qty;
            subtotal += line;
            html += '<div style="display:flex;justify-content:space-between;align-items:center;font-size:0.85rem;color:var(--pub-text);">'
                  + '<span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;padding-inline-end:8px;">'
                  + (item.name || '') + ' <span style="color:var(--pub-muted);">×' + qty + '</span></span>'
                  + '<strong>' + formatPrice(line) + '</strong></div>';
        });
        list.innerHTML = html;
        if (subtEl) subtEl.textContent = formatPrice(subtotal);
        if (totEl)  totEl.textContent  = formatPrice(subtotal);
        if (hidden) hidden.value = JSON.stringify(cart);
    }

    document.addEventListener('DOMContentLoaded', renderSummary);

    var form = document.getElementById('checkoutForm');
    if (form) {
        form.addEventListener('submit', function () {
            // Re-serialize cart just before submit (in case user changed tabs)
            var hidden = document.getElementById('cartItemsJson');
            if (hidden) hidden.value = JSON.stringify(getCart());
        });
    }
}());
</script>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>


require_once dirname(__DIR__) . '/includes/public_context.php';

$ctx      = $GLOBALS['PUB_CONTEXT'];
$lang     = $ctx['lang'];
$tenantId = $ctx['tenant_id'];
$user     = $ctx['user'] ?? null;

$GLOBALS['PUB_APP_NAME']   = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH']  = '/frontend/public';
$GLOBALS['PUB_PAGE_TITLE'] = t('checkout.title') . ' — QOOQZ';

$sessionId = session_id();
$base      = pub_api_url('');

$cartId   = (int)($_GET['cart_id'] ?? 0);
$entityId = (int)($_GET['entity_id'] ?? 1);

/* -------------------------------------------------------
 * Load cart + items
 * ----------------------------------------------------- */
$cartItems = [];
$cartTotal = 0.0;

if ($cartId) {
    $itemsResp = pub_fetch($base . "cart_items?cart_id={$cartId}&tenant_id={$tenantId}&lang={$lang}&limit=100");
    $cartItems = $itemsResp['data']['items'] ?? $itemsResp['items'] ?? [];
}
foreach ($cartItems as $item) {
    $price     = (float)($item['price'] ?? $item['unit_price'] ?? 0);
    $qty       = (int)($item['quantity'] ?? 1);
    $cartTotal += $price * $qty;
}

/* -------------------------------------------------------
 * Load payment methods (entity-specific + global)
 * ----------------------------------------------------- */
$pmResp     = pub_fetch($base . "entity_payment_methods?entity_id={$entityId}&tenant_id={$tenantId}&lang={$lang}");
$entityPMs  = $pmResp['data']['items'] ?? $pmResp['items'] ?? $pmResp['data'] ?? [];

// Global payment methods as fallback
if (empty($entityPMs)) {
    $gpmResp   = pub_fetch($base . "payment_methods?tenant_id={$tenantId}&lang={$lang}&is_active=1");
    $entityPMs = $gpmResp['data']['items'] ?? $gpmResp['items'] ?? $gpmResp['data'] ?? [];
}

// No payment methods configured for this entity — show empty list
if (empty($entityPMs)) {
    $entityPMs = [];
}

/* -------------------------------------------------------
 * Handle checkout form submission
 * ----------------------------------------------------- */
$checkoutError   = '';
$checkoutSuccess = false;
$paymentId       = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['payment_method_code'])) {
    $pmCode  = htmlspecialchars(trim($_POST['payment_method_code'] ?? ''), ENT_QUOTES, 'UTF-8');
    $notes   = htmlspecialchars(trim($_POST['order_notes'] ?? ''), ENT_QUOTES, 'UTF-8');
    $name    = htmlspecialchars(trim($_POST['customer_name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $phone   = htmlspecialchars(trim($_POST['customer_phone'] ?? ''), ENT_QUOTES, 'UTF-8');
    $address = htmlspecialchars(trim($_POST['delivery_address'] ?? ''), ENT_QUOTES, 'UTF-8');

    if (!$name || !$phone) {
        $checkoutError = t('checkout.error_fields_required');
    } elseif (!$cartId || $cartTotal <= 0) {
        $checkoutError = t('cart.empty');
    } else {
        // Create payment via API
        $payload = [
            'cart_id'           => $cartId,
            'entity_id'         => $entityId,
            'tenant_id'         => $tenantId,
            'payment_method'    => $pmCode,
            'amount'            => $cartTotal,
            'currency'          => 'SAR',
            'customer_name'     => $name,
            'customer_phone'    => $phone,
            'delivery_address'  => $address,
            'notes'             => $notes,
            'session_id'        => $sessionId,
            'user_id'           => $user['id'] ?? null,
            'status'            => 'pending',
        ];

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $apiUrl = rtrim($scheme . '://' . $host . '/api', '/') . '/payments?tenant_id=' . $tenantId;

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_TIMEOUT        => 6,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $resp    = curl_exec($ch);
        $httpCode= curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $respData = json_decode($resp ?: '{}', true);
        if ($httpCode === 201 || $httpCode === 200) {
            $checkoutSuccess = true;
            $paymentId       = (int)($respData['data']['id'] ?? $respData['id'] ?? 0);
        } else {
            $checkoutError = $respData['message'] ?? $respData['error'] ?? t('common.error');
        }
    }
}

include dirname(__DIR__) . '/partials/header.php';
?>

<div class="pub-container" style="padding-top:28px;padding-bottom:40px;">

    <!-- Breadcrumb -->
    <nav style="font-size:0.84rem;color:var(--pub-muted);margin-bottom:20px;">
        <a href="/frontend/public/index.php"><?= e(t('common.home')) ?></a>
        <span style="margin:0 6px;">›</span>
        <a href="/frontend/public/cart.php?entity_id=<?= $entityId ?>">
            🛒 <?= e(t('cart.title')) ?>
        </a>
        <span style="margin:0 6px;">›</span>
        <span>💳 <?= e(t('checkout.title')) ?></span>
    </nav>

    <?php if ($checkoutSuccess): ?>
    <!-- Success state -->
    <div style="text-align:center;padding:60px 20px;">
        <div style="font-size:4rem;margin-bottom:16px;">✅</div>
        <h1 style="font-size:1.6rem;margin:0 0 10px;color:var(--pub-text);">
            <?= e(t('checkout.success_title')) ?>
        </h1>
        <p style="color:var(--pub-muted);margin:0 0 24px;">
            <?= e(t('checkout.success_msg')) ?>
        </p>
        <?php if ($paymentId): ?>
            <p style="font-size:0.85rem;color:var(--pub-muted);">
                <?= e(t('checkout.order_number')) ?> <strong>#<?= $paymentId ?></strong>
            </p>
        <?php endif; ?>
        <div style="display:flex;gap:12px;justify-content:center;margin-top:20px;flex-wrap:wrap;">
            <a href="/frontend/public/index.php" class="pub-btn pub-btn--primary">
                🏠 <?= e(t('common.home')) ?>
            </a>
            <a href="/frontend/public/products.php" class="pub-btn pub-btn--ghost">
                🛍️ <?= e(t('hero.browse_products')) ?>
            </a>
        </div>
    </div>

    <?php else: ?>
    <!-- Checkout form + summary -->
    <h1 style="font-size:1.4rem;margin:0 0 24px;">💳 <?= e(t('checkout.title')) ?></h1>

    <?php if ($checkoutError): ?>
    <div style="background:rgba(231,76,60,0.1);border:1px solid rgba(231,76,60,0.3);color:#e74c3c;
                padding:12px 16px;border-radius:var(--pub-radius);margin-bottom:20px;">
        ⚠️ <?= e($checkoutError) ?>
    </div>
    <?php endif; ?>

    <form method="post" class="pub-checkout-layout" id="checkoutForm">
        <!-- Left: Customer Info + Payment -->
        <div>
            <!-- Customer info -->
            <div class="pub-checkout-card">
                <h2 class="pub-checkout-card-title">
                    👤 <?= e(t('checkout.customer_info')) ?>
                </h2>
                <div class="pub-form-grid">
                    <div class="pub-form-field">
                        <label class="pub-form-label">
                            <?= e(t('checkout.name')) ?> *
                        </label>
                        <input type="text" name="customer_name" class="pub-form-input" required
                               value="<?= e($user['username'] ?? '') ?>"
                               placeholder="<?= e(t('checkout.name')) ?>">
                    </div>
                    <div class="pub-form-field">
                        <label class="pub-form-label">
                            📞 <?= e(t('checkout.phone')) ?> *
                        </label>
                        <input type="tel" name="customer_phone" class="pub-form-input" required
                               placeholder="">
                    </div>
                    <div class="pub-form-field" style="grid-column:1/-1;">
                        <label class="pub-form-label">
                            📍 <?= e(t('checkout.address')) ?>
                        </label>
                        <textarea name="delivery_address" class="pub-form-input" rows="3"
                                  placeholder="<?= e(t('checkout.address')) ?>"
                                  style="resize:vertical;"></textarea>
                    </div>
                    <div class="pub-form-field" style="grid-column:1/-1;">
                        <label class="pub-form-label">
                            📝 <?= e(t('checkout.notes')) ?>
                        </label>
                        <textarea name="order_notes" class="pub-form-input" rows="2"
                                  placeholder="<?= e(t('checkout.notes')) ?>"
                                  style="resize:vertical;"></textarea>
                    </div>
                </div>
            </div>

            <div class="pub-checkout-card" style="margin-top:16px;">
                <h2 class="pub-checkout-card-title">
                    💳 <?= e(t('checkout.payment')) ?>
                </h2>
                <div class="pub-pm-grid">
                    <?php foreach ($entityPMs as $idx => $pm): ?>
                    <?php
                        $pmCode = $pm['code'] ?? $pm['payment_method_code'] ?? ('pm_' . $idx);
                        $pmName = $pm['name'] ?? $pm['payment_method_name'] ?? $pmCode;
                        $pmIcon = $pm['icon'] ?? ($pm['type'] === 'bank' ? '🏦' : '💳');
                    ?>
                    <label class="pub-pm-option">
                        <input type="radio" name="payment_method_code"
                               value="<?= e($pmCode) ?>" <?= $idx === 0 ? 'checked' : '' ?> required>
                        <span class="pub-pm-label">
                            <span class="pub-pm-icon"><?= e($pmIcon) ?></span>
                            <span class="pub-pm-name"><?= e($pmName) ?></span>
                        </span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Right: Order summary -->
        <div class="pub-checkout-summary">
            <div class="pub-cart-summary-inner">
                <h2 class="pub-cart-summary-title">📋 <?= e(t('cart.order_summary')) ?></h2>

                <?php if (!empty($cartItems)): ?>
                <div style="margin-bottom:14px;display:grid;gap:8px;max-height:260px;overflow-y:auto;">
                    <?php foreach ($cartItems as $item): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;font-size:0.85rem;color:var(--pub-text);">
                        <span style="flex:1;padding-inline-end:8px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            <?= e($item['product_name'] ?? $item['name'] ?? '') ?>
                            <span style="color:var(--pub-muted);"> ×<?= (int)($item['quantity'] ?? 1) ?></span>
                        </span>
                        <strong>
                            <?= number_format((float)($item['price'] ?? 0) * (int)($item['quantity'] ?? 1), 2) ?>
                        </strong>
                    </div>
                    <?php endforeach; ?>
                </div>
                <hr style="border-color:var(--pub-border);margin:10px 0;">
                <?php endif; ?>

                <div class="pub-summary-row">
                    <span><?= e(t('cart.subtotal')) ?></span>
                    <strong><?= number_format($cartTotal, 2) ?> <?= e(t('common.currency')) ?></strong>
                </div>
                <div class="pub-summary-row" style="color:var(--pub-muted);font-size:0.84rem;">
                    <span><?= e(t('cart.shipping')) ?></span>
                    <span>—</span>
                </div>
                <div class="pub-summary-row pub-summary-total">
                    <span><?= e(t('cart.total')) ?></span>
                    <strong><?= number_format($cartTotal, 2) ?> <?= e(t('common.currency')) ?></strong>
                </div>

                <button type="submit" class="pub-btn pub-btn--primary"
                        style="width:100%;margin-top:16px;font-size:1rem;padding:13px;display:block;">
                    ✅ <?= e(t('checkout.place_order')) ?>
                </button>

                <p style="font-size:0.75rem;text-align:center;color:var(--pub-muted);margin-top:12px;">
                    🔒 <?= e(t('checkout.secure_transaction')) ?>
                </p>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>

<style>
.pub-checkout-layout { display:grid; gap:24px; }
@media(min-width:900px){ .pub-checkout-layout { grid-template-columns:1fr 360px;align-items:start; } }

.pub-checkout-card {
    background:var(--pub-bg);border:1px solid var(--pub-border);
    border-radius:var(--pub-radius);overflow:hidden;
}
.pub-checkout-card-title {
    font-size:1rem;font-weight:700;margin:0;padding:12px 16px;
    border-bottom:1px solid var(--pub-border);color:var(--pub-text);
}
.pub-form-grid { display:grid; gap:14px; padding:16px; }
@media(min-width:600px){ .pub-form-grid { grid-template-columns:1fr 1fr; } }
.pub-form-label { display:block;font-size:0.82rem;font-weight:600;color:var(--pub-muted);margin-bottom:4px; }
.pub-form-input {
    width:100%;padding:9px 12px;border:1px solid var(--pub-border);border-radius:var(--pub-radius-sm);
    background:var(--pub-bg);color:var(--pub-text);font-size:0.9rem;
    transition:border-color var(--pub-transition);font-family:inherit;
}
.pub-form-input:focus { outline:none;border-color:var(--pub-primary);box-shadow:0 0 0 2px rgba(45,140,240,0.12); }

.pub-pm-grid { display:grid;gap:10px;padding:16px; }
@media(min-width:500px){ .pub-pm-grid { grid-template-columns:1fr 1fr; } }
.pub-pm-option {
    border:2px solid var(--pub-border);border-radius:var(--pub-radius);padding:12px;
    cursor:pointer;transition:border-color var(--pub-transition),background var(--pub-transition);
    display:block;
}
.pub-pm-option:has(input:checked) { border-color:var(--pub-primary);background:rgba(45,140,240,0.06); }
.pub-pm-option input[type=radio] { position:absolute;opacity:0;width:0;height:0; }
.pub-pm-label { display:flex;align-items:center;gap:10px; }
.pub-pm-icon { font-size:1.4rem; }
.pub-pm-name { font-size:0.88rem;font-weight:600;color:var(--pub-text); }

.pub-checkout-summary { position:sticky;top:calc(var(--pub-header-h)+16px); }
</style>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>
