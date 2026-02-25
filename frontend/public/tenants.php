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

/* Fetch from real API */
$qs = http_build_query(array_filter([
    'lang'   => $lang,
    'page'   => $page,
    'per'    => $limit,
    'search' => $search ?: null,
]));
$resp    = pub_fetch(pub_api_url('public/tenants') . '?' . $qs);
$tenants = $resp['data'] ?? [];
$meta    = $resp['meta'] ?? [];
$total   = (int)($meta['total'] ?? count($tenants));
$totalPg = (int)($meta['total_pages'] ?? (int)ceil($total / $limit));

include dirname(__DIR__) . '/partials/header.php';
?>

<div class="pub-container" style="padding-top:28px;">

    <!-- Breadcrumb -->
    <nav style="font-size:0.84rem;color:var(--pub-muted);margin-bottom:20px;" aria-label="breadcrumb">
        <a href="/frontend/public/index.php"><?= e(t('common.home')) ?></a>
        <span style="margin:0 6px;">›</span>
        <span><?= e(t('tenants.page_title')) ?></span>
    </nav>

    <div class="pub-section-head" style="margin-bottom:16px;">
        <h1 style="font-size:1.4rem;margin:0;">👥 <?= e(t('tenants.page_title')) ?></h1>
        <span style="font-size:0.85rem;color:var(--pub-muted);">
            <?= number_format($total) ?> <?= e(t('tenants.tenant_count')) ?>
        </span>
    </div>

    <!-- Search filter -->
    <form method="get" class="pub-filter-bar">
        <input type="search" name="q" class="pub-search-input" style="max-width:280px;"
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
        <a href="/frontend/public/tenants.php?id=<?= (int)($ten['id'] ?? 0) ?>"
           class="pub-entity-card" style="text-decoration:none;">
            <div class="pub-entity-avatar">🏪</div>
            <div class="pub-entity-info">
                <p class="pub-entity-name"><?= e($ten['store_name'] ?? $ten['name'] ?? '') ?></p>
                <?php if (!empty($ten['domain'])): ?>
                    <p class="pub-entity-desc">🌐 <?= e($ten['domain']) ?></p>
                <?php endif; ?>
                <div style="display:flex;gap:6px;margin-top:5px;flex-wrap:wrap;">
                    <?php if (!empty($ten['is_active'])): ?>
                        <span class="pub-entity-verified">🟢 <?= e(t('tenants.active')) ?></span>
                    <?php else: ?>
                        <span style="font-size:0.75rem;color:var(--pub-muted);">⚪ <?= e(t('tenants.inactive')) ?></span>
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
