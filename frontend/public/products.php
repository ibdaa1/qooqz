<?php
/**
 * frontend/public/products.php
 * QOOQZ — Public Products Listing Page
 */

require_once dirname(__DIR__) . '/includes/public_context.php';

$ctx      = $GLOBALS['PUB_CONTEXT'];
$lang     = $ctx['lang'];
$tenantId = $ctx['tenant_id'];

$GLOBALS['PUB_APP_NAME']   = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH']  = '/frontend/public';
$GLOBALS['PUB_PAGE_TITLE'] = t('products.page_title') . ' — QOOQZ';

/* Filters */
$page    = max(1, (int)($_GET['page'] ?? 1));
$limit   = 20;
$search  = trim($_GET['q'] ?? '');
$brandId = (int)($_GET['brand_id'] ?? 0);
$catId   = (int)($_GET['category_id'] ?? 0);
$sort    = in_array($_GET['sort'] ?? '', ['price_asc','price_desc','newest'], true) ? $_GET['sort'] : 'newest';

/* Fetch */
$qs = http_build_query(array_filter([
    'lang'        => $lang,
    'page'        => $page,
    'limit'       => $limit,
    'tenant_id'   => $tenantId,
    'brand_id'    => $brandId ?: null,
    'category_id' => $catId ?: null,
    'search'      => $search ?: null,
]));

$resp     = pub_fetch(pub_api_url('public/products') . '?' . $qs);
$products = $resp['data']['data'] ?? ($resp['data']['items'] ?? []);
$meta     = $resp['data']['meta']  ?? [];
$total    = (int)($meta['total'] ?? count($products));
$totalPg  = (int)($meta['total_pages'] ?? (($limit > 0 && $total > 0) ? (int)ceil($total / $limit) : 1));

include dirname(__DIR__) . '/partials/header.php';
?>

