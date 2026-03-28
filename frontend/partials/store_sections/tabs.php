<?php
/**
 * frontend/partials/store_sections/tabs.php
 * Store Tabs Navigation Section
 *
 * Expected variables:
 *   $discounts           — Discounts array
 *   $entityShowReviews   — Whether reviews tab is visible
 *   $entityRatingTotal   — Total rating count
 *   $sectionSettings     — Section JSON settings (optional)
 *   $activeSections      — Array of active section types for this page
 */

// Determine which tabs to show based on settings and active sections
$configuredTabs = $sectionSettings['tabs'] ?? ['products', 'info', 'hours', 'location', 'offers', 'reviews'];
?>

<div class="pub-container">
    <div class="pub-tabs" style="margin-top:24px;" role="tablist">
        <?php if (in_array('products', $configuredTabs)): ?>
        <button class="pub-tab active" data-tab="products" role="tab"
                aria-selected="true" aria-controls="tabProducts">
            🛍️ <?= e(t('entity.products_tab')) ?>
        </button>
        <?php endif; ?>
        <?php if (in_array('info', $configuredTabs)): ?>
        <button class="pub-tab" data-tab="info" role="tab"
                aria-selected="false" aria-controls="tabInfo">
            ℹ️ <?= e(t('entity.info_tab')) ?>
        </button>
        <?php endif; ?>
        <?php if (in_array('hours', $configuredTabs)): ?>
        <button class="pub-tab" data-tab="hours" role="tab"
                aria-selected="false" aria-controls="tabHours">
            🕐 <?= e(t('entity.hours_tab')) ?>
        </button>
        <?php endif; ?>
        <?php if (in_array('location', $configuredTabs)): ?>
        <button class="pub-tab" data-tab="map" role="tab"
                aria-selected="false" aria-controls="tabMap">
            🗺️ <?= e(t('entity.location_tab')) ?>
        </button>
        <?php endif; ?>
        <?php if (in_array('offers', $configuredTabs) && !empty($discounts)): ?>
        <button class="pub-tab" data-tab="discounts" role="tab"
                aria-selected="false" aria-controls="tabDiscounts">
            🏷️ <?= e(t('entity.discounts_tab')) ?>
            <span class="pub-tab-count"><?= count($discounts) ?></span>
        </button>
        <?php endif; ?>
        <?php if (in_array('reviews', $configuredTabs) && $entityShowReviews): ?>
        <button class="pub-tab" data-tab="ratings" role="tab"
                aria-selected="false" aria-controls="tabRatings">
            ⭐ <?= e(t('entity.ratings_tab')) ?>
            <?php if ($entityRatingTotal > 0): ?><span class="pub-tab-count"><?= $entityRatingTotal ?></span><?php endif; ?>
        </button>
        <?php endif; ?>
    </div>
</div>
