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
$GLOBALS['PUB_PAGE_TITLE'] = t('entities.page_title') . ' — QOOQZ';

/* Filters */
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 18;
$search = trim($_GET['q'] ?? '');
$vType  = trim($_GET['vendor_type'] ?? '');

/* Fetch from real API */
$qs = http_build_query(array_filter([
    'lang'        => $lang,
    'page'        => $page,
    'per'         => $limit,
    'tenant_id'   => $tenantId ?: null,
    'vendor_type' => $vType ?: null,
]));
$resp     = pub_fetch(pub_api_url('public/entities') . '?' . $qs);
$entities = $resp['data'] ?? [];
$meta     = $resp['meta'] ?? [];
$total    = (int)($meta['total'] ?? count($entities));
$totalPg  = (int)($meta['total_pages'] ?? (int)ceil($total / $limit));

$vendorTypes = [
    ''           => t('entities.type_all'),
    'company'    => t('entities.type_company'),
    'store'      => t('entities.type_store'),
    'restaurant' => t('entities.type_restaurant'),
    'medical'    => t('entities.type_medical'),
    'training'   => t('entities.type_training'),
];

include dirname(__DIR__) . '/partials/header.php';
?>

<div class="pub-container" style="padding-top:28px;">

    <!-- Breadcrumb -->
    <nav style="font-size:0.84rem;color:var(--pub-muted);margin-bottom:20px;" aria-label="breadcrumb">
        <a href="/frontend/public/index.php"><?= e(t('common.home')) ?></a>
        <span style="margin:0 6px;">›</span>
        <span><?= e(t('entities.page_title')) ?></span>
    </nav>

    <div class="pub-section-head" style="margin-bottom:16px;">
        <h1 style="font-size:1.4rem;margin:0;">🏢 <?= e(t('entities.page_title')) ?></h1>
        <span style="font-size:0.85rem;color:var(--pub-muted);">
            <?= number_format($total) ?> <?= e(t('entities.entity_count')) ?>
        </span>
    </div>

    <!-- Filters -->
    <form method="get" class="pub-filter-bar">
        <input type="search" name="q" class="pub-search-input" style="max-width:240px;"
               placeholder="<?= e(t('entities.search_placeholder')) ?>"
               value="<?= e($search) ?>">
        <select name="vendor_type" class="pub-filter-select" data-auto-submit>
            <?php foreach ($vendorTypes as $val => $label): ?>
                <option value="<?= e($val) ?>" <?= $vType===$val?'selected':'' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="pub-btn pub-btn--primary pub-btn--sm"><?= e(t('entities.filter')) ?></button>
        <?php if ($search||$vType): ?>
            <a href="/frontend/public/entities.php" class="pub-btn pub-btn--ghost pub-btn--sm"><?= e(t('entities.clear')) ?></a>
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
                    <img data-src="<?= e(pub_img($ent['logo_url'], 'entity_logo')) ?>"
                         alt="<?= e($ent['store_name'] ?? '') ?>" loading="lazy"
                         onerror="this.style.display='none'">
                <?php else: ?>
                    🏢
                <?php endif; ?>
            </div>
            <div class="pub-entity-info">
                <p class="pub-entity-name"><?= e($ent['store_name'] ?? '') ?></p>
                <?php if (!empty($ent['vendor_type'])): ?>
                    <p class="pub-entity-desc"><?= e($vendorTypes[$ent['vendor_type']] ?? $ent['vendor_type']) ?></p>
                <?php endif; ?>
                <?php if (!empty($ent['description'])): ?>
                    <p class="pub-entity-desc" style="margin-top:3px;"><?= e($ent['description']) ?></p>
                <?php endif; ?>
                <?php if (!empty($ent['is_verified'])): ?>
                    <span class="pub-entity-verified">✅ <?= e(t('entities.verified')) ?></span>
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
        <a href="<?= $pg_url(max(1,$page-1)) ?>" class="pub-page-btn <?= $page<=1?'disabled':'' ?>"><?= e(t('pagination.prev')) ?></a>
        <?php for ($i = max(1,$page-2); $i <= min($totalPg,$page+2); $i++): ?>
            <a href="<?= $pg_url($i) ?>" class="pub-page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <a href="<?= $pg_url(min($totalPg,$page+1)) ?>" class="pub-page-btn <?= $page>=$totalPg?'disabled':'' ?>"><?= e(t('pagination.next')) ?></a>
    </nav>
    <?php endif; ?>

    <?php else: ?>
    <div class="pub-empty">
        <div class="pub-empty-icon">🏢</div>
        <p class="pub-empty-msg"><?= e(t('entities.empty')) ?></p>
    </div>
    <?php endif; ?>

</div>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>
