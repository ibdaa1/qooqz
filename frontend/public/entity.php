<?php
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
$entity = $resp['data']['data'] ?? [];

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
$selectedCat  = (int)($_GET['cat'] ?? 0);  // category filter

$rp = pub_fetch(
    pub_api_url('public/entity/' . ($entity['id'] ?? $entityId) . '/products')
    . '?' . $qs . '&per=' . $productLimit . '&page=' . $productPage
    . ($selectedCat ? '&category_id=' . $selectedCat : '')
);
$products    = $rp['data']['data'] ?? ($rp['data']['items'] ?? []);
$productMeta = $rp['data']['meta'] ?? ['total' => count($products), 'total_pages' => 1];

/* Fetch categories for this entity */
$catResp   = pub_fetch(pub_api_url('public/entity/' . ($entity['id'] ?? $entityId) . '/categories') . '?' . $qs);
$categories = $catResp['data']['data'] ?? [];

/* Fetch discounts for this entity */
$discResp  = pub_fetch(pub_api_url('public/entity/' . ($entity['id'] ?? $entityId) . '/discounts') . '?' . $qs);
$discounts = $discResp['data']['data'] ?? [];

$GLOBALS['PUB_PAGE_TITLE'] = e($entity['store_name'] ?? '') . ' — QOOQZ';
$GLOBALS['PUB_PAGE_DESC']  = e($entity['description'] ?? '');

/* -------------------------------------------------------
 * Open / Closed status — calculate from working_hours + current local time
 * working_hours is already in $entity from the API call
 * day_of_week: 0=Sun, 1=Mon … 6=Sat  (PHP: 0=Sun via date('w'))
 * ----------------------------------------------------- */
$entityIsOpen    = null;   // null = unknown (no hours data)
$entityOpenLabel = '';
$workingHoursArr = $entity['working_hours'] ?? [];
if (!empty($workingHoursArr)) {
    $nowDow   = (int)date('w');                  // 0(Sun)…6(Sat)
    $nowMins  = (int)date('H') * 60 + (int)date('i');
    foreach ($workingHoursArr as $h) {
        if ((int)($h['day_of_week'] ?? -1) !== $nowDow) continue;
        if (empty($h['is_open'])) {
            $entityIsOpen    = false;
            $entityOpenLabel = t('entity.closed');
            break;
        }
        $openMin  = 0;
        $closeMin = 24 * 60;
        if (!empty($h['open_time'])) {
            [$oh, $om] = array_map('intval', explode(':', $h['open_time']));
            $openMin = $oh * 60 + $om;
        }
        if (!empty($h['close_time'])) {
            [$ch, $cm] = array_map('intval', explode(':', $h['close_time']));
            $closeMin = $ch * 60 + $cm;
        }
        if ($nowMins >= $openMin && ($closeMin === 0 || $nowMins < $closeMin)) {
            $entityIsOpen    = true;
            $entityOpenLabel = t('entity.open_now');
        } else {
            $entityIsOpen    = false;
            $entityOpenLabel = t('entity.closed');
        }
        break;
    }
}

