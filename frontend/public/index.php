<?php
declare(strict_types=1);
/**
 * frontend/public/index.php
 * QOOQZ — Global Public Homepage
 * Displays: Categories · Products · Jobs · Entities · Tenants
 */

require_once dirname(__DIR__) . '/includes/public_context.php';

$ctx      = $GLOBALS['PUB_CONTEXT'];
$lang     = $ctx['lang'];
$dir      = $ctx['dir'];
$theme    = $ctx['theme'];
$tenantId = $ctx['tenant_id'];

$GLOBALS['PUB_APP_NAME']   = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH']  = '/frontend/public';
$GLOBALS['PUB_PAGE_TITLE'] = t('hero.title') . ' — QOOQZ';
$GLOBALS['PUB_PAGE_DESC']  = t('hero.subtitle');

/* -------------------------------------------------------
 * Fetch data sections
 * ----------------------------------------------------- */
$base = pub_api_url('');
$qs   = 'lang=' . urlencode($lang) . '&limit=8&page=1&tenant_id=' . $tenantId;

$featuredProducts  = [];
$featuredCategories= [];
$latestJobs        = [];
$featuredEntities  = [];
$featuredTenants   = [];

// Products
$r = pub_fetch($base . 'public/products?' . $qs);
$featuredProducts = $r['data']['items'] ?? ($r['data']['data'] ?? []);
$featuredProducts = array_slice($featuredProducts, 0, 8);

// Categories
$rc = pub_fetch($base . 'public/categories?lang=' . urlencode($lang) . '&limit=8&tenant_id=' . $tenantId . '&featured=1');
$featuredCategories = $rc['data']['items'] ?? ($rc['data']['data'] ?? []);
$featuredCategories = array_slice($featuredCategories, 0, 8);

// Jobs
$rj = pub_fetch($base . 'public/jobs?lang=' . urlencode($lang) . '&limit=6&featured=1');
$latestJobs = $rj['data']['items'] ?? ($rj['data']['data'] ?? []);
$latestJobs = array_slice($latestJobs, 0, 6);

// Entities
$re = pub_fetch($base . 'public/entities?' . $qs . '&limit=6');
$featuredEntities = $re['data']['items'] ?? ($re['data']['data'] ?? []);
$featuredEntities = array_slice($featuredEntities, 0, 6);

// Tenants
$rt = pub_fetch($base . 'public/tenants?' . $qs . '&limit=6');
$featuredTenants = $rt['data']['items'] ?? ($rt['data']['data'] ?? []);
$featuredTenants = array_slice($featuredTenants, 0, 6);

// Stats
$totalProducts   = (int)($r['data']['meta']['total']  ?? count($featuredProducts));
$totalCategories = (int)($rc['data']['meta']['total'] ?? count($featuredCategories));
$totalJobs       = (int)($rj['data']['meta']['total'] ?? count($latestJobs));
$totalEntities   = (int)($re['data']['meta']['total'] ?? count($featuredEntities));
$totalTenants    = (int)($rt['data']['meta']['total'] ?? count($featuredTenants));

/* -------------------------------------------------------
 * Demo fallback when API unavailable
 * ----------------------------------------------------- */
