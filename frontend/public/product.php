<?php
declare(strict_types=1);
/**
 * frontend/public/product.php
 * QOOQZ — Product Detail Page
 * Shows full product info: gallery, description, price, brand, categories, related products.
 * Uses direct PDO queries (not HTTP loopback) for reliability on all hosting environments.
 */

require_once dirname(__DIR__) . '/includes/public_context.php';

$ctx      = $GLOBALS['PUB_CONTEXT'];
$lang     = $ctx['lang'];
$dir      = $ctx['dir'];
$tenantId = $ctx['tenant_id'];

// Accept id or slug
$productId   = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : 0;
$productSlug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

if (!$productId && $productSlug === '') {
    header('Location: /frontend/public/products.php');
    exit;
}

// -------------------------------------------------------
// Load product via direct PDO (avoids HTTP loopback issues)
// Falls back to pub_fetch() HTTP call if PDO unavailable.
// -------------------------------------------------------
$product    = null;
$images     = [];
$categories = [];
$related    = [];

$pdo = pub_get_pdo();
if ($pdo) {
    try {
        // Resolve slug → id
        if (!$productId && $productSlug !== '') {
            $params = [$productSlug];
            $cond   = ' AND is_active = 1';
            if ($tenantId) { $cond .= ' AND tenant_id = ?'; $params[] = $tenantId; }
            $st = $pdo->prepare('SELECT id FROM products WHERE slug = ?' . $cond . ' LIMIT 1');
            $st->execute($params);
            $r = $st->fetch();
            if ($r) $productId = (int)$r['id'];
        }

        if ($productId) {
            // Main product row — no tenant_id filter when loading by explicit ID.
            // Products are public; restricting by tenant_id breaks cross-tenant links.
            // Uses scalar subqueries for pricing and images to avoid multi-row JOIN issues
            // (eliminates dependency on pp.is_active column existing in product_pricing).
            $qParams = [$lang, $productId];

            $st = $pdo->prepare(
                "SELECT p.id, p.sku, p.slug, p.barcode, p.brand_id,
                        p.is_active, p.is_featured, p.is_new, p.is_bestseller,
                        p.stock_quantity, p.stock_status, p.rating_average, p.rating_count,
                        p.views_count, p.tenant_id,
                        COALESCE(pt.name, p.slug) AS name,
                        pt.short_description, pt.description, pt.specifications,
                        (SELECT pp.price FROM product_pricing pp
                           WHERE pp.product_id = p.id ORDER BY pp.id ASC LIMIT 1) AS price,
                        (SELECT pp.compare_at_price FROM product_pricing pp
                           WHERE pp.product_id = p.id ORDER BY pp.id ASC LIMIT 1) AS compare_at_price,
                        (SELECT pp.currency_code FROM product_pricing pp
                           WHERE pp.product_id = p.id ORDER BY pp.id ASC LIMIT 1) AS currency_code,
                        b.name AS brand_name,
                        (SELECT i.url FROM images i WHERE i.owner_id = p.id
                           ORDER BY i.is_main DESC, i.sort_order ASC, i.id ASC LIMIT 1) AS image_url,
                        (SELECT i.thumb_url FROM images i WHERE i.owner_id = p.id
                           ORDER BY i.is_main DESC, i.sort_order ASC, i.id ASC LIMIT 1) AS image_thumb_url
                   FROM products p
              LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.language_code = ?
              LEFT JOIN brands b ON b.id = p.brand_id
                  WHERE p.id = ?"
            );
            $st->execute($qParams);
            $product = $st->fetch() ?: null;

            if ($product) {
                // Gallery images
                $st = $pdo->prepare(
                    "SELECT i.id, i.url, i.thumb_url, i.alt_text, i.sort_order
                       FROM images i
                  LEFT JOIN image_types it ON it.id = i.image_type_id
                      WHERE i.owner_id = ?
                        AND (it.code IS NULL OR it.code IN ('product','product_thumb'))
                      ORDER BY i.is_main DESC, i.sort_order ASC, i.id ASC LIMIT 10"
                );
                $st->execute([$productId]);
                $images = $st->fetchAll();

                // Categories
                $st = $pdo->prepare(
                    "SELECT c.id, COALESCE(ct.name, c.slug) AS name, c.slug
                       FROM categories c
                 INNER JOIN product_categories pc ON pc.category_id = c.id AND pc.product_id = ?
                  LEFT JOIN category_translations ct ON ct.category_id = c.id AND ct.language_code = ?
                      LIMIT 5"
                );
                $st->execute([$productId, $lang]);
                $categories = $st->fetchAll();

                // Related products (same first category)
                if (!empty($categories[0]['id'])) {
                    $st = $pdo->prepare(
                        "SELECT p2.id, COALESCE(pt2.name, p2.slug) AS name, p2.slug,
                                pp2.price, pp2.currency_code,
                                (SELECT i2.url FROM images i2 WHERE i2.owner_id = p2.id
                                   ORDER BY i2.is_main DESC, i2.id ASC LIMIT 1) AS image_url
                           FROM products p2
                     INNER JOIN product_categories pc2 ON pc2.product_id = p2.id AND pc2.category_id = ?
                      LEFT JOIN product_translations pt2 ON pt2.product_id = p2.id AND pt2.language_code = ?
                      LEFT JOIN product_pricing pp2 ON pp2.product_id = p2.id
                             AND pp2.variant_id IS NULL
                          WHERE p2.is_active = 1 AND p2.id != ? AND p2.tenant_id = ?
                          ORDER BY p2.is_featured DESC, p2.id DESC LIMIT 8"
                    );
                    $st->execute([(int)$categories[0]['id'], $lang, $productId, (int)$product['tenant_id']]);
                    $related = $st->fetchAll();
                }
            }
        }
    } catch (Throwable $_) {
        $product = null; // fall through to HTTP fallback
    }
}

