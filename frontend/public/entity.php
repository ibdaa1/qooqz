<?php
declare(strict_types=1);
/**
 * frontend/public/entity.php
 * QOOQZ — Public Entity/Vendor Profile Page
 *
 * Shows: banner (entity_cover), logo (entity_logo), store name, description,
 *        social links, working hours, addresses (with coordinates), products,
 *        payment methods, attributes, type badge
 */

require_once dirname(__DIR__) . '/includes/public_context.php';

$ctx      = $GLOBALS['PUB_CONTEXT'];
$lang     = $ctx['lang'];
$dir      = $ctx['dir'];
$tenantId = $ctx['tenant_id'];

/* -------------------------------------------------------
 * Entity ID from URL
 * ----------------------------------------------------- */
$entityId = (int)($_GET['id'] ?? $_GET['entity_id'] ?? 0);
$slug     = $_GET['slug'] ?? '';

if (!$entityId && !$slug) {
    header('Location: /frontend/public/entities.php');
    exit;
}

$GLOBALS['PUB_APP_NAME']  = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH'] = '/frontend/public';

/* -------------------------------------------------------
 * Fetch entity data from real API
 * ----------------------------------------------------- */
$qs   = 'lang=' . urlencode($lang) . '&tenant_id=' . $tenantId;
$resp = pub_fetch(pub_api_url('public/entity/' . $entityId) . '?' . $qs);
$entity = $resp['data'] ?? [];

if (empty($entity)) {
    $GLOBALS['PUB_PAGE_TITLE'] = t('entity.not_found') . ' — QOOQZ';
    include dirname(__DIR__) . '/partials/header.php';
    echo '<div class="pub-container" style="padding:60px 0;text-align:center;"><p>' . e(t('entity.not_found')) . '</p></div>';
    include dirname(__DIR__) . '/partials/footer.php';
    exit;
}

/* -------------------------------------------------------
 * Fetch entity products
 * ----------------------------------------------------- */
$productPage  = max(1, (int)($_GET['page'] ?? 1));
$productLimit = 12;
$rp = pub_fetch(
    pub_api_url('public/entity/' . ($entity['id'] ?? $entityId) . '/products')
    . '?' . $qs . '&per=' . $productLimit . '&page=' . $productPage
);
$products    = $rp['data'] ?? [];
$productMeta = $rp['meta'] ?? ['total' => count($products), 'total_pages' => 1];

$GLOBALS['PUB_PAGE_TITLE'] = e($entity['store_name'] ?? '') . ' — QOOQZ';
$GLOBALS['PUB_PAGE_DESC']  = e($entity['description'] ?? '');

/* -------------------------------------------------------
 * Day names from translation file
 * ----------------------------------------------------- */
$dayNames = [
    'sunday'    => t('entity.day_sunday'),
    'monday'    => t('entity.day_monday'),
    'tuesday'   => t('entity.day_tuesday'),
    'wednesday' => t('entity.day_wednesday'),
    'thursday'  => t('entity.day_thursday'),
    'friday'    => t('entity.day_friday'),
    'saturday'  => t('entity.day_saturday'),
];

include dirname(__DIR__) . '/partials/header.php';
?>

<!-- Entity Banner -->
<div class="pub-entity-banner">
    <?php if (!empty($entity['cover_url'])): ?>
        <img src="<?= e(pub_img($entity['cover_url'], 'entity_cover')) ?>"
             alt="<?= e($entity['store_name']) ?>"
             class="pub-entity-banner-img"
             loading="eager"
             onerror="this.style.display='none'">
    <?php else: ?>
        <div class="pub-entity-banner-placeholder"></div>
    <?php endif; ?>
</div>

