<?php
/**
 * frontend/public/notifications.php
 * QOOQZ — My Notifications Page
 *
 * Displays notifications addressed to the logged-in user via
 * notification_recipients. Supports read/unread state, mark-all-read,
 * type and priority filters, and pagination.
 *
 * Requires: authenticated session.
 */
require_once dirname(__DIR__) . '/includes/public_context.php';

// Login required — notifications are user-specific
if (!$_isLoggedIn) {
    header('Location: /frontend/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/frontend/public/notifications.php'));
    exit;
}

$ctx = $GLOBALS['PUB_CONTEXT'];
$GLOBALS['PUB_PAGE_TITLE'] = e(t('notifications.page_title', ['default' => 'My Notifications'])) . ' — QOOQZ';
include dirname(__DIR__) . '/partials/header.php';

$userId = (int)($_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? 0);
$pdo    = pub_get_pdo();

// Filters
$currentPage    = max(1, (int)($_GET['page'] ?? 1));
$perPage        = 20;
$typeCodeFilter = trim($_GET['type_code'] ?? '');
$priorityFilter = in_array($_GET['priority'] ?? '', ['low','normal','high','urgent']) ? $_GET['priority'] : '';
$unreadOnly     = !empty($_GET['unread']);

$notifItems = [];
$notifTotal = 0;
$notifTypes = [];
$unreadCount = 0;
$notifError  = false;

if ($pdo && $userId) {
    try {
        // Active notification types for the filter dropdown
        $stTypes = $pdo->prepare(
            'SELECT id, code, name FROM notification_types WHERE is_active = 1 ORDER BY id ASC'
        );
        $stTypes->execute();
        $notifTypes = $stTypes->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Build WHERE clause joining notification_recipients → notifications
        $where  = [
            "nr.recipient_type = 'user'",
            'nr.recipient_id   = ?',
            'n.tenant_id       = ?',
            '(n.expires_at IS NULL OR n.expires_at > NOW())',
        ];
        $params = [$userId, $tenantId];

        if ($typeCodeFilter !== '') {
            $where[]  = 'nt.code = ?';
            $params[] = $typeCodeFilter;
        }
        if ($priorityFilter !== '') {
            $where[]  = 'n.priority = ?';
            $params[] = $priorityFilter;
        }
        if ($unreadOnly) {
            $where[] = 'nr.is_read = 0';
        }

        $whereClause = implode(' AND ', $where);
        $offset      = ($currentPage - 1) * $perPage;

        // Total count (for pagination)
        $cSt = $pdo->prepare(
            "SELECT COUNT(*)
               FROM notification_recipients nr
               JOIN notifications n          ON n.id  = nr.notification_id
          LEFT JOIN notification_types nt    ON nt.id = n.notification_type_id
              WHERE $whereClause"
        );
        $cSt->execute($params);
        $notifTotal = (int)$cSt->fetchColumn();

        // Items
        // Items -- use only positional ? to avoid SQLSTATE HY093 (mixed placeholders in MySQL PDO)
        $itemParams   = $params;
        $itemParams[] = $perPage;
        $itemParams[] = $offset;

        $iSt = $pdo->prepare(
            "SELECT n.id, n.title, n.message, n.sent_at, n.priority,
                    nr.is_read, nr.read_at,
                    nt.code AS type_code, nt.name AS type_name
               FROM notification_recipients nr
               JOIN notifications n          ON n.id  = nr.notification_id
          LEFT JOIN notification_types nt    ON nt.id = n.notification_type_id
              WHERE $whereClause
           ORDER BY n.sent_at DESC
              LIMIT ? OFFSET ?"
        );
        $iSt->execute($itemParams);
        $notifItems = $iSt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Unread count (for the "Mark all read" button badge)
        $ucSt = $pdo->prepare(
            "SELECT COALESCE(
               (SELECT unread_count FROM notification_counters
                 WHERE tenant_id      = ?
                   AND recipient_type = 'user'
                   AND recipient_id   = ?
                 LIMIT 1),
               (SELECT COUNT(*)
                  FROM notification_recipients nr2
                  JOIN notifications n2 ON n2.id = nr2.notification_id
                 WHERE nr2.recipient_type = 'user'
                   AND nr2.recipient_id   = ?
                   AND nr2.is_read        = 0
                   AND n2.tenant_id       = ?
                   AND (n2.expires_at IS NULL OR n2.expires_at > NOW()))
            ) AS unread_count"
        );
        $ucSt->execute([$tenantId, $userId, $userId, $tenantId]);
        $unreadCount = (int)$ucSt->fetchColumn();

    } catch (Throwable $e) {
        $notifError = true;
        error_log('[notifications.php] ' . $e->getMessage());
    }
}