if (empty($featuredProducts)) {
    $featuredProducts = [
        ['id'=>1, 'name'=>$lang==='ar'?'جوال سامسونج S24':'Samsung S24',       'price'=>3499, 'currency'=>'SAR', 'is_featured'=>1, 'image_url'=>'/uploads/images/img_697288dfab0213.18814948.jpg'],
        ['id'=>2, 'name'=>$lang==='ar'?'لابتوب ديل XPS':'Dell XPS Laptop',    'price'=>6999, 'currency'=>'SAR', 'is_featured'=>1, 'image_url'=>null],
        ['id'=>3, 'name'=>$lang==='ar'?'سماعة سوني WH':'Sony WH Headphones', 'price'=>899,  'currency'=>'SAR', 'is_featured'=>1, 'image_url'=>null],
        ['id'=>4, 'name'=>$lang==='ar'?'كاميرا كانون':'Canon Camera',         'price'=>4200, 'currency'=>'SAR', 'is_featured'=>0, 'image_url'=>null],
    ];
}
if (empty($featuredCategories)) {
    $featuredCategories = [
        ['id'=>1, 'name'=>$lang==='ar'?'الإلكترونيات':'Electronics', 'image_url'=>'/uploads/images/img_697288dfab0213.18814948.jpg', 'is_featured'=>1, 'product_count'=>125],
        ['id'=>2, 'name'=>$lang==='ar'?'الملابس':'Clothing',          'image_url'=>'/uploads/images/img_69728365b9a6a4.90230041.png', 'is_featured'=>1, 'product_count'=>88],
        ['id'=>3, 'name'=>$lang==='ar'?'المنزل':'Home & Garden',       'image_url'=>null, 'is_featured'=>0, 'product_count'=>42],
        ['id'=>4, 'name'=>$lang==='ar'?'الرياضة':'Sports',             'image_url'=>null, 'is_featured'=>0, 'product_count'=>67],
    ];
}
if (empty($latestJobs)) {
    $latestJobs = [
        ['id'=>1, 'title'=>$lang==='ar'?'مطور واجهة أمامية':'Frontend Developer', 'employment_type'=>'full_time', 'is_remote'=>1, 'is_featured'=>1, 'is_urgent'=>0, 'city_name'=>$lang==='ar'?'الرياض':'Riyadh'],
        ['id'=>2, 'title'=>$lang==='ar'?'مدير تسويق رقمي':'Digital Marketing Mgr', 'employment_type'=>'full_time', 'is_remote'=>0, 'is_featured'=>0, 'is_urgent'=>1, 'city_name'=>$lang==='ar'?'جدة':'Jeddah'],
        ['id'=>3, 'title'=>$lang==='ar'?'مبرمج PHP':'PHP Developer',              'employment_type'=>'contract',  'is_remote'=>1, 'is_featured'=>1, 'is_urgent'=>0, 'city_name'=>$lang==='ar'?'الدمام':'Dammam'],
    ];
}
if (empty($featuredEntities)) {
    $featuredEntities = [
        ['id'=>1, 'store_name'=>$lang==='ar'?'شركة التقنية العالمية':'Global Tech Co.', 'is_verified'=>1, 'vendor_type'=>'company', 'logo_url'=>''],
        ['id'=>2, 'store_name'=>$lang==='ar'?'متجر الأزياء الفاخرة':'Luxury Fashion',  'is_verified'=>0, 'vendor_type'=>'store',   'logo_url'=>''],
        ['id'=>3, 'store_name'=>$lang==='ar'?'مركز التدريب المتقدم':'Training Center',  'is_verified'=>1, 'vendor_type'=>'training','logo_url'=>''],
    ];
}
if (empty($featuredTenants)) {
    $featuredTenants = [
        ['id'=>1, 'store_name'=>$lang==='ar'?'تك هب':'TechHub',        'domain'=>'techhub.example.com', 'is_active'=>1],
        ['id'=>2, 'store_name'=>$lang==='ar'?'متجر الموضة':'Fashion',   'domain'=>'fashion.example.com', 'is_active'=>1],
    ];
}

$totalProducts   = $totalProducts   ?: count($featuredProducts);
$totalCategories = $totalCategories ?: count($featuredCategories);
$totalJobs       = $totalJobs       ?: count($latestJobs);
$totalEntities   = $totalEntities   ?: count($featuredEntities);
$totalTenants    = $totalTenants    ?: count($featuredTenants);

include dirname(__DIR__) . '/partials/header.php';
?>

<!-- =============================================
     HERO
============================================= -->
<section class="pub-hero">
    <div class="pub-container">
        <h1><?= e(t('hero.title')) ?></h1>
        <p><?= e(t('hero.subtitle')) ?></p>
        <div class="pub-hero-actions">
            <a href="/frontend/public/products.php" class="pub-btn pub-btn--primary">
                🛍️ <?= e(t('hero.browse_products')) ?>
            </a>
            <a href="/frontend/public/jobs.php" class="pub-btn pub-btn--outline">
                💼 <?= e(t('hero.explore_jobs')) ?>
            </a>
        </div>
    </div>
</section>

<!-- =============================================
     SEARCH BAR
============================================= -->
<div class="pub-search-bar">
    <div class="pub-container">
        <form class="pub-search-form" method="get" action="/frontend/public/products.php" id="pubSearchForm">
            <input type="search" name="q" class="pub-search-input"
                   placeholder="<?= e(t('search.placeholder')) ?>"
                   value="<?= e($_GET['q'] ?? '') ?>">
            <button type="submit" class="pub-search-btn"><?= e(t('search.button')) ?></button>
        </form>
    </div>