<!-- Entity Header Card -->
<div class="pub-container">
    <div class="pub-entity-profile-header">

        <!-- Logo -->
        <div class="pub-entity-profile-logo">
            <?php if (!empty($entity['logo_url'])): ?>
                <img src="<?= e(pub_img($entity['logo_url'], 'entity_logo')) ?>"
                     alt="<?= e($entity['store_name']) ?>"
                     loading="eager"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                <span style="display:none;align-items:center;justify-content:center;font-size:2rem;">🏢</span>
            <?php else: ?>
                <span style="display:flex;align-items:center;justify-content:center;font-size:2.5rem;">🏢</span>
            <?php endif; ?>
        </div>

        <!-- Info -->
        <div class="pub-entity-profile-info">
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <h1 class="pub-entity-profile-name"><?= e($entity['store_name'] ?? '') ?></h1>
                <?php if (!empty($entity['is_verified'])): ?>
                    <span class="pub-entity-verified">✅ <?= e(t('entities.verified')) ?></span>
                <?php endif; ?>
                <?php if (!empty($entity['type_name'] ?? $entity['vendor_type'])): ?>
                    <span class="pub-tag" style="font-size:0.8rem;">
                        <?= e($entity['type_icon'] ?? '') ?> <?= e($entity['type_name'] ?? $entity['vendor_type']) ?>
                    </span>
                <?php endif; ?>
            </div>

            <?php if (!empty($entity['description'])): ?>
                <p class="pub-entity-profile-desc"><?= e($entity['description']) ?></p>
            <?php endif; ?>

            <!-- Contact info -->
            <div class="pub-entity-contacts">
                <?php if (!empty($entity['phone'])): ?>
                    <a href="tel:<?= e($entity['phone']) ?>" class="pub-contact-item">
                        📞 <?= e($entity['phone']) ?>
                    </a>
                <?php endif; ?>
                <?php if (!empty($entity['email'])): ?>
                    <a href="mailto:<?= e($entity['email']) ?>" class="pub-contact-item">
                        📧 <?= e($entity['email']) ?>
                    </a>
                <?php endif; ?>
                <?php if (!empty($entity['website'])): ?>
                    <a href="<?= e($entity['website']) ?>" target="_blank" rel="noopener" class="pub-contact-item">
                        🌐 <?= e(parse_url($entity['website'], PHP_URL_HOST) ?: $entity['website']) ?>
                    </a>
                <?php endif; ?>
            </div>

            <!-- Social links -->
            <div class="pub-entity-social">
                <?php
                $waNum = ltrim($entity['whatsapp'] ?? '', '+');
                $socials = [
                    'whatsapp'  => [$waNum ? 'https://wa.me/' . $waNum : '', '💬 WhatsApp'],
                    'facebook'  => [$entity['facebook']  ?? '', '📘 Facebook'],
                    'instagram' => [$entity['instagram'] ?? '', '📷 Instagram'],
                    'twitter'   => [$entity['twitter']   ?? '', '🐦 Twitter'],
                    'snapchat'  => [$entity['snapchat']  ?? '', '👻 Snapchat'],
                ];
                foreach ($socials as $net => [$url, $label]):
                    if (empty($entity[$net])) continue;
                ?>
                    <a href="<?= e($url) ?>"
                       target="_blank" rel="noopener" class="pub-social-btn pub-social-btn--<?= e($net) ?>">
                        <?= $label ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Main tabs -->
    <div class="pub-tabs" style="margin-top:24px;" role="tablist">
        <button class="pub-tab active" data-tab="products" role="tab"
                aria-selected="true" aria-controls="tabProducts">
            🛍️ <?= e(t('entity.products_tab')) ?>
        </button>
        <button class="pub-tab" data-tab="info" role="tab"
                aria-selected="false" aria-controls="tabInfo">
            ℹ️ <?= e(t('entity.info_tab')) ?>
        </button>
        <button class="pub-tab" data-tab="hours" role="tab"
                aria-selected="false" aria-controls="tabHours">
            🕐 <?= e(t('entity.hours_tab')) ?>
        </button>
        <button class="pub-tab" data-tab="map" role="tab"
                aria-selected="false" aria-controls="tabMap">
            🗺️ <?= e(t('entity.location_tab')) ?>
        </button>
    </div>

    <!-- TAB: Products -->
    <div class="pub-tab-panel active" id="tabProducts">
        <?php if (!empty($products)): ?>
        <div class="pub-grid" style="margin-top:20px;">
            <?php foreach ($products as $p): ?>
            <a href="/frontend/public/products.php?id=<?= (int)($p['id'] ?? 0) ?>"
               class="pub-product-card" style="text-decoration:none;">
                <div class="pub-cat-img-wrap" style="aspect-ratio:1;">
                    <?php if (!empty($p['image_url'])): ?>
                        <img src="<?= e(pub_img($p['image_url'], 'product_thumb')) ?>"
                             alt="<?= e($p['name'] ?? '') ?>" class="pub-cat-img" loading="lazy"
                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                        <span class="pub-img-placeholder" style="display:none;">🖼️</span>
                    <?php else: ?>
                        <span class="pub-img-placeholder">🖼️</span>
                    <?php endif; ?>
                </div>
                <div class="pub-product-card-body">
                    <?php if (!empty($p['is_featured'])): ?>
                        <span class="pub-product-badge"><?= e(t('products.featured')) ?></span>
                    <?php endif; ?>
                    <p class="pub-product-name"><?= e($p['name'] ?? '') ?></p>
                    <?php if (!empty($p['price'])): ?>
                        <p class="pub-product-price"><?= number_format((float)$p['price'], 2) ?> <?= e(t('common.currency')) ?></p>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <!-- Product pagination -->
        <?php
        $totalPg = (int)($productMeta['total_pages'] ?? 1);
        if ($totalPg > 1):
            $pg_url = fn(int $pg) => '?id=' . $entityId . '&page=' . $pg . '#tabProducts';
        ?>
        <nav class="pub-pagination" style="margin-top:24px;">
            <a href="<?= $pg_url(max(1,$productPage-1)) ?>" class="pub-page-btn <?= $productPage<=1?'disabled':'' ?>">
                <?= e(t('pagination.prev')) ?>
            </a>
            <?php for ($i=max(1,$productPage-2); $i<=min($totalPg,$productPage+2); $i++): ?>
                <a href="<?= $pg_url($i) ?>" class="pub-page-btn <?= $i===$productPage?'active':'' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <a href="<?= $pg_url(min($totalPg,$productPage+1)) ?>" class="pub-page-btn <?= $productPage>=$totalPg?'disabled':'' ?>">
                <?= e(t('pagination.next')) ?>
            </a>
        </nav>
        <?php endif; ?>
        <?php else: ?>
        <div class="pub-empty" style="margin-top:40px;">
            <div class="pub-empty-icon">🛍️</div>
            <p class="pub-empty-msg"><?= e(t('entity.no_products')) ?></p>
        </div>
        <?php endif; ?>
    </div>

    <!-- TAB: Info -->
    <div class="pub-tab-panel" id="tabInfo" style="display:none;">
        <div style="margin-top:20px;display:grid;gap:16px;">

            <!-- Attributes -->
            <?php if (!empty($entity['attributes'])): ?>
            <div class="pub-info-card">
                <h3 class="pub-info-card-title"><?= e(t('entity.details')) ?></h3>
                <div class="pub-attr-grid">
                    <?php foreach ($entity['attributes'] as $attr): ?>
                        <?php if (empty($attr['value'])) continue; ?>
                        <div class="pub-attr-row">
                            <span class="pub-attr-key"><?= e($attr['attribute_name'] ?? '') ?></span>
                            <span class="pub-attr-val"><?= e($attr['value'] ?? '') ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Payment Methods -->
            <?php if (!empty($entity['payment_methods'])): ?>
            <div class="pub-info-card">
                <h3 class="pub-info-card-title"><?= e(t('entity.payment_methods')) ?></h3>
                <div style="display:flex;gap:10px;flex-wrap:wrap;padding:12px 16px;">
                    <?php foreach ($entity['payment_methods'] as $pm): ?>
                        <span class="pub-tag" style="font-size:0.85rem;padding:6px 14px;">
                            <?= e($pm['icon'] ?? '💳') ?> <?= e($pm['name'] ?? '') ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB: Working Hours -->
    <div class="pub-tab-panel" id="tabHours" style="display:none;">
        <?php if (!empty($entity['working_hours'])): ?>
        <div class="pub-info-card" style="margin-top:20px;">
            <h3 class="pub-info-card-title">🕐 <?= e(t('entity.hours_tab')) ?></h3>
            <div class="pub-hours-table">
                <?php foreach ($entity['working_hours'] as $h): ?>
                <div class="pub-hours-row <?= !empty($h['is_closed']) ? 'pub-hours-row--closed' : '' ?>">
                    <span class="pub-hours-day"><?= e($dayNames[$h['day_of_week']] ?? $h['day_of_week']) ?></span>
                    <span class="pub-hours-time">
                        <?php if (!empty($h['is_closed'])): ?>
                            <span style="color:var(--pub-muted);"><?= e(t('entity.closed')) ?></span>
                        <?php else: ?>
                            <?= e($h['open_time'] ?? '') ?> — <?= e($h['close_time'] ?? '') ?>
                        <?php endif; ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- TAB: Map / Location -->
    <div class="pub-tab-panel" id="tabMap" style="display:none;">
        <?php if (!empty($entity['addresses'])): ?>
        <div style="margin-top:20px;display:grid;gap:16px;">
            <?php foreach ($entity['addresses'] as $addr): ?>
            <div class="pub-info-card">
                <h3 class="pub-info-card-title">
                    📍 <?= e($addr['label'] ?? '') ?>
                    <?php if (!empty($addr['is_primary'])): ?>
                        <span style="font-size:0.75rem;background:var(--pub-primary);color:#fff;padding:2px 8px;border-radius:20px;margin-inline-start:6px;">★</span>
                    <?php endif; ?>
                </h3>
                <p style="padding:8px 16px;color:var(--pub-text);margin:0;">
                    <?= e($addr['address_line1'] ?? '') ?>
                    <?php if (!empty($addr['address_line2'])): ?>, <?= e($addr['address_line2']) ?><?php endif; ?>
                </p>
                <?php if (!empty($addr['latitude']) && !empty($addr['longitude'])): ?>
                <div style="padding:0 16px 16px;">
                    <a href="https://www.openstreetmap.org/?mlat=<?= e($addr['latitude']) ?>&mlon=<?= e($addr['longitude']) ?>#map=16/<?= e($addr['latitude']) ?>/<?= e($addr['longitude']) ?>"
                       target="_blank" rel="noopener" class="pub-btn pub-btn--ghost pub-btn--sm" style="display:inline-flex;gap:6px;align-items:center;">
                        🗺️ <?= e(t('entity.view_on_map')) ?>
                    </a>
                    <a href="https://maps.google.com/?q=<?= e($addr['latitude']) ?>,<?= e($addr['longitude']) ?>"
                       target="_blank" rel="noopener" class="pub-btn pub-btn--ghost pub-btn--sm" style="display:inline-flex;gap:6px;align-items:center;margin-inline-start:8px;">
                        📍 Google Maps
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="pub-empty" style="margin-top:40px;">
            <div class="pub-empty-icon">📍</div>
            <p class="pub-empty-msg"><?= e(t('entity.no_addresses')) ?></p>
        </div>
        <?php endif; ?>
    </div>

