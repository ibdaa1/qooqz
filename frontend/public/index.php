<?php
declare(strict_types=1);
/**
 * frontend/public/index.php
 * QOOQZ — Global Public Homepage
 * Displays: Products · Jobs · Entities · Tenants
 */

require_once dirname(__DIR__) . '/includes/public_context.php';

$ctx      = $GLOBALS['PUB_CONTEXT'];
$lang     = $ctx['lang'];
$dir      = $ctx['dir'];
$theme    = $ctx['theme'];
$tenantId = $ctx['tenant_id'];

$GLOBALS['PUB_APP_NAME']   = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH']  = '/frontend/public';
$GLOBALS['PUB_PAGE_TITLE'] = $lang === 'ar' ? 'QOOQZ — المنصة العالمية' : 'QOOQZ — Global Platform';
$GLOBALS['PUB_PAGE_DESC']  = $lang === 'ar'
    ? 'منصة QOOQZ العالمية: تسوق المنتجات، استكشف الوظائف، تعرف على الكيانات والمستأجرين'
    : 'QOOQZ global platform: shop products, explore jobs, discover entities and tenants';

/* -------------------------------------------------------
 * Fetch data sections (parallel fetch via helper)
 * ----------------------------------------------------- */
$base = pub_api_url('');
$qs   = 'lang=' . urlencode($lang) . '&limit=8&page=1&tenant_id=' . $tenantId;

$featuredProducts = [];
$latestJobs       = [];
$featuredEntities = [];
$featuredTenants  = [];
$stats            = [];

// Products
$r = pub_fetch($base . 'public/products?' . $qs);
if (!empty($r['data']['data'])) {
    $featuredProducts = array_slice($r['data']['data'], 0, 8);
} elseif (!empty($r['data']['items'])) {
    $featuredProducts = array_slice($r['data']['items'], 0, 8);
}

// Jobs (featured)
$rj = pub_fetch($base . 'jobs?featured=1&featured_limit=6&' . $qs);
if (!empty($rj['data']['items'])) {
    $latestJobs = $rj['data']['items'];
}

// Entities
$re = pub_fetch($base . 'entities?' . $qs . '&status=active&limit=6');
if (!empty($re['data']['items'])) {
    $featuredEntities = $re['data']['items'];
}

// Tenants
$rt = pub_fetch($base . 'tenants?' . $qs . '&limit=6');
if (!empty($rt['data']['items'])) {
    $featuredTenants = $rt['data']['items'];
}

// Stats
$totalProducts = $r['data']['meta']['total'] ?? count($featuredProducts);
$totalJobs     = $rj['data']['total'] ?? $rj['data']['meta']['total'] ?? count($latestJobs);
$totalEntities = $re['data']['meta']['total'] ?? count($featuredEntities);
$totalTenants  = $rt['data']['meta']['total'] ?? count($featuredTenants);

/* -------------------------------------------------------
 * Inline demo data (shown when API unavailable)
 * ----------------------------------------------------- */
