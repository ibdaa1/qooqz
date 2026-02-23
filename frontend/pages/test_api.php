<?php
/**
 * frontend/pages/test_api.php
 * صفحة اختبار نظام الذكاء الاصطناعي - تدعم جميع اللغات
 */
declare(strict_types=1);

// تحميل Bootstrap الفرونت اند (يعالج اللغة، الجلسة، API Client)
$bootstrapPath = dirname(__DIR__) . '/bootstrap.php';
if (file_exists($bootstrapPath)) {
    require_once $bootstrapPath;
}

// جلب اللغة والاتجاه من Bootstrap أو الافتراضي
$lang      = $GLOBALS['FRONT_CONTAINER']['lang'] ?? ($_SESSION['lang'] ?? 'ar');
$rtlLangs  = ['ar', 'fa', 'ur', 'he', 'ps', 'sd', 'ku'];
$direction = in_array(substr($lang, 0, 2), $rtlLangs, true) ? 'rtl' : 'ltr';

// جلب ترجمات الذكاء الاصطناعي من ملفات اللغة
$langFile = dirname(__DIR__, 2) . '/languages/frontend/main/' . $lang . '.json';
if (!file_exists($langFile)) {
    $langFile = dirname(__DIR__, 2) . '/languages/frontend/main/ar.json';
}
$t = file_exists($langFile) ? (json_decode(file_get_contents($langFile), true) ?? []) : [];

// دالة ترجمة مبسّطة
function t(array $translations, string $key, string $fallback = ''): string {
    return htmlspecialchars($translations[$key] ?? $fallback, ENT_QUOTES, 'UTF-8');
}

