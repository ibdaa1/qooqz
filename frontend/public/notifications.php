<?php
/**
 * frontend/public/notifications.php
 * QOOQZ — Notifications Page (full list)
 *
 * Displays all notifications for the current tenant.
 * No login required (notifications are tenant-level broadcasts).
 * Linked from the notification bell "View all" footer link.
 */
require_once dirname(__DIR__) . '/includes/public_context.php';

$GLOBALS['PUB_PAGE_TITLE'] = e(t('notifications.page_title', ['default' => 'Notifications'])) . ' — QOOQZ';
include dirname(__DIR__) . '/partials/header.php';

// Resolve current page for pagination
$currentPage  = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 20;
$typeCodeFilter = trim($_GET['type_code'] ?? '');
$priorityFilter = in_array($_GET['priority'] ?? '', ['low','normal','high','urgent']) ? $_GET['priority'] : '';

// Load notifications directly via PDO (same connection used by public_context)
$notifItems    = [];
$notifTotal    = 0;
$notifTypes    = [];
$notifError    = false;

$pdo = pub_get_pdo();
if ($pdo) {
    try {
        // Load active notification types for filter dropdown
        $stTypes = $pdo->prepare(
            'SELECT id, code, name FROM notification_types WHERE is_active = 1 ORDER BY id ASC'
        );
        $stTypes->execute();
        $notifTypes = $stTypes->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Build WHERE clause
        $where  = ['n.tenant_id = ?', '(n.expires_at IS NULL OR n.expires_at > NOW())'];
        $params = [$tenantId];

        if ($typeCodeFilter !== '') {
            $where[]  = 'nt.code = ?';
            $params[] = $typeCodeFilter;
        }
        if ($priorityFilter !== '') {
            $where[]  = 'n.priority = ?';
            $params[] = $priorityFilter;
        }

        $whereClause = implode(' AND ', $where);
        $offset      = ($currentPage - 1) * $perPage;

        // Total count
        $cSt = $pdo->prepare(
            "SELECT COUNT(*) FROM notifications n
             LEFT JOIN notification_types nt ON nt.id = n.notification_type_id
             WHERE $whereClause"
        );
        $cSt->execute($params);
        $notifTotal = (int)$cSt->fetchColumn();

        // Items
        $iSt = $pdo->prepare(
            "SELECT n.id, n.title, n.message, n.sent_at, n.priority,
                    nt.code AS type_code, nt.name AS type_name
               FROM notifications n
          LEFT JOIN notification_types nt ON nt.id = n.notification_type_id
              WHERE $whereClause
           ORDER BY n.sent_at DESC
              LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $i => $v) {
            $iSt->bindValue($i + 1, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $iSt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $iSt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $iSt->execute();
        $notifItems = $iSt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    } catch (Throwable $e) {
        $notifError = true;
        error_log('[notifications.php] ' . $e->getMessage());
    }
}

$totalPages = $notifTotal > 0 ? (int)ceil($notifTotal / $perPage) : 0;

// Notification type → emoji icon map (mirrors JS initNotifBell)
function notif_icon(string $code): string {
    $icons = [
        'order' => '📦', 'payment' => '💳', 'shipment' => '🚚', 'return' => '↩️',
        'review' => '⭐', 'promotion' => '🎉', 'system' => '⚙️', 'entities' => '🏢',
        'support' => '🆘', 'wallet' => '💰', 'loyalty' => '🏅',
        'audit_completed' => '✅', 'audit_rejected' => '❌',
    ];
    return $icons[$code] ?? '🔔';
}

// Priority label
function notif_priority_label(string $p): string {
    $labels = ['low' => '🔵', 'normal' => '⚪', 'high' => '🟠', 'urgent' => '🔴'];
    return $labels[$p] ?? '';
}

// Build filter query string (strip page)
function notif_filter_qs(array $extra = []): string {
    $base = [];
    if (!empty($_GET['type_code'])) $base['type_code'] = $_GET['type_code'];
    if (!empty($_GET['priority']))  $base['priority']  = $_GET['priority'];
    if (!empty($_GET['tenant_id'])) $base['tenant_id'] = $_GET['tenant_id'];
    $merged = array_merge($base, $extra);
    return $merged ? '?' . http_build_query($merged) : '';
}
?>

<main class="pub-container" style="padding-top:24px;padding-bottom:48px;">

    <!-- Page header -->
    <div class="pub-page-hero">
        <h1>🔔 <?= e(t('notifications.page_title', ['default' => 'Notifications'])) ?></h1>
        <?php if ($notifTotal > 0): ?>
        <p><?= $notifTotal ?> <?= e(t('notifications.total_count', ['default' => 'notification(s)'])) ?></p>
        <?php endif; ?>
    </div>

    <!-- Filters bar -->
    <form method="get" action="" class="pub-filters-bar" style="margin-bottom:24px;display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
        <?php if (!empty($_GET['tenant_id'])): ?>
            <input type="hidden" name="tenant_id" value="<?= (int)$_GET['tenant_id'] ?>">
        <?php endif; ?>

        <!-- Type filter -->
        <?php if (!empty($notifTypes)): ?>
        <div>
            <label class="pub-label"><?= e(t('notifications.filter_type', ['default' => 'Type'])) ?></label>
            <select name="type_code" class="pub-select pub-filter-select" style="min-width:140px;" onchange="this.form.submit()">
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
            <select name="priority" class="pub-select pub-filter-select" style="min-width:120px;" onchange="this.form.submit()">
                <option value=""><?= e(t('notifications.all_priorities', ['default' => 'All'])) ?></option>
                <option value="urgent" <?= $priorityFilter === 'urgent' ? 'selected' : '' ?>>🔴 <?= e(t('notifications.priority_urgent', ['default' => 'Urgent'])) ?></option>
                <option value="high"   <?= $priorityFilter === 'high'   ? 'selected' : '' ?>>🟠 <?= e(t('notifications.priority_high',   ['default' => 'High'])) ?></option>
                <option value="normal" <?= $priorityFilter === 'normal' ? 'selected' : '' ?>>⚪ <?= e(t('notifications.priority_normal', ['default' => 'Normal'])) ?></option>
                <option value="low"    <?= $priorityFilter === 'low'    ? 'selected' : '' ?>>🔵 <?= e(t('notifications.priority_low',    ['default' => 'Low'])) ?></option>
            </select>
        </div>

        <!-- Reset -->
        <?php if ($typeCodeFilter || $priorityFilter): ?>
        <a href="?<?= !empty($_GET['tenant_id']) ? 'tenant_id='.(int)$_GET['tenant_id'] : '' ?>"
           class="pub-btn pub-btn--ghost pub-btn--sm">✕ <?= e(t('notifications.reset_filters', ['default' => 'Reset'])) ?></a>
        <?php endif; ?>
    </form>

    <?php if ($notifError): ?>
    <div class="pub-alert pub-alert--error" style="margin-bottom:20px;">
        ⚠️ <?= e(t('notifications.load_error', ['default' => 'Could not load notifications. Please try again.'])) ?>
    </div>
    <?php elseif (empty($notifItems)): ?>
    <div class="pub-empty-state" style="text-align:center;padding:60px 20px;">
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
            $nIcon  = notif_icon($nCode);
            $nPLbl  = notif_priority_label($nPrio);
        ?>
        <div class="pub-notif-page-item" data-id="<?= $nId ?>" data-priority="<?= e($nPrio) ?>">
            <span class="pub-notif-page-icon" aria-hidden="true"><?= $nIcon ?></span>
            <div class="pub-notif-page-body">
                <div class="pub-notif-page-row">
                    <p class="pub-notif-page-title"><?= e($nTitle) ?></p>
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
    gap: 0;
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
.pub-notif-page-item:hover { background: var(--pub-bg, #f9fafb); }
.pub-notif-page-item[data-priority="urgent"] {
    border-inline-start: 3px solid #ef4444;
}
.pub-notif-page-item[data-priority="high"] {
    border-inline-start: 3px solid #f97316;
}
.pub-notif-page-icon {
    font-size: 1.4rem;
    flex-shrink: 0;
    margin-top: 2px;
}
.pub-notif-page-body  { flex: 1; min-width: 0; }
.pub-notif-page-row   { display: flex; align-items: flex-start; gap: 8px; }
.pub-notif-page-title {
    font-size: 0.92rem;
    font-weight: 600;
    color: var(--pub-text, #111);
    margin: 0;
    flex: 1;
}
.pub-notif-page-priority { font-size: 0.9rem; flex-shrink: 0; }
.pub-notif-page-msg {
    font-size: 0.83rem;
    color: var(--pub-muted, #6b7280);
    margin: 4px 0 0;
    line-height: 1.5;
}
.pub-notif-page-time {
    font-size: 0.73rem;
    color: var(--pub-muted, #9ca3af);
    margin-top: 5px;
}
</style>

<?php include dirname(__DIR__) . '/partials/footer.php'; ?>