if (empty($featuredProducts)) {
    $featuredProducts = [
        ['id'=>1, 'name'=>($lang==='ar'?'جوال سامسونج S24':'Samsung S24'), 'price'=>3499, 'currency'=>'SAR', 'is_featured'=>1],
        ['id'=>2, 'name'=>($lang==='ar'?'لابتوب ديل XPS':'Dell XPS Laptop'),    'price'=>6999, 'currency'=>'SAR', 'is_featured'=>1],
        ['id'=>3, 'name'=>($lang==='ar'?'سماعة سوني WH':'Sony WH Headphones'), 'price'=>899,  'currency'=>'SAR', 'is_featured'=>1],
        ['id'=>4, 'name'=>($lang==='ar'?'كاميرا كانون':'Canon Camera'),         'price'=>4200, 'currency'=>'SAR', 'is_featured'=>0],
    ];
}
if (empty($latestJobs)) {
    $latestJobs = [
        ['id'=>1, 'title'=>($lang==='ar'?'مطور واجهة أمامية':'Frontend Developer'), 'employment_type'=>'full_time', 'is_remote'=>1, 'is_featured'=>1, 'is_urgent'=>0, 'city_name'=>($lang==='ar'?'الرياض':'Riyadh')],
        ['id'=>2, 'title'=>($lang==='ar'?'مدير تسويق رقمي':'Digital Marketing Manager'), 'employment_type'=>'full_time', 'is_remote'=>0, 'is_featured'=>0, 'is_urgent'=>1, 'city_name'=>($lang==='ar'?'جدة':'Jeddah')],
        ['id'=>3, 'title'=>($lang==='ar'?'مبرمج PHP':'PHP Developer'), 'employment_type'=>'contract', 'is_remote'=>1, 'is_featured'=>1, 'is_urgent'=>0, 'city_name'=>($lang==='ar'?'الدمام':'Dammam')],
    ];
}
if (empty($featuredEntities)) {
    $featuredEntities = [
        ['id'=>1, 'store_name'=>($lang==='ar'?'شركة التقنية العالمية':'Global Tech Co.'), 'is_verified'=>1, 'vendor_type'=>'company', 'logo_url'=>''],
        ['id'=>2, 'store_name'=>($lang==='ar'?'متجر الأزياء الفاخرة':'Luxury Fashion'), 'is_verified'=>0, 'vendor_type'=>'store', 'logo_url'=>''],
        ['id'=>3, 'store_name'=>($lang==='ar'?'مركز التدريب المتقدم':'Advanced Training Center'), 'is_verified'=>1, 'vendor_type'=>'training', 'logo_url'=>''],
    ];
}
if (empty($featuredTenants)) {
    $featuredTenants = [
        ['id'=>1, 'name'=>'TechHub', 'store_name'=>($lang==='ar'?'تك هب':'TechHub'), 'domain'=>'techhub.example.com', 'is_active'=>1],
        ['id'=>2, 'name'=>'FashionStore', 'store_name'=>($lang==='ar'?'متجر الموضة':'Fashion Store'), 'domain'=>'fashion.example.com', 'is_active'=>1],
    ];
}

$totalProducts = $totalProducts ?: count($featuredProducts);
$totalJobs     = $totalJobs     ?: count($latestJobs);
$totalEntities = $totalEntities ?: count($featuredEntities);
$totalTenants  = $totalTenants  ?: count($featuredTenants);

/* -------------------------------------------------------
 * Text helpers
 * ----------------------------------------------------- */
$t = function (string $ar, string $en) use ($lang): string {
    return $lang === 'ar' ? $ar : $en;
};

include dirname(__DIR__) . '/partials/header.php';
?>

<!-- =============================================
     HERO
============================================= -->
<section class="pub-hero">
    <div class="pub-container">
        <h1><?= $t('منصة QOOQZ العالمية', 'QOOQZ Global Platform') ?></h1>
        <p><?= $t(
            'اكتشف المنتجات، الوظائف، الكيانات والمستأجرين في مكان واحد',
            'Discover products, jobs, entities and tenants — all in one place'
        ) ?></p>
        <div class="pub-hero-actions">
            <a href="/frontend/public/products.php" class="pub-btn pub-btn--primary">
                🛍️ <?= $t('تصفح المنتجات', 'Browse Products') ?>
            </a>
            <a href="/frontend/public/jobs.php" class="pub-btn pub-btn--outline">
                💼 <?= $t('الوظائف', 'Explore Jobs') ?>
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
                   placeholder="<?= $t('ابحث عن منتجات، وظائف، كيانات...', 'Search products, jobs, entities...') ?>"
                   value="<?= e($_GET['q'] ?? '') ?>">
            <button type="submit" class="pub-search-btn"><?= $t('بحث', 'Search') ?></button>
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
            <span class="pub-stat-label"><?= $t('منتج', 'Products') ?></span>
        </div>
        <div class="pub-stat-item">
            <span class="pub-stat-value" data-target="<?= (int)$totalJobs ?>"><?= number_format((int)$totalJobs) ?>+</span>
            <span class="pub-stat-label"><?= $t('وظيفة', 'Jobs') ?></span>
        </div>
        <div class="pub-stat-item">
            <span class="pub-stat-value" data-target="<?= (int)$totalEntities ?>"><?= number_format((int)$totalEntities) ?>+</span>
            <span class="pub-stat-label"><?= $t('كيان', 'Entities') ?></span>
        </div>
        <div class="pub-stat-item">
            <span class="pub-stat-value" data-target="<?= (int)$totalTenants ?>"><?= number_format((int)$totalTenants) ?>+</span>
            <span class="pub-stat-label"><?= $t('مستأجر', 'Tenant') ?></span>
        </div>
    </div>
