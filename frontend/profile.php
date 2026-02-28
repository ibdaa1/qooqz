<?php
/**
 * frontend/profile.php
 * QOOQZ — Public User Profile + Address Management Page
 * Requires authentication; redirects to login if not logged in.
 */

require_once __DIR__ . '/includes/public_context.php';

$ctx  = $GLOBALS['PUB_CONTEXT'];
$user = $ctx['user'] ?? null;

// Require login
if (empty($user['id'])) {
    header('Location: /frontend/login.php?redirect=' . urlencode('/frontend/profile.php'));
    exit;
}

$GLOBALS['PUB_APP_NAME']   = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH']  = '/frontend/public';
$GLOBALS['PUB_PAGE_TITLE'] = e(t('nav.account')) . ' — QOOQZ';

$uId    = (int)$user['id'];
$uName  = $user['name']     ?? $user['username'] ?? '';
$uEmail = $user['email']    ?? '';
$uLang  = $user['preferred_language'] ?? $ctx['lang'] ?? 'en';
$dir    = $ctx['dir'] ?? 'ltr';
$lang   = $ctx['lang'];

// Load addresses from DB directly
$addresses = [];
$pdo = pub_get_pdo();
if ($pdo) {
    try {
        $st = $pdo->prepare(
            "SELECT a.id, a.address_line1, a.address_line2, a.postal_code, a.is_primary,
                    c.name AS city_name, co.name AS country_name
               FROM addresses a
          LEFT JOIN cities c ON c.id = a.city_id
          LEFT JOIN countries co ON co.id = a.country_id
              WHERE a.owner_id = ? AND a.owner_type = 'user'
              ORDER BY a.is_primary DESC, a.id DESC"
        );
        $st->execute([$uId]);
        $addresses = $st->fetchAll();
    } catch (Throwable $_) {}

    // Load countries for the add-address form
    $countries = [];
    try {
        $st2 = $pdo->query('SELECT id, name FROM countries ORDER BY name ASC');
        $countries = $st2->fetchAll();
    } catch (Throwable $_) {}
}

include __DIR__ . '/partials/header.php';
?>

