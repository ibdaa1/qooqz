<?php
declare(strict_types=1);
/**
 * frontend/public/entities.php
 * QOOQZ — Public Entities Listing Page
 */

require_once dirname(__DIR__) . '/includes/public_context.php';

$ctx      = $GLOBALS['PUB_CONTEXT'];
$lang     = $ctx['lang'];
$tenantId = $ctx['tenant_id'];

$GLOBALS['PUB_APP_NAME']   = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH']  = '/frontend/public';
$GLOBALS['PUB_PAGE_TITLE'] = $lang === 'ar' ? 'الكيانات — QOOQZ' : 'Entities — QOOQZ';

$t = fn(string $ar, string $en) => $lang === 'ar' ? $ar : $en;

/* Filters */
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 18;
$search = trim($_GET['q'] ?? '');
$vType  = trim($_GET['vendor_type'] ?? '');

/* Fetch */
$qs = http_build_query(array_filter([
    'lang'        => $lang,
    'page'        => $page,
    'limit'       => $limit,
    'tenant_id'   => $tenantId,
    'status'      => 'active',
    'vendor_type' => $vType ?: null,
]));
$resp     = pub_fetch(pub_api_url('entities') . '?' . $qs);
$entities = $resp['data']['items'] ?? [];
$meta     = $resp['data']['meta']  ?? [];
$total    = (int)($meta['total'] ?? count($entities));
$totalPg  = (int)($meta['total_pages'] ?? ceil($total / $limit));

/* Demo fallback */
if (empty($entities)) {
    $entities = [
        ['id'=>1,'store_name'=>$t('شركة التقنية العالمية','Global Tech Co.'),'vendor_type'=>'company','is_verified'=>1,'logo_url'=>'','description'=>$t('شركة رائدة في مجال التقنية','Leading technology company')],
        ['id'=>2,'store_name'=>$t('متجر الأزياء الفاخرة','Luxury Fashion'),'vendor_type'=>'store','is_verified'=>0,'logo_url'=>'','description'=>$t('أحدث صيحات الموضة','Latest fashion trends')],
        ['id'=>3,'store_name'=>$t('مركز التدريب المتقدم','Advanced Training Center'),'vendor_type'=>'training','is_verified'=>1,'logo_url'=>'','description'=>$t('تدريب متخصص في التقنية','Specialized technology training')],
        ['id'=>4,'store_name'=>$t('مستشفى الصحة العامة','Public Health Hospital'),'vendor_type'=>'medical','is_verified'=>1,'logo_url'=>'','description'=>$t('رعاية صحية متكاملة','Comprehensive healthcare')],
        ['id'=>5,'store_name'=>$t('مطعم المأكولات العالمية','World Cuisine Restaurant'),'vendor_type'=>'restaurant','is_verified'=>0,'logo_url'=>'','description'=>$t('مطبخ عالمي متنوع','Diverse international cuisine')],
        ['id'=>6,'store_name'=>$t('شركة البناء والتطوير','Construction & Development'),'vendor_type'=>'company','is_verified'=>1,'logo_url'=>'','description'=>$t('بناء وتطوير عقاري','Real estate development')],
    ];
    $total   = count($entities);
    $totalPg = 1;
}

$vendorTypes = [
    ''           => $t('جميع الأنواع','All Types'),
    'company'    => $t('شركة','Company'),
    'store'      => $t('متجر','Store'),
    'restaurant' => $t('مطعم','Restaurant'),
    'medical'    => $t('طبي','Medical'),
    'training'   => $t('تدريب','Training'),
];

include dirname(__DIR__) . '/partials/header.php';
?>

<div class="pub-container" style="padding-top:28px;">

    <!-- Breadcrumb -->
    <nav style="font-size:0.84rem;color:var(--pub-muted);margin-bottom:20px;" aria-label="breadcrumb">
        <a href="/frontend/public/index.php"><?= $t('الرئيسية','Home') ?></a>
        <span style="margin:0 6px;">›</span>
        <span><?= $t('الكيانات','Entities') ?></span>
    </nav>

    <div class="pub-section-head" style="margin-bottom:16px;">
        <h1 style="font-size:1.4rem;margin:0;"><?= $t('🏢 الكيانات','🏢 Entities') ?></h1>
        <span style="font-size:0.85rem;color:var(--pub-muted);">
            <?= number_format($total) ?> <?= $t('كيان','entity/entities') ?>
        </span>
    </div>

    <!-- Filters -->
    <form method="get" class="pub-filter-bar">
        <input type="search" name="q" class="pub-search-input" style="max-width:240px;"
               placeholder="<?= $t('ابحث عن كيان...','Search entities...') ?>"
               value="<?= e($search) ?>">
        <select name="vendor_type" class="pub-filter-select" data-auto-submit>
            <?php foreach ($vendorTypes as $val => $label): ?>
                <option value="<?= e($val) ?>" <?= $vType===$val?'selected':'' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="pub-btn pub-btn--primary pub-btn--sm"><?= $t('بحث','Search') ?></button>
        <?php if ($search||$vType): ?>
            <a href="/frontend/public/entities.php" class="pub-btn pub-btn--ghost pub-btn--sm"><?= $t('مسح','Clear') ?></a>
        <?php endif; ?>
    </form>

    <!-- Entities grid -->
    <?php if (!empty($entities)): ?>
    <div class="pub-grid-md">
        <?php foreach ($entities as $ent): ?>
        <a href="/frontend/public/entity.php?id=<?= (int)($ent['id'] ?? 0) ?>"
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
                    <p class="pub-entity-desc"><?= e($vendorTypes[$ent['vendor_type']] ?? $ent['vendor_type']) ?></p>
                <?php endif; ?>
                <?php if (!empty($ent['description'])): ?>
                    <p class="pub-entity-desc" style="margin-top:3px;"><?= e($ent['description']) ?></p>
                <?php endif; ?>
                <?php if (!empty($ent['is_verified'])): ?>
                    <span class="pub-entity-verified">✅ <?= $t('موثّق','Verified') ?></span>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPg > 1): ?>
    <nav class="pub-pagination">
        <?php
        $base_qs = http_build_query(array_filter(['q'=>$search,'vendor_type'=>$vType]));
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
        <div class="pub-empty-icon">🏢</div>
        <p class="pub-empty-msg"><?= $t('لا توجد كيانات متاحة حالياً','No entities available at the moment') ?></p>
    </div>
    <?php endif; ?>

</div>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>
