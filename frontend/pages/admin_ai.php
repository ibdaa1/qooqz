<?php
/**
 * frontend/pages/admin_ai.php
 * صفحة إدارة الذكاء الاصطناعي - تستخدم api/bootstrap_admin_ui.php
 * الألوان والسيمثات من قاعدة البيانات
 */
declare(strict_types=1);

// بدء الجلسة بشكل مستقل قبل أي bootstrap
if (session_status() === PHP_SESSION_NONE) {
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_start([
        'cookie_httponly' => true,
        'cookie_secure'   => $isSecure,
        'cookie_samesite' => 'Lax',
    ]);
}

// تحميل Bootstrap الأدمن (اختياري — لا نوقف الصفحة إذا فشل)
$adminBootstrap = dirname(__DIR__, 2) . '/api/bootstrap_admin_ui.php';
if (file_exists($adminBootstrap)) {
    try {
        require_once $adminBootstrap;
    } catch (Throwable $_e) {
        // الصفحة تعمل بقيم افتراضية إذا فشل bootstrap
    }
}

// جلب بيانات الأدمن UI
/** @var array $ADMIN_UI */
$ADMIN_UI  = $GLOBALS['ADMIN_UI'] ?? [];

// تحديد اللغة: URL → ADMIN_UI → Session → افتراضي
$_allowedLangs = ['ar', 'en', 'fr', 'de', 'tr', 'fa', 'ur'];
$_rawLang = $_GET['lang'] ?? ($ADMIN_UI['lang'] ?? ($_SESSION['lang'] ?? 'ar'));
$lang = in_array($_rawLang, $_allowedLangs, true) ? $_rawLang : 'ar';
$_SESSION['lang'] = $lang;

$rtlLangs  = ['ar', 'fa', 'ur', 'he', 'ps', 'sd', 'ku'];
$direction = $ADMIN_UI['direction'] ?? (in_array(substr($lang, 0, 2), $rtlLangs, true) ? 'rtl' : 'ltr');
$theme     = $ADMIN_UI['theme'] ?? [];
$user      = $ADMIN_UI['user'] ?? [];
$csrfToken = $ADMIN_UI['csrf_token'] ?? (bin2hex(random_bytes(16)));

// ملف اللغة
$langFile = dirname(__DIR__, 2) . '/languages/frontend/main/' . $lang . '.json';
if (!file_exists($langFile)) {
    $langFile = dirname(__DIR__, 2) . '/languages/frontend/main/ar.json';
}
$t = file_exists($langFile) ? (json_decode(file_get_contents($langFile), true) ?? []) : [];

function tAdmin(array $translations, string $key, string $fallback = ''): string {
    return htmlspecialchars($translations[$key] ?? $fallback, ENT_QUOTES, 'UTF-8');
}

// ألوان الثيم من قاعدة البيانات
$colors        = $theme['color_settings'] ?? [];
$primaryColor  = $colors['primary_color']    ?? '#4361ee';
$secondaryColor= $colors['secondary_color']  ?? '#3a0ca3';
$bgColor       = $colors['background_color'] ?? '#f5f7fb';
$textColor     = $colors['text_color']       ?? '#1a1a2e';

// رابط AI API
$aiApiBase = defined('AI_API_BASE_URL') ? AI_API_BASE_URL : '/ai-engine/api/v1';

// جلب إحصاءات من AI API
$stats = ['threads' => 0, 'files' => 0, 'chunks' => 0, 'kbs' => 0];

// جلب قواعد المعرفة
$knowledgeBases = [];
$files          = [];

