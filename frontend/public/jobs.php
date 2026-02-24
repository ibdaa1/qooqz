<?php
declare(strict_types=1);
/**
 * frontend/public/jobs.php
 * QOOQZ — Public Jobs Listing Page
 */

require_once dirname(__DIR__) . '/includes/public_context.php';

$ctx      = $GLOBALS['PUB_CONTEXT'];
$lang     = $ctx['lang'];
$tenantId = $ctx['tenant_id'];

$GLOBALS['PUB_APP_NAME']   = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH']  = '/frontend/public';
$GLOBALS['PUB_PAGE_TITLE'] = $lang === 'ar' ? 'الوظائف — QOOQZ' : 'Jobs — QOOQZ';

$t = fn(string $ar, string $en) => $lang === 'ar' ? $ar : $en;

/* Filters */
$page      = max(1, (int)($_GET['page'] ?? 1));
$limit     = 15;
$search    = trim($_GET['q'] ?? '');
$jobType   = trim($_GET['employment_type'] ?? '');
$isRemote  = isset($_GET['remote']) && $_GET['remote'] === '1' ? 1 : null;
$isFeat    = isset($_GET['featured']) && $_GET['featured'] === '1' ? 1 : null;

/* Fetch */
$qs = http_build_query(array_filter([
    'lang'            => $lang,
    'page'            => $page,
    'limit'           => $limit,
    'search'          => $search ?: null,
    'employment_type' => $jobType ?: null,
    'is_remote'       => $isRemote,
    'is_featured'     => $isFeat,
]));
$resp = pub_fetch(pub_api_url('jobs') . '?' . $qs);
$jobs    = $resp['data']['items'] ?? [];
$meta    = $resp['data']['meta']  ?? [];
$total   = (int)($meta['total'] ?? count($jobs));
$totalPg = (int)($meta['total_pages'] ?? ceil($total / $limit));

/* Demo fallback */
if (empty($jobs)) {
    $jobs = [
        ['id'=>1,'title'=>$t('مطور واجهة أمامية','Frontend Developer'),'employment_type'=>'full_time','is_remote'=>1,'is_featured'=>1,'is_urgent'=>0,'city_name'=>$t('الرياض','Riyadh'),'deadline'=>date('Y-m-d', strtotime('+30 days'))],
        ['id'=>2,'title'=>$t('مدير تسويق رقمي','Digital Marketing Manager'),'employment_type'=>'full_time','is_remote'=>0,'is_featured'=>0,'is_urgent'=>1,'city_name'=>$t('جدة','Jeddah'),'deadline'=>date('Y-m-d', strtotime('+15 days'))],
        ['id'=>3,'title'=>$t('مبرمج PHP','PHP Developer'),'employment_type'=>'contract','is_remote'=>1,'is_featured'=>1,'is_urgent'=>0,'city_name'=>$t('الدمام','Dammam'),'deadline'=>date('Y-m-d', strtotime('+45 days'))],
        ['id'=>4,'title'=>$t('مصمم جرافيك','Graphic Designer'),'employment_type'=>'part_time','is_remote'=>0,'is_featured'=>0,'is_urgent'=>0,'city_name'=>$t('مكة','Mecca'),'deadline'=>date('Y-m-d', strtotime('+20 days'))],
        ['id'=>5,'title'=>$t('محلل بيانات','Data Analyst'),'employment_type'=>'full_time','is_remote'=>1,'is_featured'=>0,'is_urgent'=>1,'city_name'=>$t('أبوظبي','Abu Dhabi'),'deadline'=>date('Y-m-d', strtotime('+10 days'))],
        ['id'=>6,'title'=>$t('مدير مشاريع','Project Manager'),'employment_type'=>'full_time','is_remote'=>0,'is_featured'=>1,'is_urgent'=>0,'city_name'=>$t('دبي','Dubai'),'deadline'=>date('Y-m-d', strtotime('+60 days'))],
    ];
    $total   = count($jobs);
    $totalPg = 1;
}

$empTypes = [
    ''            => $t('جميع الأنواع', 'All Types'),
    'full_time'   => $t('دوام كامل', 'Full Time'),
    'part_time'   => $t('دوام جزئي', 'Part Time'),
    'contract'    => $t('عقد', 'Contract'),
    'freelance'   => $t('فريلانس', 'Freelance'),
    'internship'  => $t('تدريب', 'Internship'),
];