/* Day names */
$dayNames = [
    0 => t('entity.day_sunday'),
    1 => t('entity.day_monday'),
    2 => t('entity.day_tuesday'),
    3 => t('entity.day_wednesday'),
    4 => t('entity.day_thursday'),
    5 => t('entity.day_friday'),
    6 => t('entity.day_saturday'),
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
                <?php if ($entityIsOpen !== null): ?>
                    <span class="pub-open-badge <?= $entityIsOpen ? 'pub-open-badge--open' : 'pub-open-badge--closed' ?>">
                        <?= $entityIsOpen ? '🟢' : '🔴' ?> <?= e($entityOpenLabel) ?>
                    </span>
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

            <!-- Share button -->
            <div style="margin-top:12px;">
                <button class="pub-btn pub-btn--ghost pub-btn--sm" id="pubShareBtn"
                        onclick="pubShareEntity()" style="display:inline-flex;align-items:center;gap:6px;">
                    📤 <?= e(t('entity.share')) ?>
                </button>
                <div id="pubSharePanel" style="display:none;margin-top:10px;padding:12px;
                     background:var(--pub-surface);border:1px solid var(--pub-border);
                     border-radius:var(--pub-radius);max-width:320px;">
                    <p style="margin:0 0 10px;font-size:0.85rem;font-weight:600;"><?= e(t('entity.share')) ?></p>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <a href="https://api.whatsapp.com/send?text=<?= urlencode($entity['store_name'] ?? '') ?>%20" id="pubShareWA"
                           target="_blank" rel="noopener" class="pub-social-btn">💬 WhatsApp</a>
                        <a href="https://twitter.com/intent/tweet?text=<?= urlencode($entity['store_name'] ?? '') ?>&url=" id="pubShareTW"
                           target="_blank" rel="noopener" class="pub-social-btn">🐦 Twitter/X</a>
                        <button class="pub-social-btn" onclick="pubCopyLink()">🔗 <?= e(t('entity.copy_link')) ?></button>
                    </div>
                </div>
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
        <?php if (!empty($discounts)): ?>
        <button class="pub-tab" data-tab="discounts" role="tab"
                aria-selected="false" aria-controls="tabDiscounts">
            🏷️ <?= e(t('entity.discounts_tab')) ?>
            <span class="pub-tab-count"><?= count($discounts) ?></span>
        </button>
        <?php endif; ?>
    </div>

    <!-- TAB: Products -->
    <div class="pub-tab-panel active" id="tabProducts">
        <!-- Category filter tabs -->
        <?php if (!empty($categories)): ?>
        <div class="pub-cat-tabs" style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap;overflow-x:auto;padding-bottom:4px;">
            <a href="?id=<?= $entityId ?>"
               class="pub-cat-tab-btn <?= !$selectedCat ? 'active' : '' ?>">
                <?= e(t('entity.all_categories')) ?>
            </a>
            <?php foreach ($categories as $cat): ?>
            <a href="?id=<?= $entityId ?>&cat=<?= (int)($cat['id'] ?? 0) ?>"
               class="pub-cat-tab-btn <?= $selectedCat === (int)($cat['id'] ?? 0) ? 'active' : '' ?>">
                <?= e($cat['name'] ?? '') ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($products)): ?>
        <div class="pub-grid" style="margin-top:20px;">
            <?php foreach ($products as $p): ?>
            <div class="pub-product-card">
                <a href="/frontend/public/product.php?id=<?= (int)($p['id'] ?? 0) ?>"
                   style="text-decoration:none;display:block;">
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
                <button class="pub-cart-add-btn"
                        onclick="pubAddToCart(this)"
                        data-product-id="<?= (int)($p['id'] ?? 0) ?>"
                        data-product-name="<?= e($p['name'] ?? '') ?>"
                        data-product-price="<?= (float)($p['price'] ?? 0) ?>"
                        data-product-image="<?= e($p['image_url'] ?? '') ?>"
                        data-product-sku="<?= e($p['sku'] ?? '') ?>">
                    🛒 <?= e(t('cart.add')) ?>
                </button>
            </div>
            <?php endforeach; ?>
        </div>
        <!-- Product pagination -->
        <?php
        $totalPg = (int)($productMeta['total_pages'] ?? 1);
        if ($totalPg > 1):
            $pg_url = fn(int $pg) => '?id=' . $entityId . ($selectedCat ? '&cat=' . $selectedCat : '') . '&page=' . $pg . '#tabProducts';
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
                <div class="pub-hours-row <?= empty($h['is_open']) ? 'pub-hours-row--closed' : '' ?>">
                    <span class="pub-hours-day"><?= e($dayNames[(int)($h['day_of_week'] ?? 0)] ?? $h['day_of_week']) ?></span>
                    <span class="pub-hours-time">
                        <?php if (empty($h['is_open'])): ?>
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

