<?php
declare(strict_types=1);
/**
 * frontend/public/tenants.php
 * QOOQZ — Public Tenants Listing Page
 */

require_once dirname(__DIR__) . '/includes/public_context.php';

$ctx      = $GLOBALS['PUB_CONTEXT'];
$lang     = $ctx['lang'];
$tenantId = $ctx['tenant_id'];

$GLOBALS['PUB_APP_NAME']   = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH']  = '/frontend/public';
$GLOBALS['PUB_PAGE_TITLE'] = $lang === 'ar' ? 'المستأجرون — QOOQZ' : 'Tenants — QOOQZ';

$t = fn(string $ar, string $en) => $lang === 'ar' ? $ar : $en;

/* Filters */
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 18;
$search = trim($_GET['q'] ?? '');

/* Fetch */
$qs = http_build_query(array_filter([
    'lang'   => $lang,
    'page'   => $page,
    'limit'  => $limit,
    'search' => $search ?: null,
]));
$resp    = pub_fetch(pub_api_url('tenants') . '?' . $qs);
$tenants = $resp['data']['items'] ?? ($resp['data']['data'] ?? []);
$meta    = $resp['data']['meta']  ?? [];
$total   = (int)($meta['total'] ?? count($tenants));
$totalPg = (int)($meta['total_pages'] ?? ceil($total / $limit));

/* Demo fallback */
if (empty($tenants)) {
    $tenants = [
        ['id'=>1,'name'=>'TechHub','store_name'=>$t('تك هب','TechHub'),'domain'=>'techhub.example.com','is_active'=>1,'plan_name'=>'Pro','description'=>$t('متجر تقنية متخصص','Specialized tech store')],
        ['id'=>2,'name'=>'FashionStore','store_name'=>$t('متجر الموضة','Fashion Store'),'domain'=>'fashion.example.com','is_active'=>1,'plan_name'=>'Starter','description'=>$t('أزياء عصرية','Modern fashion')],
        ['id'=>3,'name'=>'FoodWorld','store_name'=>$t('عالم الطعام','Food World'),'domain'=>'food.example.com','is_active'=>1,'plan_name'=>'Business','description'=>$t('أفضل المأكولات','Best food options')],
        ['id'=>4,'name'=>'HealthPlus','store_name'=>$t('هيلث بلص','HealthPlus'),'domain'=>'health.example.com','is_active'=>0,'plan_name'=>'Pro','description'=>$t('رعاية صحية شاملة','Comprehensive healthcare')],
        ['id'=>5,'name'=>'EduCenter','store_name'=>$t('مركز التعليم','EduCenter'),'domain'=>'edu.example.com','is_active'=>1,'plan_name'=>'Enterprise','description'=>$t('تعليم متميز','Distinguished education')],
        ['id'=>6,'name'=>'CarDeals','store_name'=>$t('صفقات السيارات','Car Deals'),'domain'=>'cars.example.com','is_active'=>1,'plan_name'=>'Business','description'=>$t('أفضل عروض السيارات','Best car deals')],
    ];
    $total   = count($tenants);
    $totalPg = 1;
}

include dirname(__DIR__) . '/partials/header.php';
?>

<div class="pub-container" style="padding-top:28px;">

    <!-- Breadcrumb -->
    <nav style="font-size:0.84rem;color:var(--pub-muted);margin-bottom:20px;" aria-label="breadcrumb">
        <a href="/frontend/public/index.php"><?= $t('الرئيسية','Home') ?></a>
        <span style="margin:0 6px;">›</span>
        <span><?= $t('المستأجرون','Tenants') ?></span>
    </nav>

    <div class="pub-section-head" style="margin-bottom:16px;">
        <h1 style="font-size:1.4rem;margin:0;"><?= $t('👥 المستأجرون','👥 Tenants') ?></h1>
        <span style="font-size:0.85rem;color:var(--pub-muted);">
            <?= number_format($total) ?> <?= $t('مستأجر','tenant(s)') ?>
        </span>
    </div>

    <!-- Search filter -->
    <form method="get" class="pub-filter-bar">
        <input type="search" name="q" class="pub-search-input" style="max-width:280px;"
               placeholder="<?= $t('ابحث عن مستأجر...','Search tenants...') ?>"
               value="<?= e($search) ?>">
        <button type="submit" class="pub-btn pub-btn--primary pub-btn--sm"><?= $t('بحث','Search') ?></button>
        <?php if ($search): ?>
            <a href="/frontend/public/tenants.php" class="pub-btn pub-btn--ghost pub-btn--sm"><?= $t('مسح','Clear') ?></a>
        <?php endif; ?>
    </form>

    <!-- Tenants grid -->
    <?php if (!empty($tenants)): ?>
    <div class="pub-grid-md">
        <?php foreach ($tenants as $ten): ?>
        <a href="/frontend/public/tenants.php?id=<?= (int)($ten['id'] ?? 0) ?>"
           class="pub-entity-card" style="text-decoration:none;">
            <div class="pub-entity-avatar">🏪</div>
            <div class="pub-entity-info">
                <p class="pub-entity-name"><?= e($ten['store_name'] ?? $ten['name'] ?? '') ?></p>
                <?php if (!empty($ten['domain'])): ?>
                    <p class="pub-entity-desc">🌐 <?= e($ten['domain']) ?></p>
                <?php endif; ?>
                <?php if (!empty($ten['description'])): ?>
                    <p class="pub-entity-desc" style="margin-top:3px;"><?= e($ten['description']) ?></p>
                <?php endif; ?>
                <div style="display:flex;gap:6px;margin-top:5px;flex-wrap:wrap;">
                    <?php if (!empty($ten['is_active'])): ?>
                        <span class="pub-entity-verified">🟢 <?= $t('نشط','Active') ?></span>
                    <?php else: ?>
                        <span style="font-size:0.75rem;color:var(--pub-muted);">⚪ <?= $t('غير نشط','Inactive') ?></span>
                    <?php endif; ?>
                    <?php if (!empty($ten['plan_name'])): ?>
                        <span class="pub-tag"><?= e($ten['plan_name']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPg > 1): ?>
    <nav class="pub-pagination">
        <?php
        $base_qs = http_build_query(array_filter(['q'=>$search]));
        $pg_url  = fn(int $pg) => '?' . ($base_qs?$base_qs.'&':'') . 'page='.$pg;
        ?>
        <a href="<?= $pg_url(max(1,$page-1)) ?>" class="pub-page-btn <?= $page<=1?'disabled':'' ?>"><?= $t('السابق','Prev') ?></a>
        <?php for ($i = max(1,$page-2); $i <= min($totalPg,$page+2); $i++): ?>
            <a href="<?= $pg_url($i) ?>" class="pub-page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <a href="<?= $pg_url(min($totalPg,$page+1)) ?>" class="pub-page-btn <?= $page>=$totalPg?'disabled':'' ?>"><?= $t('التالي','Next') ?></a>
    </nav>
    <?php endif; ?>

    <?php else: ?>
    <div class="pub-empty">
        <div class="pub-empty-icon">👥</div>
        <p class="pub-empty-msg"><?= $t('لا يوجد مستأجرون حالياً','No tenants available at the moment') ?></p>
    </div>
    <?php endif; ?>

</div>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>