</div>

<!-- =============================================
     FEATURED PRODUCTS
============================================= -->
<?php if (!empty($featuredProducts)): ?>
<section class="pub-section">
    <div class="pub-container">
        <div class="pub-section-head">
            <h2 class="pub-section-title"><?= $t('🛍️ المنتجات المميزة', '🛍️ Featured Products') ?></h2>
            <a href="/frontend/public/products.php" class="pub-section-link"><?= $t('عرض الكل', 'View all') ?> →</a>
        </div>
        <div class="pub-grid">
            <?php foreach ($featuredProducts as $p): ?>
            <a href="/frontend/public/products.php?id=<?= (int)($p['id'] ?? 0) ?>"
               class="pub-product-card" style="text-decoration:none;">
                <div class="pub-product-card-img-placeholder" aria-hidden="true">🖼️</div>
                <div class="pub-product-card-body">
                    <?php if (!empty($p['is_featured'])): ?>
                        <span class="pub-product-badge"><?= $t('مميز', 'Featured') ?></span>
                    <?php endif; ?>
                    <p class="pub-product-name"><?= e($p['name'] ?? '') ?></p>
                    <?php if (!empty($p['price'])): ?>
                        <p class="pub-product-price">
                            <?= number_format((float)$p['price'], 2) ?>
                            <?= e($p['currency'] ?? 'SAR') ?>
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
<section class="pub-section" style="background:var(--pub-surface);">
    <div class="pub-container">
        <div class="pub-section-head">
            <h2 class="pub-section-title"><?= $t('💼 أحدث الوظائف', '💼 Latest Jobs') ?></h2>
            <a href="/frontend/public/jobs.php" class="pub-section-link"><?= $t('عرض الكل', 'View all') ?> →</a>
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
                    <?php if (!empty($j['is_featured'])): ?><span class="pub-tag pub-tag--featured"><?= $t('مميزة', 'Featured') ?></span><?php endif; ?>
                    <?php if (!empty($j['is_urgent'])): ?><span class="pub-tag pub-tag--urgent"><?= $t('عاجل', 'Urgent') ?></span><?php endif; ?>
                    <?php if (!empty($j['is_remote'])): ?><span class="pub-tag pub-tag--remote"><?= $t('عن بُعد', 'Remote') ?></span><?php endif; ?>
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
<section class="pub-section">
    <div class="pub-container">
        <div class="pub-section-head">
            <h2 class="pub-section-title"><?= $t('🏢 الكيانات', '🏢 Entities') ?></h2>
            <a href="/frontend/public/entities.php" class="pub-section-link"><?= $t('عرض الكل', 'View all') ?> →</a>
        </div>
        <div class="pub-grid-md">
            <?php foreach ($featuredEntities as $ent): ?>
            <a href="/frontend/public/entities.php?id=<?= (int)($ent['id'] ?? 0) ?>"
               class="pub-entity-card" style="text-decoration:none;">
                <div class="pub-entity-avatar">
                    <?php if (!empty($ent['logo_url'])): ?>
                        <img data-src="<?= e($ent['logo_url']) ?>" alt="<?= e($ent['store_name'] ?? '') ?>" loading="lazy">
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
                        <span class="pub-entity-verified">✅ <?= $t('موثّق', 'Verified') ?></span>
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
<section class="pub-section" style="background:var(--pub-surface);">
    <div class="pub-container">
        <div class="pub-section-head">
            <h2 class="pub-section-title"><?= $t('👥 المستأجرون', '👥 Tenants') ?></h2>
            <a href="/frontend/public/tenants.php" class="pub-section-link"><?= $t('عرض الكل', 'View all') ?> →</a>
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
                        <span class="pub-entity-verified">🟢 <?= $t('نشط', 'Active') ?></span>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>
