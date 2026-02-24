<?php
declare(strict_types=1);
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
$GLOBALS['PUB_PAGE_TITLE'] = $lang === 'ar' ? 'المنتجات — QOOQZ' : 'Products — QOOQZ';

/* Filters */
$page    = max(1, (int)($_GET['page'] ?? 1));
$limit   = 20;
$search  = trim($_GET['q'] ?? '');
$brandId = (int)($_GET['brand_id'] ?? 0);
$catId   = (int)($_GET['category_id'] ?? 0);
$sort    = in_array($_GET['sort'] ?? '', ['price_asc','price_desc','newest'], true) ? $_GET['sort'] : 'newest';

$t = fn(string $ar, string $en) => $lang === 'ar' ? $ar : $en;

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
$products = $resp['data']['items'] ?? ($resp['data']['data'] ?? []);
$meta     = $resp['data']['meta']  ?? [];
$total    = (int)($meta['total'] ?? count($products));
$totalPg  = (int)($meta['total_pages'] ?? ceil($total / $limit));

/* Demo fallback */
if (empty($products)) {
    $products = [
        ['id'=>1, 'name'=>$t('جوال سامسونج S24 Ultra','Samsung S24 Ultra'), 'price'=>3499, 'currency'=>'SAR', 'is_featured'=>1, 'sku'=>'SM-S928'],
        ['id'=>2, 'name'=>$t('لابتوب ديل XPS 15','Dell XPS 15'),           'price'=>6999, 'currency'=>'SAR', 'is_featured'=>0, 'sku'=>'XPS-9530'],
        ['id'=>3, 'name'=>$t('سماعة سوني WH-1000XM5','Sony WH-1000XM5'),  'price'=>899,  'currency'=>'SAR', 'is_featured'=>1, 'sku'=>'WH1000XM5'],
        ['id'=>4, 'name'=>$t('كاميرا كانون EOS R50','Canon EOS R50'),      'price'=>4200, 'currency'=>'SAR', 'is_featured'=>0, 'sku'=>'EOSR50'],
        ['id'=>5, 'name'=>$t('تابلت آيباد برو 12.9','iPad Pro 12.9'),      'price'=>5499, 'currency'=>'SAR', 'is_featured'=>1, 'sku'=>'IPADPRO129'],
        ['id'=>6, 'name'=>$t('ساعة آبل Series 9','Apple Watch Series 9'),  'price'=>1799, 'currency'=>'SAR', 'is_featured'=>0, 'sku'=>'AWS9'],
        ['id'=>7, 'name'=>$t('شاشة سامسونج 4K 55"','Samsung 4K 55" Monitor'),'price'=>2299,'currency'=>'SAR','is_featured'=>0,'sku'=>'SMS4K55'],
        ['id'=>8, 'name'=>$t('كيبورد لوجيتك MX Keys','Logitech MX Keys'),  'price'=>549,  'currency'=>'SAR', 'is_featured'=>0, 'sku'=>'MXKEYS'],
    ];
    $total   = count($products);
    $totalPg = 1;
}

include dirname(__DIR__) . '/partials/header.php';
?>

