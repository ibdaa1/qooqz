<?php
declare(strict_types=1);
/**
 * Component: ad_ads
 * Renders paid advertisement units from the ads tables
 * (ad_campaigns → ads → ad_placement_items).
 *
 * $sectionData rows contain: id, title, description, image_url, thumb_url,
 *   target_type, target_value, priority, weight
 *
 * Available variables: $section, $sectionData, $lang, $tenantId, $apiBase,
 *   $_cardStyles
 *
 * Tracking:
 *   Views  — recorded via Intersection Observer when the ad enters the viewport.
 *   Clicks — recorded on anchor click via fetch POST to /api/public/ads/{id}/click.
 *   Both call POST /api/public/ads/{id}/view|click which upserts into ad_stats.
 */

if (empty($sectionData)) {
    return;
}

/**
 * Build the click-through URL from target_type / target_value.
 * target_type: 'url', 'product', 'category', 'entity', 'page', or empty
 * Only http/https URLs are allowed for target_type='url' to prevent XSS.
 */
if (!function_exists('_ad_link')) {
    function _ad_link(string $type, string $value): string {
        if ($value === '') return '#';
        return match ($type) {
            'url' => (function (string $v): string {
                $parsed = parse_url($v, PHP_URL_SCHEME);
                return ($parsed !== null && in_array(strtolower($parsed), ['http', 'https'], true))
                    ? $v
                    : '#';
            })($value),
            'product'  => '/frontend/public/product.php?id='    . urlencode($value),
            'category' => '/frontend/public/categories.php?id=' . urlencode($value),
            'entity'   => '/frontend/public/entity.php?id='     . urlencode($value),
            default    => '#',
        };
    }
}
?>
<div class="pub-ads-grid">
<?php foreach ($sectionData as $_ad):
    $_adId    = (int)($_ad['id'] ?? 0);
    $_adTitle = $_ad['title'] ?? '';
    $_adDesc  = $_ad['description'] ?? '';
    $_adImg   = $_ad['image_url'] ?? ($_ad['thumb_url'] ?? '');
    $_adType  = $_ad['target_type'] ?? '';
    $_adVal   = $_ad['target_value'] ?? '';
    $_adHref  = _ad_link($_adType, $_adVal);
?>
<a href="<?= e($_adHref) ?>" class="pub-ad-card" target="_blank" rel="noopener noreferrer"
   data-ad-id="<?= (int)$_adId ?>"
   onclick="fetch('/api/public/ads/<?= (int)$_adId ?>/click',{method:'POST',keepalive:true}).catch(()=>{})">
    <?php if ($_adImg !== ''): ?>
    <div class="pub-ad-img-wrap">
        <img src="<?= e(pub_img($_adImg)) ?>" alt="<?= e($_adTitle) ?>"
             class="pub-ad-img" loading="lazy">
    </div>
    <?php endif; ?>
    <?php if ($_adTitle !== '' || $_adDesc !== ''): ?>
    <div class="pub-ad-body">
        <?php if ($_adTitle !== ''): ?>
        <p class="pub-ad-title"><?= e($_adTitle) ?></p>
        <?php endif; ?>
        <?php if ($_adDesc !== ''): ?>
        <p class="pub-ad-desc"><?= e($_adDesc) ?></p>
        <?php endif; ?>
        <span class="pub-ad-badge"><?= e(t('ads.sponsored', 'إعلان')) ?></span>
    </div>
    <?php endif; ?>
</a>
<?php endforeach; ?>
</div>
<script>
(function () {
    'use strict';
    // Track ad views using IntersectionObserver.
    // A view is counted once per page load when at least 50% of the ad card
    // is visible for at least one second (prevents instant scroll-past counts).
    if (!('IntersectionObserver' in window)) return;

    var viewed = new Set();
    var timers  = {};

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            var el   = entry.target;
            var adId = el.dataset.adId;
            if (!adId || viewed.has(adId)) return;

            if (entry.isIntersecting) {
                // Start a 1-second dwell timer (guard prevents duplicate timers
                // when the card briefly leaves and re-enters the viewport)
                if (!timers[adId]) timers[adId] = setTimeout(function () {
                    if (!viewed.has(adId)) {
                        viewed.add(adId);
                        fetch('/api/public/ads/' + adId + '/view', {
                            method: 'POST',
                            keepalive: true
                        }).catch(function () {});
                    }
                    delete timers[adId];
                }, 1000);
            } else {
                // Card left viewport before 1 s — cancel pending timer
                if (timers[adId]) {
                    clearTimeout(timers[adId]);
                    delete timers[adId];
                }
            }
        });
    }, { threshold: 0.5 });

    document.querySelectorAll('.pub-ad-card[data-ad-id]').forEach(function (el) {
        observer.observe(el);
    });
})();
</script>