// HTTP fallback when PDO unavailable or failed
if (!$product) {
    // No tenant_id filter for direct ID lookup — product is public regardless of tenant
    $qs  = 'lang=' . urlencode($lang);
    $url = $productId
        ? pub_api_url('') . 'public/products?id=' . $productId . '&' . $qs
        : pub_api_url('') . 'public/products?slug=' . urlencode($productSlug) . '&' . $qs . '&tenant_id=' . $tenantId;
    $resp       = pub_fetch($url);
    $product    = $resp['data']['product']    ?? null;
    $images     = $resp['data']['images']     ?? [];
    $categories = $resp['data']['categories'] ?? [];
    $related    = $resp['data']['related']    ?? [];
}

$productName  = $product['name'] ?? $product['slug'] ?? '';
$productDesc  = $product['description'] ?? $product['short_description'] ?? '';
$price        = $product['price'] ?? null;
$comparePrice = $product['compare_at_price'] ?? null;
$currency     = $product['currency_code'] ?? '';
$stockStatus  = $product['stock_status'] ?? '';
$stockQty     = (int)($product['stock_quantity'] ?? 0);
$mainImage    = $product['image_url'] ?? '';
$thumbImage   = $product['image_thumb_url'] ?? $mainImage;
$brandName    = $product['brand_name'] ?? '';
$specs        = $product['specifications'] ?? '';
$isFeatured   = !empty($product['is_featured']);
$isNew        = !empty($product['is_new']);
$isBestseller = !empty($product['is_bestseller']);

// Build gallery — always include main image first
if (empty($images) && $mainImage) {
    $images = [['url' => $mainImage, 'thumb_url' => $thumbImage, 'alt_text' => $productName]];
}

$inStock = in_array($stockStatus, ['in_stock', ''], true);

$GLOBALS['PUB_APP_NAME']   = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH']  = '/frontend/public';

