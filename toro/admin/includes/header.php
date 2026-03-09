<?php
/**
 * TORO Admin — includes/header.php
 * Shared header, sidebar and top-bar for all admin pages.
 *
 * Expects $ADMIN_PAGE (string) and $ADMIN_TITLE (string) to be set by the caller.
 * Loads translations from /toro/admin/languages/admin/{lang}.json
 */
declare(strict_types=1);

if (php_sapi_name() === 'cli') return;

// ── Session ───────────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_secure'   => !empty($_SERVER['HTTPS']),
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
}

// ── Language helpers ──────────────────────────────────────────────────────────
$_adminLang = $_SESSION['lang'] ?? ($_GET['lang'] ?? 'ar');
if ($_GET['lang'] ?? null) {
    $_SESSION['lang'] = $_GET['lang'];
    $_adminLang = $_GET['lang'];
}
$_adminDir = in_array($_adminLang, ['ar', 'fa', 'he', 'ur'], true) ? 'rtl' : 'ltr';

$_i18nFile = __DIR__ . '/../languages/admin/' . $_adminLang . '.json';
if (!file_exists($_i18nFile)) {
    $_i18nFile = __DIR__ . '/../languages/admin/ar.json';
}
$_t = json_decode(file_get_contents($_i18nFile), true) ?? [];

if (!function_exists('t')) {
    function t(string $key, array $vars = []): string {
        global $_t;
        $val = $_t[$key] ?? $key;
        foreach ($vars as $k => $v) {
            $val = str_replace('{' . $k . '}', (string)$v, $val);
        }
        return htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
    }
}

$_brandName = $_t['brand'] ?? 'TORO';
$_pageTitle = $ADMIN_TITLE ?? $_brandName;

// ── Sidebar navigation ────────────────────────────────────────────────────────
$_navItems = [
    ['key' => 'nav.dashboard',    'icon' => 'grid',          'href' => '/toro/admin/index.php',             'page' => 'index'],
    ['key' => 'nav.images',       'icon' => 'image',         'href' => '/toro/admin/pages/images.php',      'page' => 'images'],
    ['key' => 'nav.brands',       'icon' => 'tag',           'href' => '/toro/admin/pages/brands.php',      'page' => 'brands'],
    ['key' => 'nav.categories',   'icon' => 'folder',        'href' => '/toro/admin/pages/categories.php',  'page' => 'categories'],
    ['key' => 'nav.products',     'icon' => 'box',           'href' => '/toro/admin/pages/products.php',    'page' => 'products'],
    ['key' => 'nav.attributes',   'icon' => 'sliders',       'href' => '/toro/admin/pages/attributes.php',  'page' => 'attributes'],
    ['key' => 'nav.banners',      'icon' => 'layout',        'href' => '/toro/admin/pages/banners.php',     'page' => 'banners'],
    ['key' => 'nav.orders',       'icon' => 'shopping-cart', 'href' => '/toro/admin/pages/orders.php',      'page' => 'orders'],
    ['key' => 'nav.coupons',      'icon' => 'percent',       'href' => '/toro/admin/pages/coupons.php',     'page' => 'coupons'],
    ['key' => 'nav.users',        'icon' => 'users',         'href' => '/toro/admin/pages/users.php',       'page' => 'users'],
    ['key' => 'nav.menus',        'icon' => 'menu',          'href' => '/toro/admin/pages/menus.php',       'page' => 'menus'],
    ['key' => 'nav.pages',        'icon' => 'file-text',     'href' => '/toro/admin/pages/pages.php',       'page' => 'pages'],
    ['key' => 'nav.translations', 'icon' => 'globe',         'href' => '/toro/admin/pages/translations.php','page' => 'translations'],
    ['key' => 'nav.settings',     'icon' => 'settings',      'href' => '/toro/admin/pages/settings.php',   'page' => 'settings'],
    ['key' => 'nav.roles',        'icon' => 'shield',        'href' => '/toro/admin/pages/roles.php',       'page' => 'roles'],
    ['key' => 'nav.audit_logs',   'icon' => 'file-text',     'href' => '/toro/admin/pages/audit_logs.php',  'page' => 'audit_logs'],
];