$totalPages = $notifTotal > 0 ? (int)ceil($notifTotal / $perPage) : 0;

// Type → emoji icon
function notif_icon(string $code): string {
    $icons = [
        'order' => '📦', 'payment' => '💳', 'shipment' => '🚚', 'return' => '↩️',
        'review' => '⭐', 'promotion' => '🎉', 'system' => '⚙️', 'entities' => '🏢',
        'support' => '🆘', 'wallet' => '💰', 'loyalty' => '🏅',
        'audit_completed' => '✅', 'audit_rejected' => '❌',
    ];
    return $icons[$code] ?? '🔔';
}

// Priority dot
function notif_priority_label(string $p): string {
    return ['low' => '🔵', 'normal' => '⚪', 'high' => '🟠', 'urgent' => '🔴'][$p] ?? '';
}

// Build filter query string preserving current filters
function notif_filter_qs(array $extra = []): string {
    $base = [];
    if (!empty($_GET['type_code'])) $base['type_code'] = $_GET['type_code'];
    if (!empty($_GET['priority']))  $base['priority']  = $_GET['priority'];
    if (!empty($_GET['unread']))    $base['unread']    = '1';
    if (!empty($_GET['tenant_id'])) $base['tenant_id'] = (int)$_GET['tenant_id'];
    // Remove keys explicitly set to null (used to clear individual filters)
    $merged = array_filter(array_merge($base, $extra), fn($v) => $v !== null && $v !== '');
    return '?' . http_build_query($merged);
}
?>