<div class="pub-container" style="padding-top:28px;">

    <!-- Breadcrumb -->
    <nav style="font-size:0.84rem;color:var(--pub-muted);margin-bottom:20px;" aria-label="breadcrumb">
        <a href="/frontend/public/index.php"><?= $t('الرئيسية','Home') ?></a>
        <span style="margin:0 6px;">›</span>
        <span><?= $t('المنتجات','Products') ?></span>
    </nav>

    <!-- Page title -->
    <div class="pub-section-head" style="margin-bottom:16px;">
        <h1 style="font-size:1.4rem;margin:0;"><?= $t('🛍️ المنتجات','🛍️ Products') ?></h1>
        <span style="font-size:0.85rem;color:var(--pub-muted);">
            <?= number_format($total) ?> <?= $t('منتج','product(s)') ?>
        </span>
    </div>

    <!-- Filter bar -->
    <form method="get" class="pub-filter-bar">
        <input type="search" name="q" class="pub-search-input" style="max-width:260px;"
               placeholder="<?= $t('بحث في المنتجات...','Search products...') ?>"
               value="<?= e($search) ?>">

        <select name="sort" class="pub-filter-select" data-auto-submit>
            <option value="newest" <?= $sort==='newest'?'selected':'' ?>><?= $t('الأحدث','Newest') ?></option>
            <option value="price_asc" <?= $sort==='price_asc'?'selected':'' ?>><?= $t('السعر: الأقل أولاً','Price: Low to High') ?></option>
            <option value="price_desc" <?= $sort==='price_desc'?'selected':'' ?>><?= $t('السعر: الأعلى أولاً','Price: High to Low') ?></option>
        </select>

        <button type="submit" class="pub-btn pub-btn--primary pub-btn--sm"><?= $t('تصفية','Filter') ?></button>

        <?php if ($search || $sort !== 'newest' || $brandId || $catId): ?>
            <a href="/frontend/public/products.php" class="pub-btn pub-btn--ghost pub-btn--sm"><?= $t('مسح','Clear') ?></a>
        <?php endif; ?>
    </form>

    <!-- Grid -->
    <?php if (!empty($products)): ?>
    <div class="pub-grid">
        <?php foreach ($products as $p): ?>
        <a href="/frontend/public/products.php?id=<?= (int)($p['id'] ?? 0) ?>"
           class="pub-product-card" style="text-decoration:none;" aria-label="<?= e($p['name'] ?? '') ?>">
            <div class="pub-product-card-img-placeholder" aria-hidden="true">🖼️</div>
            <div class="pub-product-card-body">
                <?php if (!empty($p['is_featured'])): ?>
                    <span class="pub-product-badge"><?= $t('مميز','Featured') ?></span>
                <?php endif; ?>
                <p class="pub-product-name"><?= e($p['name'] ?? '') ?></p>
                <?php if (!empty($p['sku'])): ?>
                    <small style="color:var(--pub-muted);font-size:0.76rem;"><?= e($p['sku']) ?></small>
                <?php endif; ?>
                <?php if (!empty($p['price'])): ?>
                    <p class="pub-product-price">
                        <?= number_format((float)$p['price'], 2) ?>
                        <small><?= e($p['currency'] ?? 'SAR') ?></small>
                    </p>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPg > 1): ?>
    <nav class="pub-pagination" aria-label="<?= $t('التنقل بين الصفحات','Pagination') ?>">
        <?php
        $base_qs = http_build_query(array_filter(['q'=>$search,'sort'=>$sort,'brand_id'=>$brandId,'category_id'=>$catId]));
        $pg_url  = fn(int $pg) => '?' . ($base_qs ? $base_qs . '&' : '') . 'page=' . $pg;
        ?>
        <a href="<?= $pg_url(max(1,$page-1)) ?>"
           class="pub-page-btn <?= $page<=1?'disabled':'' ?>"><?= $t('السابق','Prev') ?></a>
        <?php for ($i = max(1,$page-2); $i <= min($totalPg,$page+2); $i++): ?>
            <a href="<?= $pg_url($i) ?>"
               class="pub-page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <a href="<?= $pg_url(min($totalPg,$page+1)) ?>"
           class="pub-page-btn <?= $page>=$totalPg?'disabled':'' ?>"><?= $t('التالي','Next') ?></a>
    </nav>
    <?php endif; ?>

    <?php else: ?>
    <div class="pub-empty">
        <div class="pub-empty-icon">🛍️</div>
        <p class="pub-empty-msg"><?= $t('لا توجد منتجات متاحة حالياً','No products available at the moment') ?></p>
    </div>
    <?php endif; ?>

</div>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>