$_currentPage = $ADMIN_PAGE ?? 'index';
$_isRtl = ($_adminDir === 'rtl');
$_sidePos = $_isRtl ? 'right' : 'left';
$_marginSide = $_isRtl ? 'margin-right' : 'margin-left';
$_translateOff = $_isRtl ? 'translateX(100%)' : 'translateX(-100%)';
$_textAlign = $_isRtl ? 'right' : 'left';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_adminLang) ?>" dir="<?= $_adminDir ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($_pageTitle) ?> — <?= htmlspecialchars($_brandName) ?></title>
<meta name="robots" content="noindex,nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap">
<style>
/* ═══════════════════════════════════════════════════
   TORO Admin – Design System  (v1)
═══════════════════════════════════════════════════ */
:root{
  --clr-bg:#0f172a;--clr-surface:#1e293b;--clr-border:#334155;
  --clr-primary:#6366f1;--clr-primary-h:#4f46e5;
  --clr-danger:#ef4444;--clr-success:#22c55e;--clr-warning:#f59e0b;
  --clr-text:#f1f5f9;--clr-muted:#94a3b8;--clr-sidebar:#1e293b;
  --sidebar-w:256px;--topbar-h:56px;--radius:8px;
  --shadow:0 4px 24px rgba(0,0,0,.35);--t:.2s ease;
  --font-ar:'IBM Plex Sans Arabic',system-ui,sans-serif;
  --font-en:'Inter',system-ui,sans-serif;
}
[dir=rtl]{font-family:var(--font-ar)}
[dir=ltr]{font-family:var(--font-en)}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;background:var(--clr-bg);color:var(--clr-text)}
a{color:inherit;text-decoration:none}
button{cursor:pointer;font-family:inherit;font-size:inherit}
img{max-width:100%}

/* Layout */
.admin-layout{display:flex;min-height:100vh}

/* Sidebar */
.sidebar{
  position:fixed;top:0;<?= $_sidePos ?>:0;
  width:var(--sidebar-w);height:100vh;
  background:var(--clr-sidebar);
  border-<?= $_isRtl?'left':'right' ?>:1px solid var(--clr-border);
  display:flex;flex-direction:column;overflow-y:auto;
  z-index:200;transition:transform var(--t);
}
.sidebar.collapsed{transform:<?= $_translateOff ?>}
.sidebar-brand{
  display:flex;align-items:center;gap:.75rem;
  padding:1.25rem 1.25rem 1rem;
  border-bottom:1px solid var(--clr-border);
  font-size:1.2rem;font-weight:700;color:var(--clr-primary);letter-spacing:.04em;
}
.sidebar-nav{padding:.5rem 0;flex:1}
.sidebar-nav ul{list-style:none}
.sidebar-nav li a{
  display:flex;align-items:center;gap:.75rem;
  padding:.6rem 1.25rem;
  color:var(--clr-muted);font-size:.875rem;
  transition:background var(--t),color var(--t);
}
.sidebar-nav li a:hover,.sidebar-nav li.active a{
  background:rgba(99,102,241,.12);color:var(--clr-primary);
}
.sidebar-nav li.active a{font-weight:600}
.sidebar-nav li a svg{flex-shrink:0;width:15px;height:15px}
.sidebar-footer{
  padding:.875rem 1.25rem;border-top:1px solid var(--clr-border);
  font-size:.75rem;color:var(--clr-muted);
}

/* Main wrapper */
.main-wrapper{
  flex:1;<?= $_marginSide ?>:var(--sidebar-w);
  display:flex;flex-direction:column;min-height:100vh;
  transition:<?= $_marginSide ?> var(--t);
}
.main-wrapper.sidebar-hidden{<?= $_marginSide ?>:0}

/* Topbar */
.topbar{
  position:sticky;top:0;height:var(--topbar-h);
  background:var(--clr-surface);border-bottom:1px solid var(--clr-border);
  display:flex;align-items:center;justify-content:space-between;
  padding:0 1.25rem;z-index:100;gap:1rem;
}
.topbar-left,.topbar-right{display:flex;align-items:center;gap:.75rem}
.btn-icon{
  background:none;border:none;color:var(--clr-muted);
  padding:.375rem;border-radius:var(--radius);
  display:flex;align-items:center;transition:color var(--t),background var(--t);
}
.btn-icon:hover{color:var(--clr-text);background:rgba(255,255,255,.06)}
.topbar-search{
  background:var(--clr-bg);border:1px solid var(--clr-border);
  border-radius:var(--radius);color:var(--clr-text);
  padding:.35rem .75rem;width:200px;font-size:.875rem;
  outline:none;transition:border-color var(--t);font-family:inherit;
}
.topbar-search:focus{border-color:var(--clr-primary)}
.topbar-search::placeholder{color:var(--clr-muted)}
.topbar-page-title{font-weight:600;font-size:.9375rem}
.topbar-avatar{
  width:30px;height:30px;border-radius:50%;background:var(--clr-primary);
  display:flex;align-items:center;justify-content:center;
  font-size:.75rem;font-weight:700;color:#fff;flex-shrink:0;
}
.lang-btn{
  font-size:.7rem;font-weight:700;padding:.25rem .45rem;
  border:1px solid var(--clr-border);border-radius:4px;
  color:var(--clr-muted);background:none;transition:all var(--t);
}
.lang-btn:hover,.lang-btn.active{border-color:var(--clr-primary);color:var(--clr-primary)}