// جلب رابط AI API من الإعدادات
$aiApiBase = defined('AI_API_BASE_URL') ? AI_API_BASE_URL : '/ai-engine/api/v1';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>" dir="<?= $direction ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= t($t, 'ai_test_title', 'AI Test') ?> — QOOQZ</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <style>
        *,*::before,*::after{box-sizing:border-box}
        body{margin:0;font-family:system-ui,sans-serif;background:#f5f7fb;color:#1a1a2e}
        [dir=rtl]{text-align:right}
        .ai-wrap{max-width:860px;margin:40px auto;padding:0 16px}
        .ai-card{background:#fff;border-radius:12px;box-shadow:0 2px 16px rgba(0,0,0,.08);overflow:hidden}
        .ai-header{background:linear-gradient(135deg,#4361ee,#3a0ca3);color:#fff;padding:20px 24px;display:flex;align-items:center;gap:12px}
        .ai-header h1{margin:0;font-size:1.3rem;font-weight:700}
        .ai-header .badge{background:rgba(255,255,255,.2);border-radius:20px;padding:3px 10px;font-size:.75rem}
        .ai-messages{height:420px;overflow-y:auto;padding:20px;display:flex;flex-direction:column;gap:12px;background:#fafbfc}
        .msg{max-width:75%;padding:12px 16px;border-radius:12px;font-size:.93rem;line-height:1.6;word-break:break-word;white-space:pre-wrap}
        .msg.user{background:#4361ee;color:#fff;align-self:flex-end;border-bottom-right-radius:4px}
        [dir=rtl] .msg.user{align-self:flex-start;border-bottom-right-radius:12px;border-bottom-left-radius:4px}
        .msg.bot{background:#fff;border:1px solid #e2e8f0;align-self:flex-start;border-bottom-left-radius:4px}
        [dir=rtl] .msg.bot{align-self:flex-end;border-bottom-left-radius:12px;border-bottom-right-radius:4px}
        .msg.thinking{opacity:.6;font-style:italic}
        .msg-meta{font-size:.72rem;opacity:.6;margin-top:4px}
        .msg-file-badge{display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.15);border-radius:6px;padding:3px 8px;font-size:.78rem;margin-bottom:6px}
        .ai-form{border-top:1px solid #e2e8f0;padding:16px 20px;display:flex;gap:10px;align-items:flex-end;background:#fff}
        .ai-input{flex:1;resize:none;border:1px solid #d1d5db;border-radius:8px;padding:10px 14px;font-size:.93rem;font-family:inherit;min-height:44px;max-height:120px;outline:none;transition:border-color .2s}
        .ai-input:focus{border-color:#4361ee}
        .btn-send{background:#4361ee;color:#fff;border:none;border-radius:8px;padding:10px 20px;font-size:.93rem;cursor:pointer;transition:background .2s;white-space:nowrap}
        .btn-send:hover{background:#3a0ca3}
        .btn-send:disabled{opacity:.5;cursor:not-allowed}
        .btn-attach{background:#f1f5f9;border:1px solid #d1d5db;border-radius:8px;padding:10px 14px;cursor:pointer;font-size:1.1rem;transition:background .2s}
        .btn-attach:hover{background:#e2e8f0}
        .file-preview{padding:8px 20px;background:#eff6ff;border-top:1px solid #bfdbfe;font-size:.82rem;color:#1e40af;display:flex;align-items:center;gap:8px}
        .file-preview .remove{cursor:pointer;color:#ef4444;font-weight:bold;margin-inline-start:auto}
        .sources-block{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px 14px;margin-top:8px;font-size:.8rem}
        .sources-block summary{cursor:pointer;font-weight:600;color:#4361ee}
        .source-item{border-top:1px solid #e2e8f0;padding:6px 0;color:#64748b}
        #file-input{display:none}
        .lang-switcher{display:flex;gap:8px;margin-bottom:16px;justify-content:flex-end}
        [dir=rtl] .lang-switcher{justify-content:flex-start}
        .lang-btn{padding:5px 14px;border-radius:20px;border:1px solid #d1d5db;background:#fff;cursor:pointer;font-size:.82rem}
        .lang-btn.active{background:#4361ee;color:#fff;border-color:#4361ee}
        .thread-bar{padding:10px 20px;background:#f8fafc;border-bottom:1px solid #e2e8f0;font-size:.82rem;color:#64748b;display:flex;align-items:center;gap:8px}
        .btn-new-thread{background:none;border:1px solid #d1d5db;border-radius:6px;padding:3px 10px;cursor:pointer;font-size:.8rem;color:#4361ee}
        .btn-new-thread:hover{background:#eff6ff}
    </style>
</head>
<body>

<?php
// تضمين الهيدر إن وُجد (اختياري)
$headerFile = dirname(__DIR__) . '/partials/header.php';
if (file_exists($headerFile) && isset($GLOBALS['PUBLIC_UI'])) {
    // تجنّب إعادة طباعة DOCTYPE إذا أُدرج الهيدر
}
?>

<div class="ai-wrap">

    <!-- Language Switcher -->
    <div class="lang-switcher">
        <a href="?lang=ar" class="lang-btn <?= $lang === 'ar' ? 'active' : '' ?>">العربية</a>
        <a href="?lang=en" class="lang-btn <?= $lang === 'en' ? 'active' : '' ?>">English</a>
    </div>

    <div class="ai-card">

        <!-- Header -->
        <div class="ai-header">
            <span style="font-size:1.6rem">🤖</span>
            <div>
                <h1><?= t($t, 'ai_chat_title', 'AI Assistant') ?></h1>
                <div class="badge">QOOQZ RAG</div>
            </div>
        </div>

        <!-- Thread Bar -->
        <div class="thread-bar">
            <span id="thread-label"><?= t($t, 'ai_threads', 'Conversations') ?>: —</span>
            <button class="btn-new-thread" onclick="startNewThread()">
                + <?= t($t, 'ai_new_thread', 'New Chat') ?>
            </button>
        </div>

        <!-- Messages -->
        <div class="ai-messages" id="messages"></div>

        <!-- File Preview -->
        <div class="file-preview" id="file-preview" style="display:none">
            <span>📎</span>
            <span id="file-name"></span>
            <span class="remove" onclick="removeFile()">✕</span>
        </div>

        <!-- Form -->
        <form class="ai-form" id="chat-form" onsubmit="sendMessage(event)">
            <label for="file-input" class="btn-attach" title="<?= t($t, 'ai_attach_file', 'Attach file') ?>">📎</label>
            <input type="file" id="file-input" accept="image/*,.pdf,.txt,.docx,.csv,.md"
                   onchange="onFileSelect(this)">
            <textarea class="ai-input" id="question" rows="1"
                      placeholder="<?= t($t, 'ai_placeholder', 'Type your question...') ?>"
                      onkeydown="handleKey(event)"></textarea>
            <button type="submit" class="btn-send" id="send-btn">
                <?= t($t, 'ai_send', 'Send') ?>
            </button>
        </form>

    </div>
</div>

<script>
const AI_BASE = <?= json_encode($aiApiBase) ?>;
const LANG    = <?= json_encode($lang) ?>;
const DIR     = <?= json_encode($direction) ?>;
const L       = {
    thinking  : <?= json_encode($t['ai_thinking'] ?? 'Thinking...') ?>,
    error     : <?= json_encode($t['ai_error']    ?? 'Error') ?>,
    sources   : <?= json_encode($t['ai_sources']  ?? 'Sources') ?>,
    latency   : <?= json_encode($t['ai_latency']  ?? 'Latency') ?>,
    ms        : <?= json_encode($t['ai_ms']        ?? 'ms') ?>,
    attached  : <?= json_encode($t['ai_file_attached'] ?? 'Attached') ?>,
};

let threadId  = null;
let attachedFile = null;

function startNewThread() {
    threadId = null;
    attachedFile = null;
    removeFile();
    document.getElementById('messages').innerHTML = '';
    document.getElementById('thread-label').textContent = <?= json_encode(($t['ai_threads'] ?? 'Conversations') . ': —') ?>;
}

function onFileSelect(input) {
    if (input.files && input.files[0]) {
        attachedFile = input.files[0];
        document.getElementById('file-name').textContent = attachedFile.name;
        document.getElementById('file-preview').style.display = 'flex';
    }
}

function removeFile() {
    attachedFile = null;
    document.getElementById('file-input').value = '';
    document.getElementById('file-preview').style.display = 'none';
}

function handleKey(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        document.getElementById('chat-form').dispatchEvent(new Event('submit'));
    }
}

function appendMessage(role, text, meta) {
    const box = document.getElementById('messages');
    const div = document.createElement('div');
    div.className = 'msg ' + (role === 'user' ? 'user' : 'bot');

    if (meta && meta.filename) {
        const badge = document.createElement('div');
        badge.className = 'msg-file-badge';
        badge.textContent = '📎 ' + meta.filename;
        div.appendChild(badge);
    }

    const textNode = document.createElement('div');
    textNode.textContent = text;
    div.appendChild(textNode);

    if (meta && meta.latency_ms !== undefined) {
        const m = document.createElement('div');
        m.className = 'msg-meta';
        m.textContent = L.latency + ': ' + meta.latency_ms + ' ' + L.ms;
        div.appendChild(m);
    }

    if (meta && meta.sources && meta.sources.length > 0) {
        const det = document.createElement('details');
        det.className = 'sources-block';
        const sum = document.createElement('summary');
        sum.textContent = L.sources + ' (' + meta.sources.length + ')';
        det.appendChild(sum);
        meta.sources.forEach(function(s) {
            const si = document.createElement('div');
            si.className = 'source-item';
            si.textContent = (s.score ? '[' + (s.score * 100).toFixed(0) + '%] ' : '') + s.content;
            det.appendChild(si);
        });
        div.appendChild(det);
    }

    box.appendChild(div);
    box.scrollTop = box.scrollHeight;
    return div;
}

async function sendMessage(e) {
    e.preventDefault();
    const input = document.getElementById('question');
    const question = input.value.trim();
    if (!question && !attachedFile) return;

    input.value = '';
    input.style.height = '';

    const sendBtn = document.getElementById('send-btn');
    sendBtn.disabled = true;

    // رسالة المستخدم
    const pendingFile = attachedFile;
    appendMessage('user', question, pendingFile ? {filename: pendingFile.name} : null);
    removeFile();

    // رسالة "جارٍ التفكير"
    const thinkDiv = appendMessage('bot', L.thinking, null);
    thinkDiv.classList.add('thinking');

    try {
        const fd = new FormData();
        fd.append('question', question || ' ');
        if (threadId) fd.append('thread_id', threadId);

        let endpoint = AI_BASE + '/chat';

        if (pendingFile) {
            fd.append('image', pendingFile);
            endpoint = AI_BASE + '/chat/with-image';
        }

        const resp = await fetch(endpoint, {method: 'POST', body: fd});

        thinkDiv.remove();

        if (!resp.ok) {
            const errText = await resp.text();
            appendMessage('bot', L.error + ' (HTTP ' + resp.status + '): ' + errText);
        } else {
            const data = await resp.json();
            if (data.thread_id) {
                threadId = data.thread_id;
                document.getElementById('thread-label').textContent =
                    <?= json_encode($t['ai_threads'] ?? 'Conversations') ?> + ': ' + threadId.slice(0,8) + '…';
            }
            appendMessage('bot', data.answer || '', {
                latency_ms : data.metadata?.latency_ms,
                sources    : data.sources || [],
            });
        }
    } catch (err) {
        thinkDiv.remove();
        appendMessage('bot', L.error + ': ' + err.message);
    }

    sendBtn.disabled = false;
    input.focus();
}

// Auto-resize textarea
document.getElementById('question').addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});
</script>
</body>
</html>