</div>

<!-- =============================================
     STATS ROW
============================================= -->
<div class="pub-container">
    <div class="pub-stats-row">
        <div class="pub-stat-item">
            <span class="pub-stat-value" data-target="<?= (int)$totalProducts ?>"><?= number_format((int)$totalProducts) ?>+</span>
            <span class="pub-stat-label"><?= e(t('stats.products')) ?></span>
        </div>
        <div class="pub-stat-item">
            <span class="pub-stat-value" data-target="<?= (int)$totalCategories ?>"><?= number_format((int)$totalCategories) ?>+</span>
            <span class="pub-stat-label"><?= e(t('nav.categories')) ?></span>
        </div>
        <div class="pub-stat-item">
            <span class="pub-stat-value" data-target="<?= (int)$totalJobs ?>"><?= number_format((int)$totalJobs) ?>+</span>
            <span class="pub-stat-label"><?= e(t('stats.jobs')) ?></span>
        </div>
        <div class="pub-stat-item">
            <span class="pub-stat-value" data-target="<?= (int)$totalEntities ?>"><?= number_format((int)$totalEntities) ?>+</span>
            <span class="pub-stat-label"><?= e(t('stats.entities')) ?></span>
        </div>
        <div class="pub-stat-item">
            <span class="pub-stat-value" data-target="<?= (int)$totalTenants ?>"><?= number_format((int)$totalTenants) ?>+</span>
            <span class="pub-stat-label"><?= e(t('stats.tenants')) ?></span>
        </div>
    </div>
</div>

<!-- =============================================
     FEATURED CATEGORIES
============================================= -->
<?php if (!empty($featuredCategories)): ?>
<section class="pub-section">
    <div class="pub-container">
        <div class="pub-section-head">
            <h2 class="pub-section-title"><?= e(t('sections.categories')) ?></h2>
            <a href="/frontend/public/categories.php" class="pub-section-link"><?= e(t('sections.view_all')) ?></a>
        </div>
        <div class="pub-grid-cat">
            <?php foreach ($featuredCategories as $cat): ?>
            <a href="/frontend/public/products.php?category_id=<?= (int)($cat['id'] ?? 0) ?>"
               class="pub-cat-card<?= !empty($cat['is_featured']) ? ' pub-cat-card--featured' : '' ?>"
               style="text-decoration:none;">
                <div class="pub-cat-img-wrap">
                    <?php if (!empty($cat['image_url'])): ?>
                        <img src="<?= e(pub_img($cat['image_url'], 'category')) ?>"
                             alt="<?= e($cat['name'] ?? '') ?>" class="pub-cat-img" loading="lazy"
                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                        <span class="pub-img-placeholder" style="display:none;" aria-hidden="true">📂</span>
                    <?php else: ?>
                        <span class="pub-img-placeholder" aria-hidden="true">📂</span>
                    <?php endif; ?>
                </div>
                <div class="pub-cat-body">
                    <h3 class="pub-cat-name"><?= e($cat['name'] ?? '') ?></h3>
                    <?php if (!empty($cat['product_count'])): ?>
                        <span class="pub-cat-count"><?= (int)$cat['product_count'] ?> <?= e(t('categories.products_count')) ?></span>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- =============================================
     FEATURED PRODUCTS
============================================= -->
<?php if (!empty($featuredProducts)): ?>
<section class="pub-section" style="background:var(--pub-surface);">
    <div class="pub-container">
        <div class="pub-section-head">
            <h2 class="pub-section-title"><?= e(t('sections.featured_products')) ?></h2>
            <a href="/frontend/public/products.php" class="pub-section-link"><?= e(t('sections.view_all')) ?></a>
        </div>
        <div class="pub-grid">
            <?php foreach ($featuredProducts as $p): ?>
            <a href="/frontend/public/products.php?id=<?= (int)($p['id'] ?? 0) ?>"
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
                    <?php if (!empty($p['is_featured'])): ?>
                        <span class="pub-product-badge"><?= e(t('products.featured')) ?></span>
                    <?php endif; ?>
                    <p class="pub-product-name"><?= e($p['name'] ?? '') ?></p>
                    <?php if (!empty($p['price'])): ?>
                        <p class="pub-product-price">
                            <?= number_format((float)$p['price'], 2) ?>
                            <?= e($p['currency'] ?? t('common.currency')) ?>
                        </p>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- =============================================
     LATEST JOBS
