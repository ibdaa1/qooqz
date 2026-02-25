<?php
declare(strict_types=1);
/**
 * frontend/public/cart.php
 * QOOQZ — Shopping Cart Page
 *
 * Uses: /api/carts + /api/cart_items (session-based for guests, user_id for logged-in)
 */

require_once dirname(__DIR__) . '/includes/public_context.php';

$ctx      = $GLOBALS['PUB_CONTEXT'];
$lang     = $ctx['lang'];
$tenantId = $ctx['tenant_id'];
$user     = $ctx['user'] ?? null;

$GLOBALS['PUB_APP_NAME']   = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH']  = '/frontend/public';
$GLOBALS['PUB_PAGE_TITLE'] = ($lang === 'ar' ? 'سلة التسوق' : 'Shopping Cart') . ' — QOOQZ';

$sessionId = session_id();
$base      = pub_api_url('');

/* -------------------------------------------------------
 * Handle AJAX/form actions via POST
 * ----------------------------------------------------- */
$action   = $_POST['action'] ?? $_GET['action'] ?? '';
$entityId = (int)($_POST['entity_id'] ?? $_GET['entity_id'] ?? 1);

// -------------------------------------------------------
// Get or create cart
// -------------------------------------------------------
$cartQs = $user
    ? "user_id={$user['id']}&entity_id={$entityId}&tenant_id={$tenantId}&lang={$lang}"
    : "session_id={$sessionId}&entity_id={$entityId}&tenant_id={$tenantId}&lang={$lang}";

$cartResp = pub_fetch($base . "carts?{$cartQs}");
$cart     = $cartResp['data'] ?? $cartResp['cart'] ?? null;
$cartId   = (int)($cart['id'] ?? 0);

// -------------------------------------------------------
// Cart items
// -------------------------------------------------------
$cartItems  = [];
$cartTotal  = 0.0;
$cartCount  = 0;

if ($cartId) {
    $itemsResp = pub_fetch($base . "cart_items?cart_id={$cartId}&tenant_id={$tenantId}&lang={$lang}&limit=100");
    $cartItems = $itemsResp['data']['items'] ?? $itemsResp['items'] ?? [];
}

// Compute totals
foreach ($cartItems as $item) {
    $price = (float)($item['price'] ?? $item['unit_price'] ?? 0);
    $qty   = (int)($item['quantity'] ?? 1);
    $cartTotal += $price * $qty;
    $cartCount += $qty;
}

include dirname(__DIR__) . '/partials/header.php';
?>