</div><!-- /.pub-container -->

<script>
// Simple tab switcher
document.querySelectorAll('.pub-tab').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var tab = this.dataset.tab;
        document.querySelectorAll('.pub-tab').forEach(function(b) {
            b.classList.remove('active');
            b.setAttribute('aria-selected','false');
        });
        document.querySelectorAll('.pub-tab-panel').forEach(function(p) {
            p.style.display = 'none';
            p.classList.remove('active');
        });
        this.classList.add('active');
        this.setAttribute('aria-selected','true');
        var panel = document.getElementById('tab' + tab.charAt(0).toUpperCase() + tab.slice(1));
        if (panel) { panel.style.display = ''; panel.classList.add('active'); }
    });
});
</script>

<?php
// CSS additions for entity page
echo '<style>
.pub-entity-banner { width:100%; height:220px; overflow:hidden; background:var(--pub-surface); position:relative; }
@media(min-width:900px){ .pub-entity-banner { height:320px; } }
.pub-entity-banner-img { width:100%; height:100%; object-fit:cover; display:block; }
.pub-entity-banner-placeholder { width:100%; height:100%; background: linear-gradient(135deg, var(--pub-primary) 0%, var(--pub-accent) 100%); }
.pub-entity-profile-header { display:flex; gap:20px; align-items:flex-start; margin-top:-48px; position:relative; z-index:2; flex-wrap:wrap; }
.pub-entity-profile-logo { width:96px; height:96px; border-radius:16px; overflow:hidden; background:var(--pub-bg); border:3px solid var(--pub-border); flex-shrink:0; box-shadow:var(--pub-shadow); }
.pub-entity-profile-logo img { width:100%; height:100%; object-fit:cover; }
.pub-entity-profile-info { flex:1; min-width:0; padding-top:52px; }
.pub-entity-profile-name { font-size:1.4rem; font-weight:800; margin:0 0 6px; color:var(--pub-text); }
.pub-entity-profile-desc { font-size:0.92rem; color:var(--pub-muted); margin:0 0 12px; }
.pub-entity-contacts { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:10px; }
.pub-contact-item { font-size:0.85rem; color:var(--pub-primary); display:flex; align-items:center; gap:4px; }
.pub-contact-item:hover { text-decoration:underline; }
.pub-entity-social { display:flex; gap:8px; flex-wrap:wrap; }
.pub-social-btn { padding:5px 12px; border-radius:20px; font-size:0.8rem; font-weight:600; border:1px solid var(--pub-border); background:var(--pub-surface); color:var(--pub-text); transition:opacity 0.2s; }
.pub-social-btn:hover { opacity:0.8; color:var(--pub-text); }
.pub-tab-panel { padding-bottom:40px; }
.pub-info-card { background:var(--pub-bg); border:1px solid var(--pub-border); border-radius:var(--pub-radius); overflow:hidden; }
.pub-info-card-title { font-size:1rem; font-weight:700; margin:0; padding:12px 16px; border-bottom:1px solid var(--pub-border); color:var(--pub-text); }
.pub-attr-grid { padding:12px 16px; display:grid; gap:8px; }
.pub-attr-row { display:flex; gap:10px; align-items:baseline; flex-wrap:wrap; }
.pub-attr-key { font-size:0.82rem; font-weight:600; color:var(--pub-muted); min-width:120px; }
.pub-attr-val { font-size:0.88rem; color:var(--pub-text); }
.pub-hours-table { padding:8px 16px 16px; display:grid; gap:6px; }
.pub-hours-row { display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid var(--pub-border); }
.pub-hours-row:last-child { border-bottom:none; }
.pub-hours-row--closed { opacity:0.5; }
.pub-hours-day { font-weight:600; font-size:0.88rem; color:var(--pub-text); }
.pub-hours-time { font-size:0.88rem; color:var(--pub-muted); }
</style>';
?>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>
