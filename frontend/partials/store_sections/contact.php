<?php
/**
 * frontend/partials/store_sections/contact.php
 * Store Contact Section — Phone, email, website, social links, share button
 *
 * Expected variables:
 *   $entity              — Entity data array
 *   $entityShowContactInfo — Whether to show contact info (bool)
 *   $sectionSettings     — Section JSON settings (optional)
 */

$showPhone   = ($sectionSettings['show_phone']   ?? true);
$showEmail   = ($sectionSettings['show_email']   ?? true);
$showWebsite = ($sectionSettings['show_website'] ?? true);
$showShare   = ($sectionSettings['show_share']   ?? true);
$showSocial  = ($sectionSettings['show_social']  ?? true);
?>

<div class="pub-container">
    <!-- Contact info -->
    <?php if ($entityShowContactInfo): ?>
    <div class="pub-entity-contacts">
        <?php if ($showPhone && !empty($entity['phone'])): ?>
            <a href="tel:<?= e($entity['phone']) ?>" class="pub-contact-item">
                📞 <?= e($entity['phone']) ?>
            </a>
        <?php endif; ?>
        <?php if ($showEmail && !empty($entity['email'])): ?>
            <a href="mailto:<?= e($entity['email']) ?>" class="pub-contact-item">
                📧 <?= e($entity['email']) ?>
            </a>
        <?php endif; ?>
        <?php if ($showWebsite && !empty($entity['website'])): ?>
            <a href="<?= e($entity['website']) ?>" target="_blank" rel="noopener" class="pub-contact-item">
                🌐 <?= e(parse_url($entity['website'], PHP_URL_HOST) ?: $entity['website']) ?>
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Social links -->
    <?php if ($showSocial): ?>
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
    <?php endif; ?>

    <!-- Share button -->
    <?php if ($showShare): ?>
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
    <?php endif; ?>
</div>