<main class="pub-container" style="padding:40px 0 60px;">

    <!-- Breadcrumb -->
    <nav style="font-size:0.84rem;color:var(--pub-muted);margin-bottom:24px;" aria-label="breadcrumb">
        <a href="/frontend/public/index.php"><?= e(t('nav.home')) ?></a>
        <span style="margin:0 6px;">›</span>
        <span><?= e(t('nav.account')) ?></span>
    </nav>

    <div style="max-width:760px;margin:0 auto;">

        <!-- Avatar + Name -->
        <div style="display:flex;align-items:center;gap:20px;margin-bottom:28px;
                    padding:24px;background:var(--pub-surface);border-radius:16px;
                    border:1px solid var(--pub-border);">
            <div style="width:64px;height:64px;border-radius:50%;
                        background:var(--pub-primary);display:flex;align-items:center;
                        justify-content:center;font-size:1.6rem;color:#fff;
                        font-weight:700;flex-shrink:0;">
                <?= e(mb_substr($uName, 0, 1, 'UTF-8') ?: '?') ?>
            </div>
            <div style="flex:1;">
                <h1 style="font-size:1.2rem;font-weight:700;margin:0 0 4px;color:var(--pub-text);">
                    <?= e($uName) ?>
                </h1>
                <?php if ($uEmail): ?>
                <p style="color:var(--pub-muted);margin:0;font-size:0.88rem;"><?= e($uEmail) ?></p>
                <?php endif; ?>
            </div>
            <a href="/frontend/logout.php"
               style="padding:8px 18px;border-radius:8px;font-size:0.85rem;font-weight:600;
                      background:var(--pub-border);color:var(--pub-text);text-decoration:none;
                      white-space:nowrap;">
                🚪 <?= e(t('nav.logout', ['default' => 'Logout'])) ?>
            </a>
        </div>

        <!-- Account info -->
        <div style="background:var(--pub-surface);border-radius:14px;
                    border:1px solid var(--pub-border);overflow:hidden;margin-bottom:24px;">
            <div style="padding:14px 20px;border-bottom:1px solid var(--pub-border);">
                <h2 style="font-size:0.95rem;font-weight:600;margin:0;color:var(--pub-text);">
                    👤 <?= e(t('profile.account_info', ['default' => 'Account Information'])) ?>
                </h2>
            </div>
            <div style="padding:18px 20px;">
                <?php foreach ([
                    [t('profile.full_name', ['default' => 'Name']),     $uName],
                    [t('profile.email',     ['default' => 'Email']),     $uEmail],
                    [t('profile.language',  ['default' => 'Language']),  strtoupper($uLang)],
                ] as [$label, $value]): if (!$value) continue; ?>
                <div style="display:flex;justify-content:space-between;padding:9px 0;
                            border-bottom:1px solid var(--pub-border);font-size:0.88rem;">
                    <span style="color:var(--pub-muted);"><?= e($label) ?></span>
                    <span style="color:var(--pub-text);font-weight:500;"><?= e($value) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Addresses -->
        <div style="background:var(--pub-surface);border-radius:14px;
                    border:1px solid var(--pub-border);overflow:hidden;margin-bottom:24px;">
            <div style="padding:14px 20px;border-bottom:1px solid var(--pub-border);
                        display:flex;justify-content:space-between;align-items:center;">
                <h2 style="font-size:0.95rem;font-weight:600;margin:0;color:var(--pub-text);">
                    📍 <?= e(t('profile.addresses', ['default' => 'My Addresses'])) ?>
                </h2>
                <button onclick="document.getElementById('pubAddAddrForm').style.display='block';this.style.display='none';"
                        class="pub-btn pub-btn--primary"
                        style="font-size:0.8rem;padding:6px 14px;">
                    + <?= e(t('profile.add_address', ['default' => 'Add Address'])) ?>
                </button>
            </div>

            <!-- Add address form (hidden by default) -->
            <div id="pubAddAddrForm" style="display:none;padding:20px;border-bottom:1px solid var(--pub-border);">
                <form id="addrForm" onsubmit="pubSubmitAddress(event)">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div style="grid-column:1/-1;">
                            <label style="font-size:0.82rem;color:var(--pub-muted);display:block;margin-bottom:4px;">
                                <?= e(t('checkout.address', ['default' => 'Address'])) ?> *
                            </label>
                            <input name="address_line1" required placeholder="<?= e(t('checkout.address', ['default' => 'Street address'])) ?>"
                                   class="pub-input" style="width:100%;box-sizing:border-box;">
                        </div>
                        <div style="grid-column:1/-1;">
                            <label style="font-size:0.82rem;color:var(--pub-muted);display:block;margin-bottom:4px;">
                                <?= e(t('checkout.address2', ['default' => 'Address line 2'])) ?>
                            </label>
                            <input name="address_line2" placeholder="<?= e(t('checkout.address2', ['default' => 'Building, apartment, floor…'])) ?>"
                                   class="pub-input" style="width:100%;box-sizing:border-box;">
                        </div>
                        <?php if (!empty($countries)): ?>
                        <div>
                            <label style="font-size:0.82rem;color:var(--pub-muted);display:block;margin-bottom:4px;">
                                <?= e(t('checkout.country', ['default' => 'Country'])) ?>
                            </label>
                            <select name="country_id" class="pub-input" style="width:100%;box-sizing:border-box;">
                                <option value="">—</option>
                                <?php foreach ($countries as $co): ?>
                                <option value="<?= (int)$co['id'] ?>"><?= e($co['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div>
                            <label style="font-size:0.82rem;color:var(--pub-muted);display:block;margin-bottom:4px;">
                                <?= e(t('checkout.postal_code', ['default' => 'Postal code'])) ?>
                            </label>
                            <input name="postal_code" class="pub-input" style="width:100%;box-sizing:border-box;">
                        </div>
                        <div style="grid-column:1/-1;display:flex;align-items:center;gap:8px;">
                            <input type="checkbox" name="is_primary" id="isPrimary" value="1">
                            <label for="isPrimary" style="font-size:0.88rem;color:var(--pub-text);cursor:pointer;">
                                <?= e(t('profile.set_primary', ['default' => 'Set as primary address'])) ?>
                            </label>
                        </div>
                    </div>
                    <div style="margin-top:14px;display:flex;gap:10px;">
                        <button type="submit" class="pub-btn pub-btn--primary" style="font-size:0.85rem;">
                            <?= e(t('profile.save_address', ['default' => 'Save Address'])) ?>
                        </button>
                        <button type="button" onclick="document.getElementById('pubAddAddrForm').style.display='none';"
                                class="pub-btn pub-btn--ghost" style="font-size:0.85rem;">
                            <?= e(t('common.cancel', ['default' => 'Cancel'])) ?>
                        </button>
                    </div>
                    <p id="addrMsg" style="margin-top:10px;font-size:0.85rem;display:none;"></p>
                </form>
            </div>

            <!-- Address list -->
            <div id="pubAddrList" style="padding:16px 20px;">
                <?php if (empty($addresses)): ?>
                <p style="color:var(--pub-muted);font-size:0.88rem;margin:0;text-align:center;padding:16px 0;">
                    <?= e(t('profile.no_addresses', ['default' => 'No addresses yet'])) ?>
                </p>
                <?php else: ?>
                <?php foreach ($addresses as $addr): ?>
                <div style="display:flex;justify-content:space-between;align-items:flex-start;
                            padding:14px 0;border-bottom:1px solid var(--pub-border);" id="addr-<?= (int)$addr['id'] ?>">
                    <div style="font-size:0.88rem;color:var(--pub-text);line-height:1.6;">
                        <?php if (!empty($addr['is_primary'])): ?>
                        <span style="padding:2px 8px;border-radius:20px;font-size:0.72rem;font-weight:600;
                                     background:var(--pub-primary);color:#fff;margin-<?= $dir==='rtl'?'left':'right' ?>:6px;">
                            <?= e(t('profile.primary', ['default' => 'Primary'])) ?>
                        </span>
                        <?php endif; ?>
                        <strong><?= e($addr['address_line1']) ?></strong>
                        <?php if (!empty($addr['address_line2'])): ?><br><?= e($addr['address_line2']) ?><?php endif; ?>
                        <?php if (!empty($addr['city_name'])): ?><br><?= e($addr['city_name']) ?><?php endif; ?>
                        <?php if (!empty($addr['country_name'])): ?>, <?= e($addr['country_name']) ?><?php endif; ?>
                        <?php if (!empty($addr['postal_code'])): ?>&nbsp;<?= e($addr['postal_code']) ?><?php endif; ?>
                    </div>
                    <button onclick="pubDeleteAddress(<?= (int)$addr['id'] ?>)"
                            style="padding:6px 12px;border-radius:6px;border:1px solid var(--pub-border);
                                   background:transparent;color:var(--pub-muted);cursor:pointer;font-size:0.8rem;
                                   white-space:nowrap;flex-shrink:0;margin-<?= $dir==='rtl'?'right':'left' ?>:12px;">
                        🗑 <?= e(t('common.delete', ['default' => 'Delete'])) ?>
                    </button>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick links -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <?php foreach ([
                ['🏠', t('nav.home'),     '/frontend/public/index.php'],
                ['🛍️', t('nav.products'), '/frontend/public/products.php'],
                ['🛒', t('nav.cart'),     '/frontend/public/cart.php'],
                ['💼', t('nav.jobs'),     '/frontend/public/jobs.php'],
            ] as [$icon, $label, $href]): ?>
            <a href="<?= e($href) ?>"
               style="display:flex;align-items:center;gap:10px;padding:14px 18px;
                      background:var(--pub-surface);border-radius:12px;
                      border:1px solid var(--pub-border);text-decoration:none;color:var(--pub-text);font-size:0.88rem;">
                <?= $icon ?> <?= e($label) ?>
            </a>
            <?php endforeach; ?>
        </div>

    </div>
</main>

<script>
// Sync PHP session user to localStorage
try {
    var _su = window.pubSessionUser;
    if (_su && _su.id) localStorage.setItem('pubUser', JSON.stringify(_su));
} catch(e){}

function pubSubmitAddress(ev) {
    ev.preventDefault();
    var form = ev.target;
    var msg  = document.getElementById('addrMsg');
    var data = new FormData(form);

    fetch('/api/public/addresses', { method: 'POST', body: data, credentials: 'include' })
        .then(function(r){ return r.json(); })
        .then(function(j){
            if (j.success || j.ok) {
                msg.style.display = 'none';
                location.reload(); // reload to show new address
            } else {
                msg.textContent = j.message || 'Error saving address';
                msg.style.display = 'block';
                msg.style.color   = 'var(--pub-error,#ef4444)';
            }
        })
        .catch(function(){ msg.textContent = 'Network error'; msg.style.display='block'; });
}

function pubDeleteAddress(id) {
    if (!confirm('<?= e(t('profile.confirm_delete', ['default' => 'Delete this address?'])) ?>')) return;
    var fd = new FormData();
    fd.append('_method', 'DELETE');
    fetch('/api/public/addresses/' + id, { method: 'POST', body: fd, credentials: 'include' })
        .then(function(r){ return r.json(); })
        .then(function(j){
            if (j.success || j.ok) {
                var el = document.getElementById('addr-' + id);
                if (el) el.remove();
            }
        });
}
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>


$GLOBALS['PUB_APP_NAME']   = 'QOOQZ';
$GLOBALS['PUB_BASE_PATH']  = '/frontend/public';
$GLOBALS['PUB_PAGE_TITLE'] = e(t('nav.account')) . ' — QOOQZ';

include __DIR__ . '/partials/header.php';

$uName  = $user['name']     ?? $user['username'] ?? '';
$uEmail = $user['email']    ?? '';
$uLang  = $user['preferred_language'] ?? $ctx['lang'] ?? 'ar';
$uRoles = $user['roles']    ?? [];
$dir    = $ctx['dir'] ?? 'rtl';
?>

<main class="pub-container" style="padding:40px 0 60px;">

    <!-- Breadcrumb -->
    <nav style="font-size:0.84rem;color:var(--pub-muted);margin-bottom:24px;" aria-label="breadcrumb">
        <a href="/frontend/public/index.php"><?= e(t('nav.home')) ?></a>
        <span style="margin:0 6px;">›</span>
        <span><?= e(t('nav.account')) ?></span>
    </nav>

    <div style="max-width:680px;margin:0 auto;">

        <!-- Avatar + Name -->
        <div style="display:flex;align-items:center;gap:20px;margin-bottom:32px;
                    padding:28px;background:var(--pub-surface);border-radius:16px;
                    border:1px solid var(--pub-border);">
            <div style="width:72px;height:72px;border-radius:50%;
                        background:var(--pub-primary);display:flex;align-items:center;
                        justify-content:center;font-size:1.8rem;color:#fff;
                        font-weight:700;flex-shrink:0;">
                <?= e(mb_substr($uName, 0, 1, 'UTF-8')) ?>
            </div>
            <div>
                <h1 style="font-size:1.3rem;font-weight:700;margin:0 0 4px;color:var(--pub-text);">
                    <?= e($uName) ?>
                </h1>
                <?php if ($uEmail): ?>
                <p style="color:var(--pub-muted);margin:0;font-size:0.9rem;">
                    <?= e($uEmail) ?>
                </p>
                <?php endif; ?>
                <?php if (!empty($uRoles)): ?>
                <div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:6px;">
                    <?php foreach ($uRoles as $role): ?>
                    <span style="padding:2px 10px;border-radius:20px;font-size:0.75rem;font-weight:600;
                                 background:var(--pub-primary);color:#fff;">
                        <?= e($role) ?>
                    </span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Account info -->
        <div style="background:var(--pub-surface);border-radius:16px;
                    border:1px solid var(--pub-border);overflow:hidden;margin-bottom:24px;">
            <div style="padding:16px 24px;border-bottom:1px solid var(--pub-border);">
                <h2 style="font-size:1rem;font-weight:600;margin:0;color:var(--pub-text);">
                    👤 <?= e(t('profile.account_info', ['default' => 'Account Information'])) ?>
                </h2>
            </div>
            <div style="padding:20px 24px;">
                <?php
                $rows = [
                    [t('profile.full_name', ['default' => 'Full Name']), $uName],
                    [t('profile.email',     ['default' => 'Email']),     $uEmail],
                    [t('profile.language',  ['default' => 'Language']),  strtoupper($uLang)],
                ];
                ?>
                <?php foreach ($rows as [$label, $value]): if (!$value) continue; ?>
                <div style="display:flex;justify-content:space-between;padding:10px 0;
                            border-bottom:1px solid var(--pub-border);font-size:0.9rem;">
                    <span style="color:var(--pub-muted);"><?= e($label) ?></span>
                    <span style="color:var(--pub-text);font-weight:500;"><?= e($value) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Quick links -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:24px;">
            <a href="/frontend/public/index.php"
               style="display:flex;align-items:center;gap:10px;padding:16px 20px;
                      background:var(--pub-surface);border-radius:12px;
                      border:1px solid var(--pub-border);text-decoration:none;color:var(--pub-text);">
                🏠 <?= e(t('nav.home')) ?>
            </a>
            <a href="/frontend/public/products.php"
               style="display:flex;align-items:center;gap:10px;padding:16px 20px;
                      background:var(--pub-surface);border-radius:12px;
                      border:1px solid var(--pub-border);text-decoration:none;color:var(--pub-text);">
                🛍️ <?= e(t('nav.products')) ?>
            </a>
            <a href="/frontend/public/cart.php"
               style="display:flex;align-items:center;gap:10px;padding:16px 20px;
                      background:var(--pub-surface);border-radius:12px;
                      border:1px solid var(--pub-border);text-decoration:none;color:var(--pub-text);">
                🛒 <?= e(t('nav.cart')) ?>
            </a>
            <a href="/frontend/public/jobs.php"
               style="display:flex;align-items:center;gap:10px;padding:16px 20px;
                      background:var(--pub-surface);border-radius:12px;
                      border:1px solid var(--pub-border);text-decoration:none;color:var(--pub-text);">
                💼 <?= e(t('nav.jobs')) ?>
            </a>
        </div>

        <!-- Logout -->
        <div style="text-align:center;">
            <a href="/frontend/logout.php"
               class="pub-btn pub-btn--ghost"
               style="display:inline-flex;align-items:center;gap:8px;padding:12px 32px;">
                🚪 <?= e(t('nav.logout')) ?>
            </a>
        </div>

    </div>
</main>

<script>
// Clear localStorage on profile page to keep it in sync with server session
try {
    var sessUser = window.pubSessionUser;
    if (sessUser && sessUser.id) {
        localStorage.setItem('pubUser', JSON.stringify(sessUser));
    }
} catch (e) {}
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
