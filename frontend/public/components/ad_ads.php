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
 */

if (empty($sectionData)) {
    return;
}

/**
 * Build the click-through URL from target_type / target_value.
 * target_type: 'url', 'product', 'category', 'entity', 'page', or empty
 */
function _ad_link(string $type, string $value): string {
    if ($value === '') return '#';
    return match ($type) {
        'url'      => $value,
        'product'  => '/frontend/public/product.php?id='   . urlencode($value),
        'category' => '/frontend/public/categories.php?id=' . urlencode($value),
        'entity'   => '/frontend/public/entity.php?id='    . urlencode($value),
        default    => $value,
    };
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
<a href="<?= e($_adHref) ?>" class="pub-ad-card" target="_blank" rel="noopener"
   data-ad-id="<?= $_adId ?>"
   onclick="fetch('/api/public/ads/<?= $_adId ?>/click',{method:'POST',keepalive:true}).catch(()=>{})">
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