<?php if (!empty($discounts)): ?>
<!-- TAB: Discounts (rendered outside container so it spans full context) -->
<?php endif; ?>

<!-- Discounts tab panel (inside container) -->
<div class="pub-container">

    <!-- TAB: Discounts panel -->
    <div class="pub-tab-panel" id="tabDiscounts" style="display:none;">
        <?php if (!empty($discounts)): ?>
        <div style="margin-top:20px;display:grid;gap:14px;">
            <?php foreach ($discounts as $d): ?>
            <div class="pub-discount-card">
                <?php if (!empty($d['marketing_badge'])): ?>
                    <span class="pub-discount-badge-top"><?= e($d['marketing_badge']) ?></span>
                <?php endif; ?>
                <div class="pub-discount-inner">
                    <div class="pub-discount-icon">🏷️</div>
                    <div class="pub-discount-body">
                        <p class="pub-discount-title"><?= e($d['title'] ?? $d['code'] ?? '') ?></p>
                        <?php if (!empty($d['description'])): ?>
                            <p class="pub-discount-desc"><?= e($d['description']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($d['code'])): ?>
                            <div class="pub-discount-code-row">
                                <span class="pub-discount-code"><?= e($d['code']) ?></span>
                                <button class="pub-btn pub-btn--ghost pub-btn--sm"
                                        onclick="pubCopyDiscount('<?= e(addslashes($d['code'])) ?>', this)">
                                    📋 <?= e(t('discounts.copy_code')) ?>
                                </button>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($d['ends_at'])): ?>
                            <p class="pub-discount-expires">
                                ⏰ <?= e(t('discounts.expires')) ?>: <?= e(substr($d['ends_at'], 0, 10)) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (!empty($d['terms_conditions'])): ?>
                    <details class="pub-discount-terms">
                        <summary><?= e(t('discounts.terms')) ?></summary>
                        <p><?= e($d['terms_conditions']) ?></p>
                    </details>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="pub-empty" style="margin-top:40px;">
            <div class="pub-empty-icon">🏷️</div>
            <p class="pub-empty-msg"><?= e(t('discounts.none')) ?></p>
        </div>
        <?php endif; ?>
    </div>

</div><!-- /.pub-container discounts -->

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

// Share panel toggle
function pubShareEntity() {
    var panel = document.getElementById('pubSharePanel');
    if (!panel) return;
    var isOpen = panel.style.display !== 'none';
    panel.style.display = isOpen ? 'none' : 'block';
    if (!isOpen) {
        var url = encodeURIComponent(window.location.href);
        var wa = document.getElementById('pubShareWA');
        var tw = document.getElementById('pubShareTW');
        if (wa) wa.href = 'https://api.whatsapp.com/send?text=' + encodeURIComponent(document.title + ' ') + url;
        if (tw) tw.href = 'https://twitter.com/intent/tweet?text=' + encodeURIComponent(document.title) + '&url=' + url;
    }
}

function pubCopyLink() {
    var url = window.location.href;
    if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(function() { alert('✅'); });
    } else {
        var ta = document.createElement('textarea');
        ta.value = url;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        alert('✅');
    }
}

function pubCopyDiscount(code, btn) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(code).then(function() {
            var orig = btn.textContent;
            btn.textContent = '✅';
            setTimeout(function() { btn.textContent = orig; }, 1800);
        });
    } else {
        var ta = document.createElement('textarea');
        ta.value = code;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        var orig = btn.textContent;
        btn.textContent = '✅';
        setTimeout(function() { btn.textContent = orig; }, 1800);
    }
}
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
/* Category filter tabs */
.pub-cat-tabs { border-bottom:1px solid var(--pub-border); margin-bottom:4px; }
.pub-cat-tab-btn { padding:7px 16px; border-radius:var(--pub-radius) var(--pub-radius) 0 0; font-size:0.85rem; font-weight:600;
  color:var(--pub-muted); text-decoration:none; white-space:nowrap; border:1px solid transparent;
  border-bottom:none; transition:background 0.15s,color 0.15s; display:inline-block; }