include dirname(__DIR__) . '/partials/header.php';
?>

<div class="pub-container" style="padding-top:28px;">

    <!-- Breadcrumb -->
    <nav style="font-size:0.84rem;color:var(--pub-muted);margin-bottom:20px;" aria-label="breadcrumb">
        <a href="/frontend/public/index.php"><?= $t('الرئيسية','Home') ?></a>
        <span style="margin:0 6px;">›</span>
        <span><?= $t('الوظائف','Jobs') ?></span>
    </nav>

    <div class="pub-section-head" style="margin-bottom:16px;">
        <h1 style="font-size:1.4rem;margin:0;"><?= $t('💼 الوظائف','💼 Jobs') ?></h1>
        <span style="font-size:0.85rem;color:var(--pub-muted);">
            <?= number_format($total) ?> <?= $t('وظيفة','job(s)') ?>
        </span>
    </div>

    <!-- Filters -->
    <form method="get" class="pub-filter-bar">
        <input type="search" name="q" class="pub-search-input" style="max-width:240px;"
               placeholder="<?= $t('ابحث عن وظيفة...','Search jobs...') ?>"
               value="<?= e($search) ?>">

        <select name="employment_type" class="pub-filter-select" data-auto-submit>
            <?php foreach ($empTypes as $val => $label): ?>
                <option value="<?= e($val) ?>" <?= $jobType===$val?'selected':'' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>

        <label style="display:flex;align-items:center;gap:5px;font-size:0.88rem;cursor:pointer;">
            <input type="checkbox" name="remote" value="1" <?= $isRemote?'checked':'' ?> onchange="this.form.submit()">
            <?= $t('عن بُعد','Remote') ?>
        </label>
        <label style="display:flex;align-items:center;gap:5px;font-size:0.88rem;cursor:pointer;">
            <input type="checkbox" name="featured" value="1" <?= $isFeat?'checked':'' ?> onchange="this.form.submit()">
            <?= $t('المميزة','Featured') ?>
        </label>

        <button type="submit" class="pub-btn pub-btn--primary pub-btn--sm"><?= $t('بحث','Search') ?></button>
        <?php if ($search||$jobType||$isRemote||$isFeat): ?>
            <a href="/frontend/public/jobs.php" class="pub-btn pub-btn--ghost pub-btn--sm"><?= $t('مسح','Clear') ?></a>
        <?php endif; ?>
    </form>

    <!-- Jobs list -->
    <?php if (!empty($jobs)): ?>
    <div class="pub-grid-lg">
        <?php foreach ($jobs as $j): ?>
        <a href="/frontend/public/jobs.php?id=<?= (int)($j['id'] ?? 0) ?>"
           class="pub-job-card" style="text-decoration:none;">
            <h2 class="pub-job-title"><?= e($j['title'] ?? '') ?></h2>
            <div class="pub-job-meta">
                <?php if (!empty($j['city_name'])): ?>
                    <span>📍 <?= e($j['city_name']) ?></span>
                <?php endif; ?>
                <?php if (!empty($j['employment_type'])): ?>
                    <span>🕐 <?= e($empTypes[$j['employment_type']] ?? $j['employment_type']) ?></span>
                <?php endif; ?>
                <?php if (!empty($j['deadline'])): ?>
                    <span>📅 <?= e($j['deadline']) ?></span>
                <?php endif; ?>
            </div>
            <div class="pub-job-tags">
                <?php if (!empty($j['is_featured'])): ?><span class="pub-tag pub-tag--featured"><?= $t('مميزة','Featured') ?></span><?php endif; ?>
                <?php if (!empty($j['is_urgent'])): ?><span class="pub-tag pub-tag--urgent"><?= $t('عاجل','Urgent') ?></span><?php endif; ?>
                <?php if (!empty($j['is_remote'])): ?><span class="pub-tag pub-tag--remote"><?= $t('عن بُعد','Remote') ?></span><?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPg > 1): ?>
    <nav class="pub-pagination">
        <?php
        $base_qs = http_build_query(array_filter(['q'=>$search,'employment_type'=>$jobType,'remote'=>$isRemote,'featured'=>$isFeat]));
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
        <div class="pub-empty-icon">💼</div>
        <p class="pub-empty-msg"><?= $t('لا توجد وظائف متاحة حالياً','No jobs available at the moment') ?></p>
    </div>
    <?php endif; ?>

</div>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>