// Show a "not found" page if product is still null (no redirect — user sees 404)
if (!$product) {
    $GLOBALS['PUB_PAGE_TITLE'] = e(t('products.not_found_title', ['default' => 'Product Not Found'])) . ' — QOOQZ';
    include dirname(__DIR__) . '/partials/header.php';
    echo '<main class="pub-container" style="padding:60px 0;text-align:center;">';
    echo '<div class="pub-empty-icon" style="font-size:4rem;">🔍</div>';
    echo '<h1 style="margin:16px 0 8px;">' . e(t('products.not_found_title', ['default' => 'Product Not Found'])) . '</h1>';
    echo '<p style="color:var(--pub-muted);">' . e(t('products.not_found_msg', ['default' => 'This product is unavailable or does not exist.'])) . '</p>';
    echo '<a href="/frontend/public/products.php" class="pub-btn pub-btn--primary" style="margin-top:24px;display:inline-block;">'
       . e(t('nav.products')) . '</a>';
    echo '</main>';
    include dirname(__DIR__) . '/partials/footer.php';
    exit;
}

$GLOBALS['PUB_PAGE_TITLE'] = e($productName) . ' — QOOQZ';
$GLOBALS['PUB_PAGE_DESC']  = strip_tags($productDesc ?: $productName);

include dirname(__DIR__) . '/partials/header.php';
?>

<!-- Breadcrumb -->
<div class="pub-search-bar" style="padding:10px 0;">
    <div class="pub-container">
        <nav aria-label="breadcrumb" style="font-size:0.85rem;color:var(--pub-muted);">
            <a href="/frontend/public/index.php"><?= e(t('nav.home')) ?></a>
            <span style="margin:0 6px;">›</span>
            <a href="/frontend/public/products.php"><?= e(t('nav.products')) ?></a>
            <?php foreach ($categories as $cat): ?>
            <span style="margin:0 6px;">›</span>
            <a href="/frontend/public/products.php?category_id=<?= (int)$cat['id'] ?>"><?= e($cat['name'] ?? '') ?></a>
            <?php endforeach; ?>
            <span style="margin:0 6px;">›</span>
            <span><?= e($productName) ?></span>
        </nav>
    </div>
</div>

<!-- =============================================
     PRODUCT DETAIL