============================================= -->
<?php if (!empty($latestJobs)): ?>
<section class="pub-section">
    <div class="pub-container">
        <div class="pub-section-head">
            <h2 class="pub-section-title"><?= e(t('sections.latest_jobs')) ?></h2>
            <a href="/frontend/public/jobs.php" class="pub-section-link"><?= e(t('sections.view_all')) ?></a>
        </div>
        <div class="pub-grid-lg">
            <?php foreach ($latestJobs as $j): ?>
            <a href="/frontend/public/jobs.php?id=<?= (int)($j['id'] ?? 0) ?>"
               class="pub-job-card" style="text-decoration:none;">
                <h3 class="pub-job-title"><?= e($j['title'] ?? '') ?></h3>
                <div class="pub-job-meta">
                    <?php if (!empty($j['city_name'])): ?>
                        <span>📍 <?= e($j['city_name']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($j['employment_type'])): ?>
                        <span>🕐 <?= e(str_replace('_', ' ', $j['employment_type'])) ?></span>
                    <?php endif; ?>
                </div>
                <div class="pub-job-tags">
                    <?php if (!empty($j['is_featured'])): ?><span class="pub-tag pub-tag--featured"><?= e(t('jobs.featured')) ?></span><?php endif; ?>
                    <?php if (!empty($j['is_urgent'])): ?><span class="pub-tag pub-tag--urgent"><?= e(t('jobs.urgent')) ?></span><?php endif; ?>
                    <?php if (!empty($j['is_remote'])): ?><span class="pub-tag pub-tag--remote"><?= e(t('jobs.remote')) ?></span><?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- =============================================
     ENTITIES
============================================= -->
<?php if (!empty($featuredEntities)): ?>
<section class="pub-section" style="background:var(--pub-surface);">
    <div class="pub-container">
        <div class="pub-section-head">
            <h2 class="pub-section-title"><?= e(t('sections.entities')) ?></h2>
            <a href="/frontend/public/entities.php" class="pub-section-link"><?= e(t('sections.view_all')) ?></a>
        </div>
        <div class="pub-grid-md">
            <?php foreach ($featuredEntities as $ent): ?>
            <a href="/frontend/public/entities.php?id=<?= (int)($ent['id'] ?? 0) ?>"
               class="pub-entity-card" style="text-decoration:none;">
                <div class="pub-entity-avatar">
                    <?php if (!empty($ent['logo_url'])): ?>
                        <img src="<?= e(pub_img($ent['logo_url'], 'entity_logo')) ?>"
                             alt="<?= e($ent['store_name'] ?? '') ?>" loading="lazy"
                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                        <span style="display:none;">🏢</span>
                    <?php else: ?>
                        🏢
                    <?php endif; ?>
                </div>
                <div class="pub-entity-info">
                    <p class="pub-entity-name"><?= e($ent['store_name'] ?? $ent['name'] ?? '') ?></p>
                    <?php if (!empty($ent['vendor_type'])): ?>
                        <p class="pub-entity-desc"><?= e($ent['vendor_type']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($ent['is_verified'])): ?>
                        <span class="pub-entity-verified">✅ <?= e(t('entities.verified')) ?></span>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- =============================================
     TENANTS
============================================= -->
<?php if (!empty($featuredTenants)): ?>
<section class="pub-section">
    <div class="pub-container">
        <div class="pub-section-head">
            <h2 class="pub-section-title"><?= e(t('sections.tenants')) ?></h2>
            <a href="/frontend/public/tenants.php" class="pub-section-link"><?= e(t('sections.view_all')) ?></a>
        </div>
        <div class="pub-grid-md">
            <?php foreach ($featuredTenants as $ten): ?>
            <a href="/frontend/public/tenants.php?id=<?= (int)($ten['id'] ?? 0) ?>"
               class="pub-entity-card" style="text-decoration:none;">
                <div class="pub-entity-avatar">🏪</div>
                <div class="pub-entity-info">
                    <p class="pub-entity-name"><?= e($ten['store_name'] ?? $ten['name'] ?? '') ?></p>
                    <?php if (!empty($ten['domain'])): ?>
                        <p class="pub-entity-desc"><?= e($ten['domain']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($ten['is_active'])): ?>
                        <span class="pub-entity-verified">🟢 <?= e(t('tenants.active')) ?></span>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>
