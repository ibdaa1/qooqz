<?php
/**
 * frontend/partials/store_sections/info.php
 * Store Info Section — Description, business details, attributes, payment methods, settings
 *
 * Expected variables:
 *   $entity, $entitySettings, $lang
 *   $sectionSettings — Section JSON settings
 */

$showDescription    = ($sectionSettings['show_description']     ?? true);
$showAttributes     = ($sectionSettings['show_attributes']      ?? true);
$showPaymentMethods = ($sectionSettings['show_payment_methods'] ?? true);
$showSettings       = ($sectionSettings['show_settings']        ?? true);
?>

<div class="pub-tab-panel" id="tabInfo" style="display:none;">
    <div style="margin-top:20px;display:grid;gap:16px;">

        <!-- Attributes -->
        <?php if ($showAttributes && !empty($entity['attributes'])): ?>
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
        <?php if ($showPaymentMethods && !empty($entity['payment_methods'])): ?>
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

        <!-- Entity Settings / Business Info -->
        <?php
        $_esRows = [];
        if ($showSettings && !empty($entitySettings)) {
            if ((float)($entitySettings['min_order_amount'] ?? 0) > 0)
                $_esRows[] = ['🛒', t('entity.settings_min_order'), number_format((float)$entitySettings['min_order_amount'], 2) . ' ' . e(t('common.currency'))];
            if ((int)($entitySettings['preparation_time_minutes'] ?? 0) > 0)
                $_esRows[] = ['⏱️', t('entity.settings_prep_time'), (int)$entitySettings['preparation_time_minutes'] . ' ' . e(t('entity.settings_minutes'))];
            if ((float)($entitySettings['delivery_radius_km'] ?? 0) > 0)
                $_esRows[] = ['🚚', t('entity.settings_delivery_radius'), (float)$entitySettings['delivery_radius_km'] . ' ' . e(t('entity.settings_km'))];
            if ((float)($entitySettings['free_delivery_min_order'] ?? 0) > 0)
                $_esRows[] = ['🆓', t('entity.settings_free_delivery'), number_format((float)$entitySettings['free_delivery_min_order'], 2) . ' ' . e(t('common.currency'))];
            if (!empty($entitySettings['allow_online_booking']))
                $_esRows[] = ['📅', t('entity.settings_online_booking'), e(t('common.yes'))];
            if (!empty($entitySettings['booking_window_days']) && (int)$entitySettings['booking_window_days'] > 0)
                $_esRows[] = ['📆', t('entity.settings_booking_window'), (int)$entitySettings['booking_window_days'] . ' ' . e(t('entity.settings_days'))];
            if (!empty($entitySettings['max_bookings_per_slot']) && (int)$entitySettings['max_bookings_per_slot'] > 0)
                $_esRows[] = ['👥', t('entity.settings_max_per_slot'), (int)$entitySettings['max_bookings_per_slot']];
            if (isset($entitySettings['booking_cancellation_allowed']))
                $_esRows[] = ['↩️', t('entity.settings_cancellation'), !empty($entitySettings['booking_cancellation_allowed']) ? e(t('common.yes')) : e(t('common.no'))];
            if (!empty($entitySettings['allow_preorders']))
                $_esRows[] = ['📋', t('entity.settings_preorders'), e(t('common.yes'))];
            if (!empty($entitySettings['allow_cod']))
                $_esRows[] = ['💵', t('entity.settings_cod'), e(t('common.yes'))];
            if (!empty($entitySettings['auto_accept_orders']))
                $_esRows[] = ['✅', t('entity.settings_auto_accept'), e(t('common.yes'))];
            if (!empty($entitySettings['max_daily_orders']) && (int)$entitySettings['max_daily_orders'] > 0)
                $_esRows[] = ['📦', t('entity.settings_max_daily'), (int)$entitySettings['max_daily_orders']];
            if (!empty($entitySettings['default_payment_method']))
                $_esRows[] = ['💳', t('entity.settings_default_payment'), e($entitySettings['default_payment_method'])];
            if (!empty($entitySettings['featured_in_app']))
                $_esRows[] = ['⭐', t('entity.settings_featured'), e(t('common.yes'))];
        }
        if (!empty($_esRows)):
        ?>
        <div class="pub-info-card">
            <h3 class="pub-info-card-title">⚙️ <?= e(t('entity.settings_title')) ?></h3>
            <div class="pub-attr-grid">
                <?php foreach ($_esRows as [$icon, $label, $value]): ?>
                <div class="pub-attr-row">
                    <span class="pub-attr-key"><?= $icon ?> <?= $label ?></span>
                    <span class="pub-attr-val"><?= $value ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