.pub-cat-tab-btn:hover { background:var(--pub-surface); color:var(--pub-text); }
.pub-cat-tab-btn.active { background:var(--pub-bg); color:var(--pub-primary); border-color:var(--pub-border);
  border-bottom-color:var(--pub-bg); margin-bottom:-1px; }
/* Cart add button on product card */
.pub-cart-add-btn { width:100%; margin-top:6px; padding:7px 0; background:var(--pub-primary,#03874e); color:#fff;
  border:none; border-radius:0 0 var(--pub-radius) var(--pub-radius); font-size:0.82rem; font-weight:600;
  cursor:pointer; transition:opacity 0.2s; }
.pub-cart-add-btn:hover { opacity:0.85; }
/* Open/closed badge */
.pub-open-badge { display:inline-flex; align-items:center; gap:4px; padding:3px 10px; border-radius:20px;
  font-size:0.78rem; font-weight:700; }
.pub-open-badge--open  { background:#d1fae5; color:#065f46; }
.pub-open-badge--closed{ background:#fee2e2; color:#991b1b; }
/* Tab count badge */
.pub-tab-count { background:var(--pub-primary); color:#fff; border-radius:20px; padding:1px 6px;
  font-size:0.72rem; font-weight:700; margin-inline-start:4px; }
/* Discount cards */
.pub-discount-card { background:var(--pub-surface); border:1px solid var(--pub-border); border-radius:var(--pub-radius);
  overflow:hidden; position:relative; }
.pub-discount-badge-top { position:absolute; top:0; right:0; background:var(--pub-accent,#F59E0B); color:#000;
  font-size:0.72rem; font-weight:800; padding:3px 10px;
  border-bottom-left-radius:var(--pub-radius); }
.pub-discount-inner { display:flex; gap:14px; padding:14px 16px; align-items:flex-start; }
.pub-discount-icon { font-size:2rem; flex-shrink:0; }
.pub-discount-body { flex:1; min-width:0; }
.pub-discount-title { font-size:1rem; font-weight:700; margin:0 0 4px; color:var(--pub-text); }
.pub-discount-desc  { font-size:0.85rem; color:var(--pub-muted); margin:0 0 8px; }
.pub-discount-code-row { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:6px; }
.pub-discount-code { font-family:monospace; font-size:0.9rem; font-weight:800; letter-spacing:2px;
  background:var(--pub-bg); border:1px dashed var(--pub-border); padding:4px 12px;
  border-radius:6px; color:var(--pub-primary); }
.pub-discount-expires { font-size:0.78rem; color:var(--pub-muted); margin:0; }
.pub-discount-terms { padding:8px 16px 12px; border-top:1px solid var(--pub-border); }
.pub-discount-terms summary { font-size:0.82rem; color:var(--pub-muted); cursor:pointer; }
.pub-discount-terms p { font-size:0.82rem; color:var(--pub-muted); margin:6px 0 0; }
/* Mobile entity profile */
@media(max-width:600px){
  .pub-entity-profile-header { flex-direction:column; align-items:center; text-align:center; gap:10px; margin-top:-36px; }
  .pub-entity-profile-info { padding-top:0; width:100%; }
  .pub-entity-profile-logo { width:72px; height:72px; margin:0 auto; }
  .pub-entity-profile-name { font-size:1.15rem; }
  .pub-entity-contacts { justify-content:center; }
  .pub-entity-social { justify-content:center; }
  .pub-attr-key { min-width:80px; }
  .pub-cat-tab-btn { padding:5px 10px; font-size:0.78rem; }
}
</style>';
?>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>