<div class="pub-container" style="padding-top:28px;">

    <!-- Breadcrumb -->
    <nav style="font-size:0.84rem;color:var(--pub-muted);margin-bottom:20px;" aria-label="breadcrumb">
        <a href="/frontend/public/index.php"><?= e(t('common.home')) ?></a>
        <span style="margin:0 6px;">›</span>
        <span><?= e(t('nav.products')) ?></span>
    </nav>

    <!-- Page title -->
    <div class="pub-section-head" style="margin-bottom:16px;">
        <h1 style="font-size:1.4rem;margin:0;">🛍️ <?= e(t('nav.products')) ?></h1>
        <span style="font-size:0.85rem;color:var(--pub-muted);">
            <?= number_format($total) ?> <?= e(t('products.product_count')) ?>
        </span>
    </div>

    <!-- Filter bar -->
    <form method="get" class="pub-filter-bar">
        <input type="search" name="q" class="pub-search-input" style="max-width:260px;"
               placeholder="<?= e(t('products.search_placeholder')) ?>"
               value="<?= e($search) ?>">

        <select name="sort" class="pub-filter-select" data-auto-submit>
            <option value="newest" <?= $sort==='newest'?'selected':'' ?>><?= e(t('products.sort_newest')) ?></option>
            <option value="price_asc" <?= $sort==='price_asc'?'selected':'' ?>><?= e(t('products.sort_price_asc')) ?></option>
            <option value="price_desc" <?= $sort==='price_desc'?'selected':'' ?>><?= e(t('products.sort_price_desc')) ?></option>
        </select>

        <button type="submit" class="pub-btn pub-btn--primary pub-btn--sm"><?= e(t('products.filter')) ?></button>

        <?php if ($search || $sort !== 'newest' || $brandId || $catId): ?>
            <a href="/frontend/public/products.php" class="pub-btn pub-btn--ghost pub-btn--sm"><?= e(t('products.clear')) ?></a>
        <?php endif; ?>
    </form>

    <!-- Grid -->
    <?php if (!empty($products)): ?>
    <div class="pub-grid">
        <?php foreach ($products as $p): ?>
        <?php
            $pId    = (int)($p['id'] ?? 0);
            $pName  = $p['name'] ?? '';
            $pPrice = $p['price'] ?? null;
            $pCur   = $p['currency_code'] ?? t('common.currency');
            $imgSrc = pub_img($p['image_thumb_url'] ?? $p['image_url'] ?? null, 'product_thumb');
        ?>
        <div class="pub-product-card" style="position:relative;">
            <!-- Wishlist heart -->
            <button class="pub-wishlist-btn"
                    type="button"
                    data-product-id="<?= $pId ?>"
                    data-entity-id="<?= $p['entity_id'] ?? 1 ?>"
                    onclick="pubToggleWishlist(this)"
                    title="<?= e(t('wishlist.add')) ?>">♡</button>
            <a href="/frontend/public/product.php?id=<?= $pId ?>"
               style="text-decoration:none;display:flex;flex-direction:column;flex:1;"
               aria-label="<?= e($pName) ?>">
                <div class="pub-cat-img-wrap" style="aspect-ratio:1;">
                    <?php if ($imgSrc): ?>
                        <img src="<?= e($imgSrc) ?>"
                             alt="<?= e($pName) ?>" class="pub-cat-img" loading="lazy"
                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                        <span class="pub-img-placeholder" style="display:none;" aria-hidden="true">🖼️</span>
                    <?php else: ?>
                        <span class="pub-img-placeholder" aria-hidden="true">🖼️</span>
                    <?php endif; ?>
                </div>
                <div class="pub-product-card-body">
                    <?php if (!empty($p['is_featured'])): ?>
                        <span class="pub-product-badge"><?= e(t('products.featured')) ?></span>
                    <?php endif; ?>
                    <p class="pub-product-name"><?= e($pName) ?></p>
                    <?php if (!empty($p['sku'])): ?>
                        <small style="color:var(--pub-muted);font-size:0.76rem;"><?= e($p['sku']) ?></small>
                    <?php endif; ?>
                    <?php if ($pPrice !== null): ?>
                        <p class="pub-product-price">
                            <?= number_format((float)$pPrice, 2) ?>
                            <small><?= e($pCur) ?></small>
                        </p>
                    <?php endif; ?>
                    <?php
                    $pStock = $p['stock_status'] ?? '';
                    $pQty   = (int)($p['stock_quantity'] ?? 0);
                    if ($pStock === 'out_of_stock'):
                    ?>
                        <span class="pub-stock-badge pub-stock-badge--out"><?= e(t('products.out_of_stock')) ?></span>
                    <?php elseif ($pQty > 0 && $pQty <= 10): ?>
                        <span class="pub-stock-badge pub-stock-badge--low"><?= e(t('products.low_stock', ['count' => $pQty, 'default' => 'Only '.$pQty.' left'])) ?></span>
                    <?php elseif ($pStock === 'in_stock' || $pQty > 0): ?>
                        <span class="pub-stock-badge pub-stock-badge--in"><?= e(t('products.in_stock')) ?></span>
                    <?php endif; ?>
                </div>
            </a>
            <!-- Add to Cart button -->
            <div style="padding:0 14px 12px;">
                <button class="pub-btn pub-btn--primary pub-btn--sm"
                        style="width:100%;justify-content:center;"
                        type="button"
                        data-product-id="<?= $pId ?>"
                        data-product-name="<?= e($pName) ?>"
                        data-product-price="<?= e((string)($pPrice ?? '0')) ?>"
                        data-product-image="<?= e($imgSrc ?: '') ?>"
                        data-product-sku="<?= e($p['sku'] ?? '') ?>"
                        data-currency="<?= e($pCur) ?>"
                        data-added-text="✅ <?= e(t('cart.added')) ?>"
                        onclick="pubAddToCart(this)">
                    🛒 <?= e(t('cart.add')) ?>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPg > 1): ?>
    <nav class="pub-pagination" aria-label="Pagination">
        <?php
        $base_qs = http_build_query(array_filter(['q'=>$search,'sort'=>$sort,'brand_id'=>$brandId,'category_id'=>$catId]));
        $pg_url  = fn(int $pg) => '?' . ($base_qs ? $base_qs . '&' : '') . 'page=' . $pg;
        ?>
        <a href="<?= $pg_url(max(1,$page-1)) ?>"
           class="pub-page-btn <?= $page<=1?'disabled':'' ?>"><?= e(t('pagination.prev')) ?></a>
        <?php for ($i = max(1,$page-2); $i <= min($totalPg,$page+2); $i++): ?>
            <a href="<?= $pg_url($i) ?>"
               class="pub-page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <a href="<?= $pg_url(min($totalPg,$page+1)) ?>"
           class="pub-page-btn <?= $page>=$totalPg?'disabled':'' ?>"><?= e(t('pagination.next')) ?></a>
    </nav>
    <?php endif; ?>

    <?php else: ?>
    <div class="pub-empty">
        <div class="pub-empty-icon">🛍️</div>
        <p class="pub-empty-msg"><?= e(t('products.empty')) ?></p>
    </div>
    <?php endif; ?>

</div>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>