<main class="pub-container" style="padding-top:24px;padding-bottom:48px;">

    <!-- Page header -->
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:20px;">
        <div>
            <h1 style="margin:0;font-size:1.4rem;">🔔 <?= e(t('notifications.page_title', ['default' => 'My Notifications'])) ?></h1>
            <?php if ($unreadCount > 0): ?>
            <p style="color:var(--pub-muted);font-size:0.85rem;margin:4px 0 0;">
                <?= $unreadCount ?> <?= e(t('notifications.unread_count', ['default' => 'unread'])) ?>
            </p>
            <?php endif; ?>
        </div>
        <?php if ($unreadCount > 0): ?>
        <button type="button" class="pub-btn pub-btn--primary pub-btn--sm" id="pubMarkAllReadBtn">
            ✓ <?= e(t('notifications.mark_all_read', ['default' => 'Mark all as read'])) ?>
        </button>
        <?php endif; ?>
    </div>

    <!-- Filters bar -->
    <form method="get" action="" style="margin-bottom:20px;display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
        <?php if (!empty($_GET['tenant_id'])): ?>
            <input type="hidden" name="tenant_id" value="<?= (int)$_GET['tenant_id'] ?>">
        <?php endif; ?>

        <!-- Unread-only toggle -->
        <div>
            <label class="pub-label">&nbsp;</label>
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:0.88rem;">
                <input type="checkbox" name="unread" value="1" onchange="this.form.submit()" <?= $unreadOnly ? 'checked' : '' ?>>
                <?= e(t('notifications.unread_only', ['default' => 'Unread only'])) ?>
            </label>
        </div>

        <!-- Type filter -->
        <?php if (!empty($notifTypes)): ?>
        <div>
            <label class="pub-label"><?= e(t('notifications.filter_type', ['default' => 'Type'])) ?></label>
            <select name="type_code" class="pub-select" style="min-width:140px;" onchange="this.form.submit()">
                <option value=""><?= e(t('notifications.all_types', ['default' => 'All types'])) ?></option>
                <?php foreach ($notifTypes as $nt): ?>
                <option value="<?= e($nt['code']) ?>" <?= $typeCodeFilter === $nt['code'] ? 'selected' : '' ?>>
                    <?= notif_icon($nt['code']) ?> <?= e($nt['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <!-- Priority filter -->
        <div>
            <label class="pub-label"><?= e(t('notifications.filter_priority', ['default' => 'Priority'])) ?></label>
            <select name="priority" class="pub-select" style="min-width:120px;" onchange="this.form.submit()">
                <option value=""><?= e(t('notifications.all_priorities', ['default' => 'All'])) ?></option>
                <option value="urgent" <?= $priorityFilter === 'urgent' ? 'selected' : '' ?>>🔴 <?= e(t('notifications.priority_urgent', ['default' => 'Urgent'])) ?></option>
                <option value="high"   <?= $priorityFilter === 'high'   ? 'selected' : '' ?>>🟠 <?= e(t('notifications.priority_high',   ['default' => 'High'])) ?></option>
                <option value="normal" <?= $priorityFilter === 'normal' ? 'selected' : '' ?>>⚪ <?= e(t('notifications.priority_normal', ['default' => 'Normal'])) ?></option>
                <option value="low"    <?= $priorityFilter === 'low'    ? 'selected' : '' ?>>🔵 <?= e(t('notifications.priority_low',    ['default' => 'Low'])) ?></option>
            </select>
        </div>

        <?php if ($typeCodeFilter || $priorityFilter || $unreadOnly): ?>
        <a href="<?= notif_filter_qs(['type_code' => null, 'priority' => null, 'unread' => null, 'page' => null]) ?>"
           class="pub-btn pub-btn--ghost pub-btn--sm" style="align-self:flex-end;">
            ✕ <?= e(t('notifications.reset_filters', ['default' => 'Reset'])) ?>
        </a>
        <?php endif; ?>
    </form>

    <?php if ($notifError): ?>
    <div class="pub-alert pub-alert--error" style="margin-bottom:20px;">
        ⚠️ <?= e(t('notifications.load_error', ['default' => 'Could not load notifications. Please try again.'])) ?>
    </div>
    <?php elseif (empty($notifItems)): ?>
    <div style="text-align:center;padding:60px 20px;">
        <div style="font-size:3rem;margin-bottom:16px;">🔔</div>
        <p style="color:var(--pub-muted);font-size:1rem;">
            <?= e(t('notifications.empty', ['default' => 'No notifications yet'])) ?>
        </p>
    </div>
    <?php else: ?>

    <!-- Notification list -->
    <div class="pub-notif-page-list" id="pubNotifPageList">
        <?php foreach ($notifItems as $n):
            $nId    = (int)$n['id'];
            $nTitle = $n['title'] ?? '';
            $nMsg   = $n['message'] ?? '';
            $nTime  = isset($n['sent_at']) ? substr((string)$n['sent_at'], 0, 16) : '';
            $nCode  = $n['type_code'] ?? '';
            $nPrio  = $n['priority'] ?? 'normal';
            $nRead  = (bool)$n['is_read'];
            $nIcon  = notif_icon($nCode);
            $nPLbl  = notif_priority_label($nPrio);
        ?>
        <div class="pub-notif-page-item<?= $nRead ? ' pub-notif-read' : ' pub-notif-unread' ?>"
             data-id="<?= $nId ?>"
             data-priority="<?= e($nPrio) ?>">
            <span class="pub-notif-page-icon" aria-hidden="true"><?= $nIcon ?></span>
            <div class="pub-notif-page-body">
                <div class="pub-notif-page-row">
                    <p class="pub-notif-page-title"><?= e($nTitle) ?></p>
                    <?php if (!$nRead): ?>
                    <span class="pub-notif-badge-unread" title="<?= e(t('notifications.unread', ['default' => 'Unread'])) ?>"></span>
                    <?php endif; ?>
                    <?php if ($nPLbl): ?>
                    <span class="pub-notif-page-priority" title="<?= e($nPrio) ?>"><?= $nPLbl ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($nMsg): ?>
                <p class="pub-notif-page-msg"><?= e($nMsg) ?></p>
                <?php endif; ?>
                <?php if ($nTime): ?>
                <div class="pub-notif-page-time"><?= e($nTime) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav class="pub-pagination" style="margin-top:32px;" aria-label="Pagination">
        <?php if ($currentPage > 1): ?>
        <a class="pub-page-btn" href="<?= notif_filter_qs(['page' => $currentPage - 1]) ?>">‹</a>
        <?php endif; ?>

        <?php for ($p = max(1, $currentPage - 2); $p <= min($totalPages, $currentPage + 2); $p++): ?>
        <a class="pub-page-btn<?= $p === $currentPage ? ' active' : '' ?>"
           href="<?= notif_filter_qs(['page' => $p]) ?>"><?= $p ?></a>
        <?php endfor; ?>

        <?php if ($currentPage < $totalPages): ?>
        <a class="pub-page-btn" href="<?= notif_filter_qs(['page' => $currentPage + 1]) ?>">›</a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>

    <?php endif; ?>
</main>

<style>
.pub-notif-page-list {
    display: flex;
    flex-direction: column;
    border: 1px solid var(--pub-border, #e6e9ee);
    border-radius: var(--pub-radius, 10px);
    overflow: hidden;
}
.pub-notif-page-item {
    display: flex;
    gap: 14px;
    padding: 14px 18px;
    border-bottom: 1px solid var(--pub-border, #e6e9ee);
    background: var(--pub-surface, #fff);
    transition: background 0.15s;
}
.pub-notif-page-item:last-child { border-bottom: none; }
.pub-notif-page-item:hover      { background: var(--pub-bg, #f9fafb); }
.pub-notif-page-item.pub-notif-unread { background: #eef2ff; } /* fallback for older browsers */
@supports (background: color-mix(in srgb, red 5%, white)) {
    .pub-notif-page-item.pub-notif-unread { background: color-mix(in srgb, var(--pub-primary, #2563eb) 5%, var(--pub-surface, #fff)); }
}
.pub-notif-page-item[data-priority="urgent"] { border-inline-start: 3px solid #ef4444; }
.pub-notif-page-item[data-priority="high"]   { border-inline-start: 3px solid #f97316; }
.pub-notif-page-icon     { font-size: 1.4rem; flex-shrink: 0; margin-top: 2px; }
.pub-notif-page-body     { flex: 1; min-width: 0; }
.pub-notif-page-row      { display: flex; align-items: flex-start; gap: 8px; }
.pub-notif-page-title    { font-size: 0.92rem; font-weight: 600; color: var(--pub-text, #111); margin: 0; flex: 1; }
.pub-notif-read .pub-notif-page-title { font-weight: 400; color: var(--pub-muted, #6b7280); }
.pub-notif-badge-unread  { width: 8px; height: 8px; border-radius: 50%; background: var(--pub-primary, #2563eb); flex-shrink: 0; margin-top: 5px; }
.pub-notif-page-priority { font-size: 0.9rem; flex-shrink: 0; }
.pub-notif-page-msg      { font-size: 0.83rem; color: var(--pub-muted, #6b7280); margin: 4px 0 0; line-height: 1.5; }
.pub-notif-page-time     { font-size: 0.73rem; color: var(--pub-muted, #9ca3af); margin-top: 5px; }
</style>

<script>
(function () {
    // Helper: visually mark a single notification item as read
    function markItemRead(el) {
        el.classList.remove('pub-notif-unread');
        el.classList.add('pub-notif-read');
        var dot = el.querySelector('.pub-notif-badge-unread');
        if (dot) dot.remove();
    }

    // Mark-all-read button
    var markAllBtn = document.getElementById('pubMarkAllReadBtn');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', function () {
            markAllBtn.disabled = true;
            fetch('/api/public/notifications/mark-all-read', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' }
            })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success) {
                    document.querySelectorAll('#pubNotifPageList .pub-notif-unread').forEach(markItemRead);
                    markAllBtn.style.display = 'none';
                }
            })
            .catch(function () {})
            .finally(function () { markAllBtn.disabled = false; });
        });
    }

    // Individual mark-read on click for unread items
    document.querySelectorAll('#pubNotifPageList .pub-notif-page-item.pub-notif-unread').forEach(function (el) {
        el.style.cursor = 'pointer';
        el.addEventListener('click', function () {
            var id = parseInt(el.dataset.id, 10);
            if (!id || el.classList.contains('pub-notif-read')) return;
            // Optimistic UI update
            markItemRead(el);
            fetch('/api/public/notifications/mark-read', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ids: [id] })
            }).catch(function () {});
        });
    });
})();
</script>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>