/* Page content */
.page-content{
  flex:1;padding:1.5rem 1.75rem;
  max-width:1440px;width:100%;
}

/* Card */
.card{background:var(--clr-surface);border:1px solid var(--clr-border);border-radius:var(--radius);padding:1.5rem;margin-bottom:1.5rem}
.card-header{
  display:flex;align-items:center;justify-content:space-between;
  margin-bottom:1.25rem;padding-bottom:.875rem;border-bottom:1px solid var(--clr-border);
}
.card-title{font-size:1rem;font-weight:600}

/* Buttons */
.btn{
  display:inline-flex;align-items:center;gap:.45rem;
  padding:.5rem 1rem;border-radius:var(--radius);border:1px solid transparent;
  font-weight:500;font-size:.875rem;transition:all var(--t);
  white-space:nowrap;line-height:1.4;font-family:inherit;
}
.btn-primary{background:var(--clr-primary);color:#fff}
.btn-primary:hover{background:var(--clr-primary-h)}
.btn-danger{background:var(--clr-danger);color:#fff}
.btn-danger:hover{opacity:.88}
.btn-success{background:var(--clr-success);color:#fff}
.btn-outline{background:transparent;border-color:var(--clr-border);color:var(--clr-text)}
.btn-outline:hover{background:rgba(255,255,255,.06)}
.btn-sm{padding:.3rem .625rem;font-size:.8125rem}
.btn-xs{padding:.2rem .45rem;font-size:.75rem}

/* Table */
.table-responsive{overflow-x:auto;border-radius:var(--radius);border:1px solid var(--clr-border)}
.table{width:100%;border-collapse:collapse;font-size:.875rem}
.table th,.table td{
  padding:.75rem 1rem;text-align:<?= $_textAlign ?>;
  border-bottom:1px solid var(--clr-border);
}
.table th{
  color:var(--clr-muted);font-weight:600;font-size:.75rem;
  text-transform:uppercase;letter-spacing:.04em;
  background:rgba(0,0,0,.15);
}
.table tbody tr:last-child td{border-bottom:none}
.table tbody tr:hover{background:rgba(255,255,255,.03)}
.table-actions{display:flex;gap:.4rem;align-items:center}

/* Form */
.form-group{margin-bottom:1rem}
.form-label{display:block;margin-bottom:.35rem;font-size:.8125rem;color:var(--clr-muted);font-weight:500}
.form-control{
  width:100%;background:var(--clr-bg);border:1px solid var(--clr-border);
  border-radius:var(--radius);color:var(--clr-text);
  padding:.5rem .75rem;font-size:.875rem;font-family:inherit;
  outline:none;transition:border-color var(--t);
}
.form-control:focus{border-color:var(--clr-primary)}
.form-control::placeholder{color:var(--clr-muted)}
select.form-control{
  background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%2394a3b8' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
  background-repeat:no-repeat;background-position:<?= $_isRtl?'left .75rem center':'right .75rem center' ?>;
  padding-<?= $_isRtl?'left':'right' ?>:2rem;
}
textarea.form-control{resize:vertical;min-height:90px}
.form-check{display:flex;align-items:center;gap:.5rem}
.form-check input[type=checkbox]{width:16px;height:16px;accent-color:var(--clr-primary)}

.form-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
.form-grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem}
@media(max-width:768px){.form-grid-2,.form-grid-3{grid-template-columns:1fr}}

/* Badge */
.badge{display:inline-block;padding:.2rem .55rem;border-radius:999px;font-size:.7rem;font-weight:600}
.badge-success{background:rgba(34,197,94,.15);color:var(--clr-success)}
.badge-danger{background:rgba(239,68,68,.15);color:var(--clr-danger)}
.badge-warning{background:rgba(245,158,11,.15);color:var(--clr-warning)}

/* Image thumb */
.img-thumb{width:42px;height:42px;object-fit:cover;border-radius:6px;border:1px solid var(--clr-border);background:var(--clr-bg)}
.img-thumb-lg{width:72px;height:72px;object-fit:cover;border-radius:8px;border:1px solid var(--clr-border)}

/* Alert */
.alert{padding:.75rem 1rem;border-radius:var(--radius);margin-bottom:1rem;font-size:.875rem}
.alert-success{background:rgba(34,197,94,.1);border:1px solid var(--clr-success);color:var(--clr-success)}
.alert-danger{background:rgba(239,68,68,.1);border:1px solid var(--clr-danger);color:var(--clr-danger)}

/* Modal */
.modal-backdrop{
  display:none;position:fixed;inset:0;
  background:rgba(0,0,0,.65);z-index:900;
  align-items:center;justify-content:center;
}
.modal-backdrop.open{display:flex}
.modal{
  background:var(--clr-surface);border:1px solid var(--clr-border);
  border-radius:var(--radius);padding:1.5rem;
  width:100%;max-width:600px;max-height:90vh;overflow-y:auto;
  box-shadow:var(--shadow);
}
.modal-header{
  display:flex;align-items:center;justify-content:space-between;
  margin-bottom:1.25rem;padding-bottom:.875rem;border-bottom:1px solid var(--clr-border);
}
.modal-title{font-size:1rem;font-weight:600}

/* Page header */
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:.75rem}
.page-header h1{font-size:1.375rem;font-weight:700}

/* Helpers */
.d-flex{display:flex}.gap-2{gap:.5rem}.gap-3{gap:.75rem}
.align-center{align-items:center}.justify-between{justify-content:space-between}
.text-muted{color:var(--clr-muted);font-size:.875rem}
.text-danger{color:var(--clr-danger)}
.text-success{color:var(--clr-success)}
.ms-auto{margin-<?= $_isRtl?'right':'left' ?>:auto}
.mt-1{margin-top:.25rem}.mt-2{margin-top:.5rem}.mb-2{margin-bottom:.5rem}.mb-3{margin-bottom:.75rem}
.p-2{padding:.5rem}.fw-600{font-weight:600}

/* Drop zone */
.drop-zone{
  border:2px dashed var(--clr-border);border-radius:var(--radius);
  padding:2rem;text-align:center;color:var(--clr-muted);
  transition:border-color var(--t),background var(--t);cursor:pointer;
}
.drop-zone:hover,.drop-zone.over{border-color:var(--clr-primary);background:rgba(99,102,241,.04)}

/* Spinner */
.spinner{
  display:inline-block;width:18px;height:18px;
  border:2px solid var(--clr-border);border-top-color:var(--clr-primary);
  border-radius:50%;animation:spin .7s linear infinite;vertical-align:middle;
}
@keyframes spin{to{transform:rotate(360deg)}}

@media(max-width:1024px){
  .sidebar{transform:<?= $_translateOff ?>}
  .sidebar.open{transform:translateX(0)}
  .main-wrapper{<?= $_marginSide ?>:0}
}
</style>
</head>
<body>
<div class="admin-layout">

<!-- ══ SIDEBAR ══════════════════════════════════════════════ -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
    <?= htmlspecialchars($_brandName) ?>
  </div>
  <nav class="sidebar-nav">
    <ul>
<?php foreach ($_navItems as $_item): ?>
      <li class="<?= ($_currentPage === $_item['page'] ? 'active' : '') ?>">
        <a href="<?= htmlspecialchars($_item['href']) ?>">
          <svg data-feather="<?= htmlspecialchars($_item['icon']) ?>"></svg>
          <?= t($_item['key']) ?>
        </a>
      </li>
<?php endforeach; ?>
    </ul>
  </nav>
  <div class="sidebar-footer"><?= htmlspecialchars($_brandName) ?> Admin v1</div>
</aside>

<!-- ══ MAIN WRAPPER ═════════════════════════════════════════ -->
<div class="main-wrapper" id="mainWrapper">

  <!-- Topbar -->
  <header class="topbar">
    <div class="topbar-left">
      <button class="btn-icon" id="sidebarToggle" title="<?= t('toggle_sidebar') ?>">
        <svg data-feather="menu"></svg>
      </button>
      <span class="topbar-page-title"><?= htmlspecialchars($_pageTitle) ?></span>
    </div>
    <div class="topbar-right">
      <input type="search" class="topbar-search" id="globalSearch" placeholder="<?= t('search_placeholder') ?>">
      <button class="btn-icon" title="<?= t('nav.notifications') ?>">
        <svg data-feather="bell"></svg>
      </button>
      <a href="?lang=ar" class="lang-btn <?= ($_adminLang==='ar'?'active':'') ?>">AR</a>
      <a href="?lang=en" class="lang-btn <?= ($_adminLang==='en'?'active':'') ?>">EN</a>
      <div class="topbar-avatar"><?= strtoupper(substr((string)($_SESSION['user_name'] ?? 'A'), 0, 1)) ?></div>
      <a href="/toro/admin/logout.php" class="btn-icon" title="<?= t('logout') ?>">
        <svg data-feather="log-out"></svg>
      </a>
    </div>
  </header>

  <!-- Page content (closed by footer.php) -->
  <main class="page-content">
