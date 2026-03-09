<?php
/**
 * TORO Admin — index.php  (Dashboard)
 */
declare(strict_types=1);

$ADMIN_PAGE  = 'index';
$ADMIN_TITLE = 'لوحة التحكم';

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <h1><?= t('nav.dashboard') ?></h1>
  <span class="text-muted"><?= t('dashboard_subtitle') ?></span>
</div>

<!-- Welcome card -->
<div class="card" style="background:linear-gradient(135deg,rgba(99,102,241,.18),rgba(99,102,241,.06))">
  <div style="display:flex;align-items:center;gap:1rem">
    <div style="font-size:2.5rem">👋</div>
    <div>
      <h3 style="font-size:1.125rem;font-weight:600;margin-bottom:.25rem"><?= t('welcome_title') ?></h3>
      <p class="text-muted" style="margin:0"><?= t('welcome_message') ?></p>
    </div>
  </div>
</div>

<!-- Quick Actions -->
<div class="card">
  <div class="card-header">
    <span class="card-title"><?= t('quick_actions_title') ?></span>
  </div>
  <div class="form-grid-3" style="gap:1rem">
    <?php
    $actions = [
      ['icon'=>'image',   'key'=>'manage_images',     'href'=>'/toro/admin/pages/images.php',   'color'=>'#6366f1'],
      ['icon'=>'tag',     'key'=>'manage_brands',     'href'=>'/toro/admin/pages/brands.php',   'color'=>'#22c55e'],
      ['icon'=>'box',     'key'=>'manage_products',   'href'=>'/toro/admin/pages/products.php', 'color'=>'#f59e0b'],
      ['icon'=>'folder',  'key'=>'manage_categories', 'href'=>'/toro/admin/pages/categories.php','color'=>'#0ea5e9'],
      ['icon'=>'shopping-cart','key'=>'manage_orders','href'=>'/toro/admin/pages/orders.php',   'color'=>'#a855f7'],
      ['icon'=>'users',   'key'=>'manage_users',      'href'=>'/toro/admin/pages/users.php',    'color'=>'#ef4444'],
    ];
    foreach ($actions as $a): ?>
    <a href="<?= htmlspecialchars($a['href']) ?>" style="
      display:flex;align-items:center;gap:.875rem;
      padding:1rem 1.25rem;border-radius:var(--radius);
      border:1px solid var(--clr-border);background:var(--clr-bg);
      transition:all var(--t);text-decoration:none;
    " onmouseenter="this.style.borderColor='<?= $a['color'] ?>'" onmouseleave="this.style.borderColor='var(--clr-border)'">
      <div style="width:40px;height:40px;border-radius:10px;background:<?= $a['color'] ?>22;color:<?= $a['color'] ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <svg data-feather="<?= $a['icon'] ?>" width="18" height="18"></svg>
      </div>
      <span style="font-weight:500"><?= t($a['key']) ?></span>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