============================================= -->
<main class="pub-container" style="padding-top:28px;padding-bottom:40px;">

    <div class="pub-product-detail">

        <!-- Gallery -->
        <div class="pub-product-gallery">
            <div class="pub-gallery-main" id="pubGalleryMain">
                <?php if ($mainImage): ?>
                <img src="<?= e(pub_img($mainImage)) ?>"
                     alt="<?= e($productName) ?>" id="pubMainImg"
                     class="pub-gallery-main-img" loading="eager">
                <?php else: ?>
                <div class="pub-gallery-placeholder" aria-hidden="true">🖼️</div>
                <?php endif; ?>
                <!-- Badges -->
                <div class="pub-gallery-badges">
                    <?php if ($isFeatured): ?><span class="pub-product-badge"><?= e(t('products.featured')) ?></span><?php endif; ?>
                    <?php if ($isNew): ?><span class="pub-product-badge" style="background:var(--pub-secondary);"><?= e(t('products.new')) ?></span><?php endif; ?>
                    <?php if ($isBestseller): ?><span class="pub-product-badge" style="background:var(--pub-accent);"><?= e(t('products.bestseller')) ?></span><?php endif; ?>
                </div>
            </div>
            <?php if (count($images) > 1): ?>
            <div class="pub-gallery-thumbs" id="pubGalleryThumbs">
                <?php foreach ($images as $img): ?>
                <img src="<?= e(pub_img($img['thumb_url'] ?? $img['url'])) ?>"
                     alt="<?= e($img['alt_text'] ?? $productName) ?>"
                     class="pub-gallery-thumb"
                     loading="lazy"
                     data-full="<?= e(pub_img($img['url'])) ?>"
                     onclick="document.getElementById('pubMainImg').src=this.dataset.full;
                              document.querySelectorAll('.pub-gallery-thumb').forEach(function(t){t.classList.remove('active')});
                              this.classList.add('active');">
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Info -->
        <div class="pub-product-info">

            <?php if ($brandName): ?>
            <p class="pub-product-brand"><?= e($brandName) ?></p>
            <?php endif; ?>

            <h1 class="pub-product-detail-title"><?= e($productName) ?></h1>

            <!-- Rating -->
            <?php if (!empty($product['rating_count'])): ?>
            <div class="pub-product-rating">
                <span class="pub-stars">★★★★★</span>
                <span style="font-size:0.85rem;color:var(--pub-muted);">
                    <?= number_format((float)($product['rating_average'] ?? 0), 1) ?>
                    (<?= (int)$product['rating_count'] ?> <?= e(t('products.reviews')) ?>)
                </span>
            </div>
            <?php endif; ?>

            <!-- Price -->
            <?php if ($price !== null): ?>
            <div class="pub-product-price-block">
                <span class="pub-product-detail-price">
                    <?= number_format((float)$price, 2) ?> <?= e($currency) ?>
                </span>
                <?php if ($comparePrice && (float)$comparePrice > (float)$price): ?>
                <span class="pub-product-compare-price">
                    <?= number_format((float)$comparePrice, 2) ?> <?= e($currency) ?>
                </span>
                <span class="pub-product-discount-pct">
                    <?= round((1 - $price / $comparePrice) * 100) ?>% <?= e(t('products.off')) ?>
                </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Stock -->
            <div class="pub-product-stock <?= $inStock ? 'in-stock' : 'out-stock' ?>">
                <?php if ($inStock): ?>
                ✅ <?= e(t('products.in_stock')) ?>
                <?php if ($stockQty > 0 && $stockQty <= 10): ?>
                — <span style="color:var(--pub-accent);">
                    <?= e(t('products.low_stock', ['count' => $stockQty])) ?>
                </span>
                <?php endif; ?>
                <?php else: ?>
                ❌ <?= e(t('products.out_of_stock')) ?>
                <?php endif; ?>
            </div>

            <!-- Short description -->
            <?php if (!empty($product['short_description'])): ?>
            <p class="pub-product-short-desc"><?= e($product['short_description']) ?></p>
            <?php endif; ?>

            <!-- Add to cart -->
            <?php if ($inStock): ?>
            <div class="pub-product-cart-row">
                <div class="pub-qty-wrap">
                    <button class="pub-qty-btn" onclick="pubQtyChange(-1)" type="button">−</button>
                    <input type="number" id="pubQtyInput" class="pub-qty-input" value="1" min="1"
                           max="<?= $stockQty > 0 ? (int)$stockQty : 999 ?>">
                    <button class="pub-qty-btn" onclick="pubQtyChange(1)" type="button">+</button>
                </div>
                <button class="pub-btn pub-btn--primary pub-add-to-cart"
                        id="pubAddToCartBtn"
                        data-product-id="<?= (int)$product['id'] ?>"
                        data-product-name="<?= e($productName) ?>"
                        data-product-price="<?= e((string)($price ?? '0')) ?>"
                        data-product-image="<?= e(pub_img($mainImage)) ?>"
                        data-currency="<?= e($currency) ?>"
                        data-added-text="✅ <?= e(t('cart.added')) ?>"
                        onclick="pubAddToCart(this)">
                    🛒 <?= e(t('cart.add')) ?>
                </button>
            </div>
            <?php endif; ?>

            <!-- Categories -->
            <?php if (!empty($categories)): ?>
            <div class="pub-product-cats">
                <span class="pub-product-cat-label"><?= e(t('products.categories')) ?>:</span>
                <?php foreach ($categories as $cat): ?>
                <a href="/frontend/public/products.php?category_id=<?= (int)$cat['id'] ?>"
                   class="pub-cat-tag"><?= e($cat['name'] ?? '') ?></a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- SKU -->
            <?php if (!empty($product['sku'])): ?>
            <p style="font-size:0.82rem;color:var(--pub-muted);margin-top:8px;">
                <?= e(t('products.sku')) ?>: <span><?= e($product['sku']) ?></span>
            </p>
            <?php endif; ?>

        </div><!-- /.pub-product-info -->
    </div><!-- /.pub-product-detail -->

    <!-- =============================================
         DESCRIPTION / SPECS TABS
    ============================================= -->
    <?php if ($productDesc || $specs): ?>
    <div class="pub-section" style="padding:28px 0 0;">
        <div class="pub-tabs" id="pubDetailTabs">
            <?php if ($productDesc): ?>
            <button class="pub-tab active" onclick="pubTabSwitch(this,'pubDescPanel')" type="button">
                <?= e(t('products.description')) ?>
            </button>
            <?php endif; ?>
            <?php if ($specs): ?>
            <button class="pub-tab<?= $productDesc ? '' : ' active' ?>"
                    onclick="pubTabSwitch(this,'pubSpecPanel')" type="button">
                <?= e(t('products.specifications')) ?>
            </button>
            <?php endif; ?>
        </div>

        <?php if ($productDesc): ?>
        <div id="pubDescPanel" class="pub-tab-panel" style="padding:18px 0;line-height:1.8;color:var(--pub-text);">
            <?= nl2br(e($productDesc)) ?>
        </div>
        <?php endif; ?>

        <?php if ($specs): ?>
        <div id="pubSpecPanel" class="pub-tab-panel" style="<?= $productDesc ? 'display:none; ' : '' ?>padding:18px 0;">
            <?= nl2br(e($specs)) ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- =============================================
         RELATED PRODUCTS
    ============================================= -->
    <?php if (!empty($related)): ?>
    <section class="pub-section">
        <div class="pub-section-head">
            <h2 class="pub-section-title"><?= e(t('products.related')) ?></h2>
        </div>
        <div class="pub-grid">
            <?php foreach ($related as $p): ?>
            <a href="/frontend/public/product.php?id=<?= (int)($p['id'] ?? 0) ?>"
               class="pub-product-card" style="text-decoration:none;">
                <div class="pub-cat-img-wrap" style="aspect-ratio:1;">
                    <?php if (!empty($p['image_url'])): ?>
                    <img src="<?= e(pub_img($p['image_url'], 'product')) ?>"
                         alt="<?= e($p['name'] ?? '') ?>" class="pub-cat-img" loading="lazy"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                    <span class="pub-img-placeholder" style="display:none;" aria-hidden="true">🖼️</span>
                    <?php else: ?>
                    <span class="pub-img-placeholder" aria-hidden="true">🖼️</span>
                    <?php endif; ?>
                </div>
                <div class="pub-product-card-body">
                    <p class="pub-product-name"><?= e($p['name'] ?? '') ?></p>
                    <?php if (!empty($p['price'])): ?>
                    <p class="pub-product-price">
                        <?= number_format((float)$p['price'], 2) ?> <?= e($p['currency_code'] ?? '') ?>
                    </p>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

</main>

<script>
/* Tab switcher */
function pubTabSwitch(btn, panelId) {
    document.querySelectorAll('.pub-tab').forEach(function(t) { t.classList.remove('active'); });
    document.querySelectorAll('.pub-tab-panel').forEach(function(p) { p.style.display = 'none'; });
    btn.classList.add('active');
    var panel = document.getElementById(panelId);
    if (panel) panel.style.display = '';
}

/* Gallery thumb active on load */
document.addEventListener('DOMContentLoaded', function() {
    var first = document.querySelector('.pub-gallery-thumb');
    if (first) first.classList.add('active');
});
</script>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>
