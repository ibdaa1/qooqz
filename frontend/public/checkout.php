<?php
declare(strict_types=1);
/**
 * frontend/public/checkout.php
 * QOOQZ — Checkout / Payment Page
 *
 * Uses: /api/carts, /api/cart_items, /api/payment_methods,
 *       /api/entity_payment_methods, /api/payments
 */

require_once dirname(__DIR__) . '/includes/public_context.php';

$ctx      = $GLOBALS['PUB_CONTEXT'];
$lang     = $ctx['lang'];
$tenantId = $ctx['tenant_id'];
$user     = $ctx['user'] ?? null;

$GLOBALS['PUB_APP_NAME']   = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH']  = '/frontend/public';
$GLOBALS['PUB_PAGE_TITLE'] = ($lang === 'ar' ? 'إتمام الشراء' : 'Checkout') . ' — QOOQZ';

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

// Demo fallback
if (empty($entityPMs)) {
    $entityPMs = [
        ['id'=>1, 'code'=>'card',  'name'=>$lang==='ar'?'بطاقة ائتمان':'Credit Card',  'icon'=>'💳', 'is_active'=>1],
        ['id'=>2, 'code'=>'mada',  'name'=>'Mada',                                       'icon'=>'💳', 'is_active'=>1],
        ['id'=>3, 'code'=>'bank',  'name'=>$lang==='ar'?'تحويل بنكي':'Bank Transfer',   'icon'=>'🏦', 'is_active'=>1],
        ['id'=>4, 'code'=>'cod',   'name'=>$lang==='ar'?'الدفع عند الاستلام':'Cash on Delivery','icon'=>'💵','is_active'=>1],
    ];
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
        $checkoutError = $lang === 'ar'
            ? 'يرجى إدخال الاسم ورقم الهاتف.'
            : 'Please enter your name and phone number.';
    } elseif (!$cartId || $cartTotal <= 0) {
        $checkoutError = $lang === 'ar' ? 'السلة فارغة.' : 'Cart is empty.';
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
            $checkoutError = $respData['message'] ?? $respData['error'] ?? ($lang === 'ar' ? 'حدث خطأ، حاول مجدداً.' : 'An error occurred, please try again.');
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
            🛒 <?= $lang === 'ar' ? 'سلة التسوق' : 'Cart' ?>
        </a>
        <span style="margin:0 6px;">›</span>
        <span>💳 <?= $lang === 'ar' ? 'إتمام الشراء' : 'Checkout' ?></span>
    </nav>

    <?php if ($checkoutSuccess): ?>
    <!-- Success state -->
    <div style="text-align:center;padding:60px 20px;">
        <div style="font-size:4rem;margin-bottom:16px;">✅</div>
        <h1 style="font-size:1.6rem;margin:0 0 10px;color:var(--pub-text);">
            <?= $lang === 'ar' ? 'تم تقديم طلبك بنجاح!' : 'Order Placed Successfully!' ?>
        </h1>
        <p style="color:var(--pub-muted);margin:0 0 24px;">
            <?= $lang === 'ar'
                ? 'شكراً لك. سيتم التواصل معك قريباً لتأكيد الطلب.'
                : 'Thank you! We will contact you shortly to confirm your order.' ?>
        </p>
        <?php if ($paymentId): ?>
            <p style="font-size:0.85rem;color:var(--pub-muted);">
                <?= $lang === 'ar' ? 'رقم الطلب:' : 'Order #' ?> <strong>#<?= $paymentId ?></strong>
            </p>
        <?php endif; ?>
        <div style="display:flex;gap:12px;justify-content:center;margin-top:20px;flex-wrap:wrap;">
            <a href="/frontend/public/index.php" class="pub-btn pub-btn--primary">
                🏠 <?= $lang === 'ar' ? 'الصفحة الرئيسية' : 'Home' ?>
            </a>
            <a href="/frontend/public/products.php" class="pub-btn pub-btn--ghost">
                🛍️ <?= e(t('hero.browse_products')) ?>
            </a>
        </div>
    </div>

    <?php else: ?>
    <!-- Checkout form + summary -->
    <h1 style="font-size:1.4rem;margin:0 0 24px;">💳 <?= $lang === 'ar' ? 'إتمام الشراء' : 'Checkout' ?></h1>

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
                    👤 <?= $lang === 'ar' ? 'بيانات العميل' : 'Customer Info' ?>
                </h2>
                <div class="pub-form-grid">
                    <div class="pub-form-field">
                        <label class="pub-form-label">
                            <?= $lang === 'ar' ? 'الاسم الكامل' : 'Full Name' ?> *
                        </label>
                        <input type="text" name="customer_name" class="pub-form-input" required
                               value="<?= e($user['username'] ?? '') ?>"
                               placeholder="<?= $lang === 'ar' ? 'أدخل اسمك الكامل' : 'Enter your full name' ?>">
                    </div>
                    <div class="pub-form-field">
                        <label class="pub-form-label">
                            📞 <?= $lang === 'ar' ? 'رقم الهاتف' : 'Phone Number' ?> *
                        </label>
                        <input type="tel" name="customer_phone" class="pub-form-input" required
                               placeholder="<?= $lang === 'ar' ? '+966XXXXXXXXX' : '+1XXXXXXXXXX' ?>">
                    </div>
                    <div class="pub-form-field" style="grid-column:1/-1;">
                        <label class="pub-form-label">
                            📍 <?= $lang === 'ar' ? 'عنوان التوصيل' : 'Delivery Address' ?>
                        </label>
                        <textarea name="delivery_address" class="pub-form-input" rows="3"
                                  placeholder="<?= $lang === 'ar' ? 'أدخل العنوان بالتفصيل' : 'Enter delivery address' ?>"
                                  style="resize:vertical;"></textarea>
                    </div>
                    <div class="pub-form-field" style="grid-column:1/-1;">
                        <label class="pub-form-label">
                            📝 <?= $lang === 'ar' ? 'ملاحظات الطلب' : 'Order Notes' ?>
                        </label>
                        <textarea name="order_notes" class="pub-form-input" rows="2"
                                  placeholder="<?= $lang === 'ar' ? 'أي ملاحظات خاصة...' : 'Any special instructions...' ?>"
                                  style="resize:vertical;"></textarea>
                    </div>
                </div>
            </div>

            <!-- Payment method selection -->
            <div class="pub-checkout-card" style="margin-top:16px;">
                <h2 class="pub-checkout-card-title">
                    💳 <?= $lang === 'ar' ? 'طريقة الدفع' : 'Payment Method' ?>
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
                <h2 class="pub-cart-summary-title">📋 <?= $lang === 'ar' ? 'ملخص الطلب' : 'Order Summary' ?></h2>

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
                    <span><?= $lang === 'ar' ? 'المجموع الفرعي' : 'Subtotal' ?></span>
                    <strong><?= number_format($cartTotal, 2) ?> <?= e(t('common.currency')) ?></strong>
                </div>
                <div class="pub-summary-row" style="color:var(--pub-muted);font-size:0.84rem;">
                    <span><?= $lang === 'ar' ? 'التوصيل' : 'Shipping' ?></span>
                    <span><?= $lang === 'ar' ? 'يُحدد لاحقاً' : 'TBD' ?></span>
                </div>
                <div class="pub-summary-row pub-summary-total">
                    <span><?= $lang === 'ar' ? 'الإجمالي' : 'Total' ?></span>
                    <strong><?= number_format($cartTotal, 2) ?> <?= e(t('common.currency')) ?></strong>
                </div>

                <button type="submit" class="pub-btn pub-btn--primary"
                        style="width:100%;margin-top:16px;font-size:1rem;padding:13px;display:block;">
                    ✅ <?= $lang === 'ar' ? 'تأكيد الطلب' : 'Place Order' ?>
                </button>

                <p style="font-size:0.75rem;text-align:center;color:var(--pub-muted);margin-top:12px;">
                    🔒 <?= $lang === 'ar' ? 'معاملاتك آمنة ومشفرة' : 'Your transaction is secure and encrypted' ?>
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
