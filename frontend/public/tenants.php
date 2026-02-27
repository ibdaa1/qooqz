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
$GLOBALS['PUB_PAGE_TITLE'] = t('tenants.page_title') . ' — QOOQZ';

/* Filters */
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 18;
$search = trim($_GET['q'] ?? '');

/* Fetch from public tenants endpoint */
$qs = http_build_query(array_filter([
    'lang'   => $lang,
    'page'   => $page,
    'limit'  => $limit,
    'search' => $search ?: null,
]));
$resp    = pub_fetch(pub_api_url('public/tenants') . '?' . $qs);
$tenants = $resp['data']['data'] ?? ($resp['data']['items'] ?? []);
$meta    = $resp['data']['meta']  ?? [];
$total   = (int)($meta['total'] ?? count($tenants));
$totalPg = (int)($meta['total_pages'] ?? (($limit > 0 && $total > 0) ? (int)ceil($total / $limit) : 1));

include dirname(__DIR__) . '/partials/header.php';
?>

<div class="pub-container" style="padding-top:28px;">

    <!-- Breadcrumb -->
    <nav style="font-size:0.84rem;color:var(--pub-muted);margin-bottom:20px;" aria-label="breadcrumb">
        <a href="/frontend/public/index.php"><?= e(t('common.home')) ?></a>
        <span style="margin:0 6px;">›</span>
        <span><?= e(t('nav.tenants')) ?></span>
    </nav>

    <div class="pub-section-head" style="margin-bottom:16px;">
        <h1 style="font-size:1.4rem;margin:0;">👥 <?= e(t('nav.tenants')) ?></h1>
        <span style="font-size:0.85rem;color:var(--pub-muted);">
            <?= number_format($total) ?> <?= e(t('tenants.tenant_count')) ?>
        </span>
    </div>

    <!-- Join as Tenant CTA -->
    <div class="pub-cta-banner">
        <div>
            <h2>🌐 <?= e(t('join_tenant.cta_title')) ?></h2>
            <p><?= e(t('join_tenant.cta_subtitle')) ?></p>
        </div>
        <a href="/frontend/public/join_tenant.php" class="pub-btn--cta"><?= e(t('join_tenant.cta_btn')) ?></a>
    </div>

    <!-- Filter -->
    <form method="get" class="pub-filter-bar">
        <input type="search" name="q" class="pub-search-input" style="max-width:300px;"
               placeholder="<?= e(t('tenants.search_placeholder')) ?>"
               value="<?= e($search) ?>">
        <button type="submit" class="pub-btn pub-btn--primary pub-btn--sm"><?= e(t('tenants.filter')) ?></button>
        <?php if ($search): ?>
            <a href="/frontend/public/tenants.php" class="pub-btn pub-btn--ghost pub-btn--sm"><?= e(t('tenants.clear')) ?></a>
        <?php endif; ?>
    </form>

    <!-- Tenants grid -->
    <?php if (!empty($tenants)): ?>
    <div class="pub-grid-md">
        <?php foreach ($tenants as $ten): ?>
        <a href="/frontend/public/tenant.php?id=<?= (int)($ten['id'] ?? 0) ?>"
           class="pub-entity-card" style="text-decoration:none;">
            <div class="pub-entity-avatar">🏪</div>
            <div class="pub-entity-info">
                <p class="pub-entity-name"><?= e($ten['store_name'] ?? $ten['name'] ?? '') ?></p>
                <?php if (!empty($ten['domain'])): ?>
                    <p class="pub-entity-desc"><?= e($ten['domain']) ?></p>
                <?php endif; ?>
                <?php if (isset($ten['plan_name']) && $ten['plan_name']): ?>
                    <p class="pub-entity-desc"><?= e($ten['plan_name']) ?></p>
                <?php endif; ?>
                <span class="pub-entity-verified">
                    <?= ($ten['status'] ?? '') === 'active' ? '🟢 ' . e(t('tenants.active')) : '🔴 ' . e(t('tenants.inactive')) ?>
                </span>
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
        <a href="<?= $pg_url(max(1,$page-1)) ?>" class="pub-page-btn <?= $page<=1?'disabled':'' ?>"><?= e(t('pagination.prev')) ?></a>
        <?php for ($i = max(1,$page-2); $i <= min($totalPg,$page+2); $i++): ?>
            <a href="<?= $pg_url($i) ?>" class="pub-page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <a href="<?= $pg_url(min($totalPg,$page+1)) ?>" class="pub-page-btn <?= $page>=$totalPg?'disabled':'' ?>"><?= e(t('pagination.next')) ?></a>
    </nav>
    <?php endif; ?>

    <?php else: ?>
    <div class="pub-empty">
        <div class="pub-empty-icon">👥</div>
        <p class="pub-empty-msg"><?= e(t('tenants.empty')) ?></p>
    </div>
    <?php endif; ?>

</div>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>