if (defined('AI_API_BASE_URL') || true) {
    // محاولة جلب البيانات من الـ API داخلياً
    $baseApiUrl = rtrim($aiApiBase, '/');
    foreach ([
        'kbs'   => $baseApiUrl . '/knowledge',
        'files' => $baseApiUrl . '/files?limit=20',
    ] as $key => $url) {
        $ctx = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw) {
            $decoded = json_decode($raw, true);
            if ($key === 'kbs' && isset($decoded['knowledge_bases'])) {
                $knowledgeBases = $decoded['knowledge_bases'];
                $stats['kbs']   = count($knowledgeBases);
            }
            if ($key === 'files' && isset($decoded['files'])) {
                $files        = $decoded['files'];
                $stats['files'] = count($files);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>" dir="<?= $direction ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= tAdmin($t, 'ai_admin_title', 'AI Management') ?> — QOOQZ Admin</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <style>
        /* ---- CSS Variables from DB theme ---- */
        :root {
            --primary:    <?= htmlspecialchars($primaryColor, ENT_QUOTES, 'UTF-8') ?>;
            --secondary:  <?= htmlspecialchars($secondaryColor, ENT_QUOTES, 'UTF-8') ?>;
            --bg:         <?= htmlspecialchars($bgColor, ENT_QUOTES, 'UTF-8') ?>;
            --text:       <?= htmlspecialchars($textColor, ENT_QUOTES, 'UTF-8') ?>;
            <?php if (!empty($theme['css_variables'])): ?>
            <?= $theme['css_variables'] ?>
            <?php endif; ?>
        }
        *,*::before,*::after{box-sizing:border-box}
        body{margin:0;font-family:system-ui,sans-serif;background:var(--bg);color:var(--text)}
        [dir=rtl]{text-align:right}
        .admin-wrap{max-width:1100px;margin:0 auto;padding:24px 16px}
        .page-header{background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff;border-radius:12px;padding:24px 28px;margin-bottom:24px;display:flex;align-items:center;gap:14px}
        .page-header h1{margin:0;font-size:1.4rem;font-weight:700}
        .page-header .sub{opacity:.8;font-size:.85rem;margin-top:4px}
        .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:16px;margin-bottom:28px}
        .stat-card{background:#fff;border-radius:10px;padding:18px 20px;box-shadow:0 1px 8px rgba(0,0,0,.06);border-top:3px solid var(--primary)}
        .stat-card .num{font-size:2rem;font-weight:700;color:var(--primary);line-height:1}
        .stat-card .label{font-size:.8rem;color:#64748b;margin-top:4px}
        .section{background:#fff;border-radius:10px;box-shadow:0 1px 8px rgba(0,0,0,.06);margin-bottom:24px;overflow:hidden}
        .section-header{padding:16px 20px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between}
        .section-header h2{margin:0;font-size:1rem;font-weight:600}
        .btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;border:none;cursor:pointer;font-size:.85rem;font-weight:500;transition:opacity .2s}
        .btn:hover{opacity:.85}
        .btn-primary{background:var(--primary);color:#fff}
        .btn-danger{background:#ef4444;color:#fff}
        .btn-sm{padding:5px 12px;font-size:.78rem}
        .table-wrap{overflow-x:auto}
        table{width:100%;border-collapse:collapse;font-size:.87rem}
        th{background:#f8fafc;padding:10px 14px;text-align:inherit;font-weight:600;border-bottom:2px solid #e2e8f0;white-space:nowrap}
        td{padding:10px 14px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
        tr:last-child td{border-bottom:none}
        .badge{display:inline-block;padding:2px 8px;border-radius:12px;font-size:.72rem;font-weight:600}
        .badge-green{background:#dcfce7;color:#16a34a}
        .badge-blue{background:#dbeafe;color:#1d4ed8}
        .empty{padding:24px;text-align:center;color:#94a3b8;font-size:.9rem}
        .upload-zone{border:2px dashed #d1d5db;border-radius:10px;padding:32px;text-align:center;color:#64748b;cursor:pointer;transition:border-color .2s;margin:16px 20px 20px}
        .upload-zone:hover,.upload-zone.drag{border-color:var(--primary);color:var(--primary)}
        .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:100;align-items:center;justify-content:center}
        .modal-overlay.open{display:flex}
        .modal{background:#fff;border-radius:12px;width:min(500px,95vw);padding:28px;box-shadow:0 8px 40px rgba(0,0,0,.15)}
        .modal h3{margin:0 0 18px;font-size:1.1rem}
        .form-group{margin-bottom:14px}
        .form-group label{display:block;font-size:.85rem;font-weight:500;margin-bottom:5px}
        .form-control{width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:.9rem;font-family:inherit;outline:none;transition:border-color .2s}
        .form-control:focus{border-color:var(--primary)}
        .modal-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:20px}
        .lang-switcher{display:flex;gap:8px;margin-bottom:16px;justify-content:flex-end}
        [dir=rtl] .lang-switcher{justify-content:flex-start}
        .lang-btn{padding:4px 12px;border-radius:20px;border:1px solid #d1d5db;background:#fff;cursor:pointer;font-size:.8rem;text-decoration:none;color:inherit}
        .lang-btn.active{background:var(--primary);color:#fff;border-color:var(--primary)}
        .alert{padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:.87rem}
        .alert-info{background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe}
    </style>
</head>
<body>

<div class="admin-wrap">

    <!-- Language Switcher -->
    <div class="lang-switcher">
        <a href="?lang=ar" class="lang-btn <?= $lang === 'ar' ? 'active' : '' ?>">العربية</a>
        <a href="?lang=en" class="lang-btn <?= $lang === 'en' ? 'active' : '' ?>">English</a>
    </div>

    <!-- Page Header -->
    <div class="page-header">
        <span style="font-size:2rem">🧠</span>
        <div>
            <h1><?= tAdmin($t, 'ai_admin_title', 'AI Management') ?></h1>
            <div class="sub">QOOQZ RAG System</div>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="num" id="stat-kbs"><?= (int)$stats['kbs'] ?></div>
            <div class="label"><?= tAdmin($t, 'ai_stats_kbs', 'Knowledge Bases') ?></div>
        </div>
        <div class="stat-card">
            <div class="num" id="stat-files"><?= (int)$stats['files'] ?></div>
            <div class="label"><?= tAdmin($t, 'ai_stats_files', 'Files') ?></div>
        </div>
        <div class="stat-card">
            <div class="num" id="stat-threads">—</div>
            <div class="label"><?= tAdmin($t, 'ai_stats_threads', 'Conversations') ?></div>
        </div>
        <div class="stat-card">
            <div class="num" id="stat-chunks">—</div>
            <div class="label"><?= tAdmin($t, 'ai_stats_chunks', 'Text Chunks') ?></div>
        </div>
    </div>

    <!-- Knowledge Bases -->
    <div class="section">
        <div class="section-header">
            <h2>📚 <?= tAdmin($t, 'ai_knowledge_bases', 'Knowledge Bases') ?></h2>
            <button class="btn btn-primary btn-sm" onclick="openModal('kb-modal')">
                + <?= tAdmin($t, 'ai_create_kb', 'Create Knowledge Base') ?>
            </button>
        </div>
        <div class="table-wrap">
            <?php if (empty($knowledgeBases)): ?>
                <div class="empty">
                    <div style="font-size:2rem;margin-bottom:8px">📭</div>
                    <?= tAdmin($t, 'ai_no_data', 'No data available') ?>
                </div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?= tAdmin($t, 'ai_name', 'Name') ?></th>
                        <th><?= tAdmin($t, 'ai_description', 'Description') ?></th>
                        <th><?= tAdmin($t, 'ai_created_at', 'Created At') ?></th>
                        <th><?= tAdmin($t, 'ai_actions', 'Actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($knowledgeBases as $i => $kb): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><strong><?= htmlspecialchars($kb['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong></td>
                        <td><?= htmlspecialchars($kb['description'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(substr($kb['created_at'] ?? '', 0, 10), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <button class="btn btn-danger btn-sm"
                                    onclick="deleteKb(<?= json_encode($kb['id'] ?? '') ?>)">
                                <?= tAdmin($t, 'ai_delete', 'Delete') ?>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Files -->
    <div class="section">
        <div class="section-header">
            <h2>📁 <?= tAdmin($t, 'ai_files', 'Files') ?></h2>
            <button class="btn btn-primary btn-sm" onclick="openModal('file-modal')">
                ↑ <?= tAdmin($t, 'ai_upload_file', 'Upload File') ?>
            </button>
        </div>

        <!-- Upload Zone -->
        <div class="upload-zone" id="drop-zone"
             ondragover="event.preventDefault();this.classList.add('drag')"
             ondragleave="this.classList.remove('drag')"
             ondrop="handleDrop(event)"
             onclick="document.getElementById('admin-file-input').click()">
            <div style="font-size:2rem;margin-bottom:8px">☁️</div>
            <div><?= tAdmin($t, 'ai_attach_file', 'Attach file or image') ?></div>
            <div style="font-size:.78rem;margin-top:4px;opacity:.7">PDF, TXT, DOCX, CSV, Images</div>
            <input type="file" id="admin-file-input" style="display:none"
                   accept="image/*,.pdf,.txt,.docx,.csv,.md"
                   onchange="uploadFile(this.files[0])">
        </div>

        <div id="upload-status" style="padding:0 20px 10px;font-size:.85rem"></div>

        <div class="table-wrap">
            <?php if (empty($files)): ?>
                <div class="empty">
                    <div style="font-size:2rem;margin-bottom:8px">📭</div>
                    <?= tAdmin($t, 'ai_no_data', 'No data available') ?>
                </div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?= tAdmin($t, 'ai_name', 'Name') ?></th>
                        <th><?= tAdmin($t, 'ai_mime_type', 'File Type') ?></th>
                        <th><?= tAdmin($t, 'ai_file_size', 'File Size') ?></th>
                        <th><?= tAdmin($t, 'ai_created_at', 'Created At') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($files as $i => $file): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($file['filename'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <span class="badge badge-blue">
                                <?= htmlspecialchars($file['mime_type'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                        <td>
                            <?php
                            $size = (int)($file['file_size'] ?? 0);
                            echo $size > 1048576
                                ? round($size / 1048576, 1) . ' MB'
                                : round($size / 1024, 1) . ' KB';
                            ?>
                        </td>
                        <td><?= htmlspecialchars(substr($file['created_at'] ?? '', 0, 10), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Create KB Modal -->
<div class="modal-overlay" id="kb-modal">
    <div class="modal">
        <h3>📚 <?= tAdmin($t, 'ai_create_kb', 'Create Knowledge Base') ?></h3>
        <div class="form-group">
            <label><?= tAdmin($t, 'ai_name', 'Name') ?></label>
            <input type="text" class="form-control" id="kb-name"
                   placeholder="<?= tAdmin($t, 'ai_name', 'Name') ?>">
        </div>
        <div class="form-group">
            <label><?= tAdmin($t, 'ai_description', 'Description') ?></label>
            <textarea class="form-control" id="kb-desc" rows="3"
                      placeholder="<?= tAdmin($t, 'ai_description', 'Description') ?>"></textarea>
        </div>
        <div class="modal-actions">
            <button class="btn" onclick="closeModal('kb-modal')" style="background:#f1f5f9">
                ✕
            </button>
            <button class="btn btn-primary" onclick="createKb()">
                <?= tAdmin($t, 'ai_create_kb', 'Create') ?>
            </button>
        </div>
    </div>
</div>

<!-- Upload File Modal -->
<div class="modal-overlay" id="file-modal">
    <div class="modal">
        <h3>↑ <?= tAdmin($t, 'ai_upload_file', 'Upload File') ?></h3>
        <div class="form-group">
            <label><?= tAdmin($t, 'ai_knowledge_bases', 'Knowledge Base') ?></label>
            <select class="form-control" id="modal-kb-select">
                <option value="">— <?= tAdmin($t, 'ai_no_data', 'None') ?> —</option>
                <?php foreach ($knowledgeBases as $kb): ?>
                <option value="<?= htmlspecialchars($kb['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($kb['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <input type="file" class="form-control" id="modal-file-input"
                   accept="image/*,.pdf,.txt,.docx,.csv,.md">
        </div>
        <div class="modal-actions">
            <button class="btn" onclick="closeModal('file-modal')" style="background:#f1f5f9">✕</button>
            <button class="btn btn-primary" onclick="modalUpload()">
                <?= tAdmin($t, 'ai_upload_file', 'Upload') ?>
            </button>
        </div>
    </div>
</div>

<script>
const AI_BASE   = <?= json_encode($aiApiBase) ?>;
const CSRF      = <?= json_encode($csrfToken) ?>;

function openModal(id) {
    document.getElementById(id).classList.add('open');
}
function closeModal(id) {
    document.getElementById(id).classList.remove('open');
}

// Close modal on overlay click
document.querySelectorAll('.modal-overlay').forEach(function(el) {
    el.addEventListener('click', function(e) {
        if (e.target === el) el.classList.remove('open');
    });
});

async function createKb() {
    const name = document.getElementById('kb-name').value.trim();
    const desc = document.getElementById('kb-desc').value.trim();
    if (!name) return;

    const resp = await fetch(AI_BASE + '/knowledge', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({name: name, description: desc})
    });
    if (resp.ok) {
        closeModal('kb-modal');
        location.reload();
    } else {
        alert('Error: ' + resp.status);
    }
}

async function deleteKb(id) {
    if (!confirm('Delete?')) return;
    const resp = await fetch(AI_BASE + '/knowledge/' + id, {method: 'DELETE'});
    if (resp.ok) location.reload();
    else alert('Error: ' + resp.status);
}

function handleDrop(e) {
    e.preventDefault();
    document.getElementById('drop-zone').classList.remove('drag');
    const file = e.dataTransfer.files[0];
    if (file) uploadFile(file);
}

async function uploadFile(file, kbId) {
    if (!file) return;
    const status = document.getElementById('upload-status');
    status.innerHTML = '⏳ <?= tAdmin($t, 'ai_upload_file', 'Uploading...') ?>';

    const fd = new FormData();
    fd.append('file', file);
    if (kbId) fd.append('knowledge_base_id', kbId);

    const resp = await fetch(AI_BASE + '/files/upload', {method: 'POST', body: fd});
    if (resp.ok) {
        const data = await resp.json();
        status.innerHTML = '✅ <?= tAdmin($t, 'ai_file_attached', 'Uploaded') ?>: ' + data.filename;
        setTimeout(function() { location.reload(); }, 1500);
    } else {
        status.innerHTML = '❌ <?= tAdmin($t, 'ai_upload_error', 'Upload failed') ?> (HTTP ' + resp.status + ')';
    }
}

async function modalUpload() {
    const fileInput = document.getElementById('modal-file-input');
    const kbId      = document.getElementById('modal-kb-select').value;
    if (!fileInput.files[0]) return;
    closeModal('file-modal');
    await uploadFile(fileInput.files[0], kbId);
}

// Load dynamic stats (threads, chunks)
(async function loadStats() {
    try {
        const r = await fetch(AI_BASE + '/threads?limit=1');
        if (r.ok) {
            const d = await r.json();
            if (d.total !== undefined) {
                document.getElementById('stat-threads').textContent = d.total;
            }
        }
    } catch(e) {}
})();
</script>
</body>
</html>