<div class="pub-container" style="padding-top:28px;padding-bottom:40px;">

    <!-- Breadcrumb -->
    <nav style="font-size:0.84rem;color:var(--pub-muted);margin-bottom:20px;">
        <a href="/frontend/public/index.php"><?= e(t('common.home')) ?></a>
        <span style="margin:0 6px;">›</span>
        <span>🛒 <?= $lang === 'ar' ? 'سلة التسوق' : 'Shopping Cart' ?></span>
    </nav>

    <h1 style="font-size:1.4rem;margin:0 0 24px;">
        🛒 <?= $lang === 'ar' ? 'سلة التسوق' : 'Shopping Cart' ?>
        <?php if ($cartCount > 0): ?>
            <span style="font-size:0.85rem;font-weight:400;color:var(--pub-muted);">
                (<?= $cartCount ?> <?= $lang === 'ar' ? 'منتج' : 'item(s)' ?>)
            </span>
        <?php endif; ?>
    </h1>

    <?php if (!empty($cartItems)): ?>
    <div class="pub-cart-layout">

        <!-- Items list -->
        <div class="pub-cart-items">
            <?php foreach ($cartItems as $item): ?>
            <?php
                $itemId   = (int)($item['id'] ?? 0);
                $prodName = $item['product_name'] ?? $item['name'] ?? ($lang === 'ar' ? 'منتج' : 'Product');
                $varName  = $item['variant_name'] ?? '';
                $price    = (float)($item['price'] ?? $item['unit_price'] ?? 0);
                $qty      = (int)($item['quantity'] ?? 1);
                $imgUrl   = pub_img($item['image_url'] ?? null, 'product_thumb');
                $prodId   = (int)($item['product_id'] ?? 0);
            ?>
            <div class="pub-cart-item" id="cartItem<?= $itemId ?>">

                <!-- Product image -->
                <div class="pub-cart-item-img">
                    <?php if ($imgUrl): ?>
                        <img src="<?= e($imgUrl) ?>" alt="<?= e($prodName) ?>" loading="lazy"
                             onerror="this.style.display='none'">
                    <?php else: ?>
                        <span style="font-size:2rem;">🖼️</span>
                    <?php endif; ?>
                </div>

                <!-- Product info -->
                <div class="pub-cart-item-info">
                    <a href="/frontend/public/products.php?id=<?= $prodId ?>" class="pub-cart-item-name">
                        <?= e($prodName) ?>
                    </a>
                    <?php if ($varName): ?>
                        <p style="font-size:0.8rem;color:var(--pub-muted);margin:2px 0 0;"><?= e($varName) ?></p>
                    <?php endif; ?>
                    <p class="pub-cart-item-price">
                        <?= number_format($price, 2) ?> <?= e(t('common.currency')) ?>
                    </p>
                </div>

                <!-- Qty + remove -->
                <div class="pub-cart-item-actions">
                    <form method="post" class="pub-qty-form" data-item-id="<?= $itemId ?>">
                        <input type="hidden" name="action" value="update_qty">
                        <input type="hidden" name="cart_item_id" value="<?= $itemId ?>">
                        <input type="hidden" name="entity_id" value="<?= $entityId ?>">
                        <button type="button" class="pub-qty-btn" onclick="changeQty(<?= $itemId ?>,-1)">−</button>
                        <input type="number" name="quantity" class="pub-qty-input"
                               value="<?= $qty ?>" min="1" max="999" id="qty<?= $itemId ?>">
                        <button type="button" class="pub-qty-btn" onclick="changeQty(<?= $itemId ?>,1)">+</button>
                    </form>
                    <p class="pub-cart-item-subtotal">
                        = <?= number_format($price * $qty, 2) ?> <?= e(t('common.currency')) ?>
                    </p>
                    <button class="pub-remove-btn" onclick="removeItem(<?= $itemId ?>)"
                            title="<?= $lang === 'ar' ? 'حذف' : 'Remove' ?>">✕</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Order summary -->
        <div class="pub-cart-summary">
            <div class="pub-cart-summary-inner">
                <h2 class="pub-cart-summary-title">
                    📋 <?= $lang === 'ar' ? 'ملخص الطلب' : 'Order Summary' ?>
                </h2>
                <div class="pub-summary-row">
                    <span><?= $lang === 'ar' ? 'المجموع الفرعي' : 'Subtotal' ?></span>
                    <strong><?= number_format($cartTotal, 2) ?> <?= e(t('common.currency')) ?></strong>
                </div>
                <div class="pub-summary-row" style="color:var(--pub-muted);font-size:0.84rem;">
                    <span><?= $lang === 'ar' ? 'التوصيل' : 'Shipping' ?></span>
                    <span><?= $lang === 'ar' ? 'يُحدد عند الدفع' : 'Calculated at checkout' ?></span>
                </div>
                <div class="pub-summary-row pub-summary-total">
                    <span><?= $lang === 'ar' ? 'الإجمالي' : 'Total' ?></span>
                    <strong id="cartGrandTotal"><?= number_format($cartTotal, 2) ?> <?= e(t('common.currency')) ?></strong>
                </div>
                <a href="/frontend/public/checkout.php?cart_id=<?= $cartId ?>&entity_id=<?= $entityId ?>"
                   class="pub-btn pub-btn--primary" style="width:100%;text-align:center;margin-top:16px;display:block;font-size:1rem;padding:12px;">
                    💳 <?= $lang === 'ar' ? 'إتمام الشراء' : 'Proceed to Checkout' ?>
                </a>
                <a href="/frontend/public/products.php" class="pub-btn pub-btn--ghost pub-btn--sm"
                   style="width:100%;text-align:center;margin-top:10px;display:block;">
                    ← <?= $lang === 'ar' ? 'مواصلة التسوق' : 'Continue Shopping' ?>
                </a>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- Empty cart -->
    <div class="pub-empty" style="padding:60px 0;">
        <div class="pub-empty-icon">🛒</div>
        <p class="pub-empty-msg" style="font-size:1.1rem;margin-bottom:20px;">
            <?= $lang === 'ar' ? 'سلة التسوق فارغة' : 'Your cart is empty' ?>
        </p>
        <a href="/frontend/public/products.php" class="pub-btn pub-btn--primary">
            🛍️ <?= e(t('hero.browse_products')) ?>
        </a>
    </div>
    <?php endif; ?>

</div>

<style>
.pub-cart-layout { display:grid; gap:24px; }
@media(min-width:900px){ .pub-cart-layout { grid-template-columns: 1fr 340px; align-items:start; } }

.pub-cart-items { display:grid; gap:12px; }

.pub-cart-item {
    background:var(--pub-bg);
    border:1px solid var(--pub-border);
    border-radius:var(--pub-radius);
    padding:14px;
    display:flex;
    gap:14px;
    align-items:flex-start;
    transition:box-shadow var(--pub-transition);
}
.pub-cart-item:hover { box-shadow:var(--pub-shadow); }

.pub-cart-item-img {
    width:72px;
    height:72px;
    border-radius:var(--pub-radius-sm);
    overflow:hidden;
    background:var(--pub-surface);
    flex-shrink:0;
    display:flex;
    align-items:center;
    justify-content:center;
}
.pub-cart-item-img img { width:100%;height:100%;object-fit:cover; }

.pub-cart-item-info { flex:1; min-width:0; }
.pub-cart-item-name { font-size:0.92rem;font-weight:600;color:var(--pub-text);display:block;margin-bottom:4px; }
.pub-cart-item-price { font-size:0.88rem;color:var(--pub-primary);font-weight:700;margin:4px 0 0; }

.pub-cart-item-actions {
    display:flex;
    flex-direction:column;
    align-items:flex-end;
    gap:8px;
    flex-shrink:0;
}
.pub-qty-form { display:flex; align-items:center; gap:4px; }
.pub-qty-btn {
    width:28px;height:28px;border-radius:50%;border:1px solid var(--pub-border);
    background:var(--pub-surface);color:var(--pub-text);font-size:1.1rem;
    cursor:pointer;display:flex;align-items:center;justify-content:center;
    transition:background var(--pub-transition);
}
.pub-qty-btn:hover { background:var(--pub-primary);color:#fff;border-color:var(--pub-primary); }
.pub-qty-input {
    width:48px;height:28px;text-align:center;
    border:1px solid var(--pub-border);border-radius:var(--pub-radius-sm);
    background:var(--pub-bg);color:var(--pub-text);font-size:0.88rem;
}
.pub-cart-item-subtotal { font-size:0.88rem;font-weight:700;color:var(--pub-text);margin:0; }
.pub-remove-btn {
    background:none;border:none;color:var(--pub-muted);cursor:pointer;
    font-size:1rem;padding:4px;border-radius:4px;
    transition:color var(--pub-transition);
}
.pub-remove-btn:hover { color:#e74c3c; }

.pub-cart-summary-inner {
    background:var(--pub-bg);
    border:1px solid var(--pub-border);
    border-radius:var(--pub-radius);
    padding:20px;
    position:sticky;
    top:calc(var(--pub-header-h) + 16px);
}
.pub-cart-summary-title { font-size:1rem;font-weight:700;margin:0 0 16px;color:var(--pub-text); }
.pub-summary-row { display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--pub-border); }
.pub-summary-row:last-of-type { border-bottom:none; }
.pub-summary-total { font-size:1rem;font-weight:800;color:var(--pub-text); }
</style>

<script>
var CART_LANG  = <?= json_encode($lang) ?>;
var CART_ID    = <?= $cartId ?>;
var ENTITY_ID  = <?= $entityId ?>;
var TENANT_ID  = <?= $tenantId ?>;
var SESSION_ID = <?= json_encode($sessionId) ?>;

function changeQty(itemId, delta) {
    var input = document.getElementById('qty' + itemId);
    if (!input) return;
    var newQty = Math.max(1, parseInt(input.value) + delta);
    input.value = newQty;
    updateCartItem(itemId, newQty);
}

function updateCartItem(itemId, qty) {
    fetch('/api/cart_items', {
        method:'PUT',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({id: itemId, quantity: qty, tenant_id: TENANT_ID})
    }).then(function(r){ return r.json(); }).then(function(data){
        location.reload();
    }).catch(function(){ location.reload(); });
}

function removeItem(itemId) {
    if(!confirm(CART_LANG==='ar'?'حذف هذا المنتج من السلة؟':'Remove this item from cart?')) return;
    fetch('/api/cart_items', {
        method:'DELETE',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({id: itemId, tenant_id: TENANT_ID})
    }).then(function(r){ return r.json(); }).then(function(){
        var el = document.getElementById('cartItem' + itemId);
        if(el) el.remove();
        location.reload();
    });
}
</script>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>
