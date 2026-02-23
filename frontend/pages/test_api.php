<?php
/**
 * 🤖 المساعد الذكي - نسخة الإنتاج
 * واجهة دردشة كاملة مع دعم الصور والملفات
 */
session_start();

$API_BASE = "http://127.0.0.1:8888";

// ====== حالة الصحة (سريع) ======
$api_ok = false;
$health_data = null;
$ctx = stream_context_create(['http' => ['timeout' => 3]]);
$health_raw = @file_get_contents($API_BASE . "/api/v1/health", false, $ctx);
if ($health_raw) {
    $health_data = json_decode($health_raw, true);
    $api_ok = ($health_data && ($health_data['status'] ?? '') === 'ok');
}

// ====== معالجة الإرسال ======
$response_data = null;
$error_msg = null;
$question = trim($_POST['question'] ?? '');
$thread_id = $_POST['thread_id'] ?? ($_SESSION['thread_id'] ?? '');
$uploaded_image = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($question)) {
    $ch = curl_init();

    $has_file = (!empty($_FILES['image']['tmp_name']) && $_FILES['image']['error'] === 0);
    $has_doc  = (!empty($_FILES['document_file']['tmp_name']) && $_FILES['document_file']['error'] === 0);

    if ($has_file) {
        // دردشة مع صورة
        $post_data = [
            'question' => $question,
            'image'    => new CURLFile(
                $_FILES['image']['tmp_name'],
                $_FILES['image']['type'],
                $_FILES['image']['name']
            ),
        ];
        if (!empty($thread_id)) $post_data['thread_id'] = $thread_id;

        curl_setopt_array($ch, [
            CURLOPT_URL            => $API_BASE . '/api/v1/chat/with-image',
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $post_data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);
        $uploaded_image = $_FILES['image']['name'];
    } else {
        // دردشة عادية
        $post_data = ['question' => $question];
        if (!empty($thread_id)) $post_data['thread_id'] = $thread_id;

        curl_setopt_array($ch, [
            CURLOPT_URL            => $API_BASE . '/api/v1/chat',
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $post_data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);
    }

    // رفع مستند منفصل
    if ($has_doc) {
        $dch = curl_init();
        curl_setopt_array($dch, [
            CURLOPT_URL            => $API_BASE . '/api/v1/files/upload',
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => [
                'file' => new CURLFile(
                    $_FILES['document_file']['tmp_name'],
                    $_FILES['document_file']['type'],
                    $_FILES['document_file']['name']
                ),
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
        ]);
        curl_exec($dch);
        curl_close($dch);
    }

    $result    = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    if ($curl_err) {
        $error_msg = "خطأ في الاتصال: " . $curl_err;
    } elseif ($http_code !== 200) {
        $error_msg = "خطأ HTTP: " . $http_code;
        $decoded = json_decode($result, true);
        if ($decoded && isset($decoded['detail'])) $error_msg .= " — " . $decoded['detail'];
    } else {
        $response_data = json_decode($result, true);
        if ($response_data && isset($response_data['thread_id'])) {
            $thread_id = $response_data['thread_id'];
            $_SESSION['thread_id'] = $thread_id;
        }
    }
}

// جلب تاريخ المحادثة
$history = [];
if (!empty($thread_id)) {
    $hraw = @file_get_contents($API_BASE . "/api/v1/threads/" . urlencode($thread_id), false, $ctx);
    if ($hraw) {
        $hdata = json_decode($hraw, true);
        if ($hdata && isset($hdata['messages'])) {
            $history = $hdata['messages'];
            // اقصِ آخر رسالتين لأنهما الحالية (سيتم عرضها أدناه)
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && count($history) >= 2) {
                array_pop($history);
                array_pop($history);
            }
        }
    }
}

// محادثة جديدة
if (isset($_GET['new'])) {
    unset($_SESSION['thread_id']);
    $thread_id = '';
    header("Location: test_api.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المساعد الذكي — AI Chat</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        :root{
            --bg:#090b10;--bg2:#0f1218;--card:#151921;--card2:#1a1f2b;
            --brd:#252a36;--brd2:#363d4e;
            --text:#e4e8f1;--text2:#8892a6;--text3:#5d6577;
            --accent:#7c6aff;--accent2:#6555e0;--accent-g:linear-gradient(135deg,#7c6aff,#5a45e0);
            --green:#2dd4a0;--green-bg:rgba(45,212,160,.08);--green-brd:rgba(45,212,160,.2);
            --red:#ff5c6a;--red-bg:rgba(255,92,106,.08);
            --orange:#ffa94d;--blue:#5eaeff;
            --radius:14px;--radius-sm:10px;
            --shadow:0 4px 24px rgba(0,0,0,.35);
            --shadow2:0 8px 40px rgba(0,0,0,.5);
        }
        html{height:100%}
        body{
            font-family:'Tajawal','Segoe UI',sans-serif;
            background:var(--bg);color:var(--text);
            height:100%;display:flex;flex-direction:column;
            -webkit-font-smoothing:antialiased;
        }

        /* ===== HEADER ===== */
        .header{
            background:linear-gradient(180deg,var(--card) 0%,var(--bg2) 100%);
            border-bottom:1px solid var(--brd);
            padding:12px 24px;display:flex;align-items:center;justify-content:space-between;
            position:sticky;top:0;z-index:100;
            backdrop-filter:blur(12px);
        }
        .header-right{display:flex;align-items:center;gap:14px}
        .logo{font-size:1.25rem;font-weight:700;display:flex;align-items:center;gap:8px}
        .logo svg{width:28px;height:28px}
        .version{
            background:var(--accent);color:#fff;padding:2px 9px;border-radius:20px;
            font-size:.65rem;font-weight:700;letter-spacing:.5px;
        }
        .status-dot{
            width:9px;height:9px;border-radius:50%;display:inline-block;
            box-shadow:0 0 6px currentColor;
        }
        .status-dot.on{background:var(--green);color:var(--green)}
        .status-dot.off{background:var(--red);color:var(--red)}
        .status-label{font-size:.78rem;color:var(--text2);display:flex;align-items:center;gap:5px}
        .header-left{display:flex;align-items:center;gap:10px}
        .btn-sm{
            background:var(--card2);border:1px solid var(--brd);color:var(--text2);
            padding:6px 14px;border-radius:8px;font-size:.78rem;cursor:pointer;
            text-decoration:none;display:inline-flex;align-items:center;gap:5px;
            transition:all .2s;font-family:inherit;
        }
        .btn-sm:hover{border-color:var(--accent);color:var(--accent);background:rgba(124,106,255,.06)}

        /* ===== CHAT AREA ===== */
        .chat-wrap{flex:1;overflow-y:auto;padding:20px 0;scroll-behavior:smooth}
        .chat-inner{max-width:800px;margin:0 auto;padding:0 20px;display:flex;flex-direction:column;gap:6px}

        /* message row */
        .msg{display:flex;gap:10px;max-width:88%;animation:fadeUp .35s ease}
        .msg.user{align-self:flex-end;flex-direction:row-reverse}
        .msg.bot{align-self:flex-start}
        @keyframes fadeUp{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}

        .avatar{
            width:36px;height:36px;border-radius:50%;display:flex;align-items:center;
            justify-content:center;font-size:1rem;flex-shrink:0;
        }
        .msg.user .avatar{background:var(--accent);color:#fff}
        .msg.bot .avatar{background:var(--green);color:#000;font-size:1.1rem}

        .bubble{
            padding:12px 16px;border-radius:var(--radius);line-height:1.75;
            font-size:.92rem;white-space:pre-wrap;word-break:break-word;
        }
        .msg.user .bubble{
            background:var(--accent2);color:#fff;border-bottom-right-radius:4px;
        }
        .msg.bot .bubble{
            background:var(--card2);border:1px solid var(--brd);border-bottom-left-radius:4px;
        }

        /* sources panel */
        .sources-panel{
            margin-top:8px;padding:10px 14px;border-radius:var(--radius-sm);
            background:rgba(124,106,255,.05);border:1px solid rgba(124,106,255,.12);
        }
        .sources-title{color:var(--accent);font-weight:700;font-size:.8rem;margin-bottom:6px}
        .src-item{
            font-size:.78rem;color:var(--text2);padding:4px 0;
            border-bottom:1px solid rgba(255,255,255,.03);
        }
        .src-item:last-child{border:0}
        .score{
            display:inline-block;background:var(--accent);color:#fff;padding:1px 7px;
            border-radius:20px;font-size:.65rem;font-weight:700;margin-right:4px;
        }

        /* meta chips */
        .meta-row{display:flex;gap:6px;flex-wrap:wrap;margin-top:6px}
        .chip{
            background:rgba(255,255,255,.04);border:1px solid var(--brd);
            padding:2px 9px;border-radius:20px;font-size:.68rem;color:var(--text3);
            display:inline-flex;align-items:center;gap:3px;
        }
        .chip .emoji{font-size:.72rem}
        .thread-info{font-size:.72rem;color:var(--orange);margin-top:4px}

        /* welcome */
        .welcome{text-align:center;padding:60px 20px}
        .welcome-icon{font-size:3.2rem;margin-bottom:12px;animation:float 3s ease-in-out infinite}
        @keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}
        .welcome h2{font-size:1.3rem;margin-bottom:8px;font-weight:700}
        .welcome p{color:var(--text2);max-width:460px;margin:0 auto 20px;line-height:1.7;font-size:.9rem}
        .suggestions{display:flex;flex-wrap:wrap;gap:8px;justify-content:center}
        .sug-btn{
            background:var(--card2);border:1px solid var(--brd);color:var(--text);
            padding:8px 18px;border-radius:24px;cursor:pointer;
            font-size:.82rem;transition:all .2s;font-family:inherit;
        }
        .sug-btn:hover{border-color:var(--accent);background:rgba(124,106,255,.08);transform:translateY(-1px)}

        /* error */
        .error-box{
            background:var(--red-bg);border:1px solid rgba(255,92,106,.2);color:var(--red);
            padding:12px 16px;border-radius:var(--radius-sm);font-size:.88rem;text-align:center;
            max-width:600px;margin:10px auto;
        }

        /* ===== INPUT BAR ===== */
        .input-bar{
            background:var(--card);border-top:1px solid var(--brd);padding:14px 20px;
        }
        .input-inner{max-width:800px;margin:0 auto}

        /* attachment preview */
        .attach-row{
            display:flex;gap:8px;margin-bottom:8px;flex-wrap:wrap;align-items:center;
        }
        .attach-tag{
            display:none;align-items:center;gap:4px;
            background:rgba(124,106,255,.1);border:1px solid rgba(124,106,255,.18);
            padding:4px 10px;border-radius:20px;font-size:.75rem;color:var(--accent);
        }
        .attach-tag.show{display:inline-flex}
        .attach-tag .remove{cursor:pointer;margin-right:4px;opacity:.6}
        .attach-tag .remove:hover{opacity:1}

        .form-row{display:flex;gap:10px;align-items:flex-end}
        .textarea-wrap{flex:1;position:relative}
        .textarea-wrap textarea{
            width:100%;background:var(--bg2);border:2px solid var(--brd);border-radius:var(--radius);
            padding:13px 16px 13px 44px;color:var(--text);font-size:.95rem;font-family:inherit;
            resize:none;outline:none;transition:border-color .3s;min-height:48px;max-height:140px;
        }
        .textarea-wrap textarea:focus{border-color:var(--accent)}
        .textarea-wrap textarea::placeholder{color:var(--text3)}

        /* attachment buttons inside textarea */
        .attach-btns{
            position:absolute;bottom:11px;left:10px;display:flex;gap:4px;
        }
        .attach-btn{
            width:32px;height:32px;border-radius:8px;border:none;
            background:var(--card2);color:var(--text3);cursor:pointer;
            display:flex;align-items:center;justify-content:center;font-size:.9rem;
            transition:all .2s;
        }
        .attach-btn:hover{background:var(--accent);color:#fff}
        input[type="file"]{display:none}

        .send-btn{
            background:var(--accent-g);border:none;width:48px;height:48px;border-radius:var(--radius);
            cursor:pointer;display:flex;align-items:center;justify-content:center;
            color:#fff;font-size:1.2rem;transition:transform .15s,box-shadow .15s;flex-shrink:0;
        }
        .send-btn:hover{transform:scale(1.06);box-shadow:0 4px 18px rgba(124,106,255,.35)}
        .send-btn:active{transform:scale(.95)}
        .send-btn:disabled{opacity:.4;cursor:not-allowed;transform:none;box-shadow:none}

        /* spinner */
        .spinner{display:none;width:20px;height:20px;border:2px solid rgba(255,255,255,.2);
            border-top-color:#fff;border-radius:50%;animation:spin .6s linear infinite}
        @keyframes spin{to{transform:rotate(360deg)}}
        .sending .spinner{display:block}
        .sending .send-icon{display:none}

        /* health strip */
        .health-strip{
            background:var(--card);border-bottom:1px solid var(--brd);
            padding:6px 20px;font-size:.72rem;color:var(--text3);
            display:flex;align-items:center;justify-content:center;gap:16px;
        }
        .health-strip .hs-item{display:flex;align-items:center;gap:4px}

        /* ===== RESPONSIVE ===== */
        @media(max-width:640px){
            .header{padding:10px 14px}
            .logo{font-size:1.05rem}
            .chat-inner{padding:0 12px}
            .msg{max-width:95%}
            .input-bar{padding:10px 12px}
            .welcome h2{font-size:1.1rem}
            .suggestions{gap:6px}
            .sug-btn{padding:6px 12px;font-size:.78rem}
        }

        /* scrollbar */
        ::-webkit-scrollbar{width:5px}
        ::-webkit-scrollbar-track{background:transparent}
        ::-webkit-scrollbar-thumb{background:var(--brd);border-radius:10px}
        ::-webkit-scrollbar-thumb:hover{background:var(--brd2)}
    </style>
</head>
<body>

<!-- HEADER -->
<div class="header">
    <div class="header-right">
        <div class="logo">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 1v4m0 14v4M4.22 4.22l2.83 2.83m9.9 9.9l2.83 2.83M1 12h4m14 0h4M4.22 19.78l2.83-2.83m9.9-9.9l2.83-2.83"/></svg>
            المساعد الذكي
            <span class="version">v1.0</span>
        </div>
        <span class="status-label">
            <span class="status-dot <?= $api_ok ? 'on' : 'off' ?>"></span>
            <?= $api_ok ? 'متصل' : 'غير متصل' ?>
        </span>
    </div>
    <div class="header-left">
        <?php if (!empty($thread_id)): ?>
            <span style="font-size:.7rem;color:var(--text3);direction:ltr">
                <?= substr($thread_id, 0, 8) ?>...
            </span>
        <?php endif; ?>
        <a href="?new=1" class="btn-sm">✦ محادثة جديدة</a>
        <a href="http://hcsfcs.top:8888/docs" target="_blank" class="btn-sm">API ↗</a>
    </div>
</div>

<!-- HEALTH STRIP -->
<?php if ($api_ok && $health_data): ?>
<div class="health-strip">
    <span class="hs-item">✅ API يعمل</span>
    <?php if (!empty($health_data['database_connection'])): ?>
        <span class="hs-item">🗄️ DB متصل</span>
    <?php endif; ?>
    <?php if (isset($health_data['total_chunks_found'])): ?>
        <span class="hs-item">📦 <?= $health_data['total_chunks_found'] ?> سجل</span>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- CHAT AREA -->
<div class="chat-wrap" id="chatWrap">
    <div class="chat-inner" id="chatInner">

        <?php if (empty($history) && empty($question)): ?>
        <!-- WELCOME -->
        <div class="welcome">
            <div class="welcome-icon">🤖</div>
            <h2>مرحباً بك في المساعد الذكي</h2>
            <p>اطرح أي سؤال وسأبحث في قاعدة المعرفة للعثور على أفضل إجابة. يمكنك أيضاً إرفاق صور وملفات.</p>
            <div class="suggestions">
                <button class="sug-btn" onclick="ask('ما هو الذكاء الاصطناعي؟')">🧠 ما هو الذكاء الاصطناعي؟</button>
                <button class="sug-btn" onclick="ask('ما الفرق بين Machine Learning و Deep Learning؟')">⚡ ML vs DL</button>
                <button class="sug-btn" onclick="ask('ما هو HTTP؟')">🌐 ما هو HTTP؟</button>
            </div>
        </div>
        <?php else: ?>

            <!-- HISTORY -->
            <?php foreach ($history as $msg): ?>
                <?php $role = $msg['role'] ?? 'user'; ?>
                <div class="msg <?= $role ?>">
                    <div class="avatar"><?= $role === 'user' ? '👤' : '🤖' ?></div>
                    <div class="bubble"><?= nl2br(htmlspecialchars($msg['content'] ?? '')) ?></div>
                </div>
            <?php endforeach; ?>

            <!-- CURRENT QUESTION -->
            <?php if (!empty($question)): ?>
                <div class="msg user">
                    <div class="avatar">👤</div>
                    <div>
                        <div class="bubble"><?= nl2br(htmlspecialchars($question)) ?></div>
                        <?php if ($uploaded_image): ?>
                            <div style="margin-top:4px;font-size:.72rem;color:var(--text3)">📎 <?= htmlspecialchars($uploaded_image) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ERROR -->
            <?php if ($error_msg): ?>
                <div class="error-box">❌ <?= htmlspecialchars($error_msg) ?></div>
            <?php endif; ?>

            <!-- AI ANSWER -->
            <?php if ($response_data && isset($response_data['answer'])): ?>
                <div class="msg bot">
                    <div class="avatar">🤖</div>
                    <div style="min-width:0">
                        <div class="bubble"><?= nl2br(htmlspecialchars($response_data['answer'])) ?></div>

                        <?php if (!empty($response_data['sources'])): ?>
                            <div class="sources-panel">
                                <div class="sources-title">📚 المصادر</div>
                                <?php foreach ($response_data['sources'] as $i => $src): ?>
                                    <div class="src-item">
                                        <?= $i + 1 ?>. <?= htmlspecialchars(mb_substr($src['content_preview'] ?? '', 0, 120)) ?>
                                        <?php if (isset($src['score'])): ?>
                                            <span class="score"><?= round(($src['score'] ?? 0) * 100) ?>%</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php $m = $response_data['metadata'] ?? []; ?>
                        <div class="meta-row">
                            <span class="chip"><span class="emoji">⚡</span><?= $m['latency_ms'] ?? 0 ?>ms</span>
                            <span class="chip"><span class="emoji">📝</span><?= $m['input_tokens'] ?? 0 ?> دخول</span>
                            <span class="chip"><span class="emoji">📤</span><?= $m['output_tokens'] ?? 0 ?> خروج</span>
                            <span class="chip"><span class="emoji">📊</span><?= $m['sources_found'] ?? 0 ?> مصادر</span>
                            <span class="chip"><span class="emoji">🧠</span><?= $m['model'] ?? 'local' ?></span>
                            <?php if (!empty($m['has_memory'])): ?>
                                <span class="chip"><span class="emoji">💾</span>ذاكرة</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<!-- INPUT BAR -->
<div class="input-bar">
    <div class="input-inner">
        <div class="attach-row">
            <span class="attach-tag" id="imgTag">
                <span class="remove" onclick="clearFile('image')">&times;</span>
                🖼️ <span id="imgName"></span>
            </span>
            <span class="attach-tag" id="docTag">
                <span class="remove" onclick="clearFile('document')">&times;</span>
                📄 <span id="docName"></span>
            </span>
        </div>
        <form method="POST" enctype="multipart/form-data" class="form-row" id="chatForm">
            <input type="hidden" name="thread_id" value="<?= htmlspecialchars($thread_id) ?>">
            <input type="file" name="image" id="imageInput" accept="image/*" onchange="showFile('image')">
            <input type="file" name="document_file" id="docInput" accept=".txt,.pdf,.doc,.docx,.csv" onchange="showFile('document')">

            <div class="textarea-wrap">
                <textarea name="question" id="qInput" placeholder="اكتب سؤالك هنا..." rows="1" required></textarea>
                <div class="attach-btns">
                    <button type="button" class="attach-btn" onclick="document.getElementById('imageInput').click()" title="إرفاق صورة">🖼️</button>
                    <button type="button" class="attach-btn" onclick="document.getElementById('docInput').click()" title="إرفاق ملف">📎</button>
                </div>
            </div>
            <button type="submit" class="send-btn" id="sendBtn" title="إرسال">
                <span class="send-icon">➤</span>
                <span class="spinner"></span>
            </button>
        </form>
    </div>
</div>

<script>
// التمرير للأسفل
const wrap = document.getElementById('chatWrap');
wrap.scrollTop = wrap.scrollHeight;

// إرسال بـ Enter
document.getElementById('qInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        if (this.value.trim()) document.getElementById('chatForm').submit();
    }
});

// تكبير textarea  تلقائياً
const ta = document.getElementById('qInput');
ta.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 140) + 'px';
});
ta.focus();

// أزرار اقتراحات
function ask(q) {
    document.getElementById('qInput').value = q;
    document.getElementById('chatForm').submit();
}

// عرض اسم الملف المرفق
function showFile(type) {
    const input = type === 'image' ? document.getElementById('imageInput') : document.getElementById('docInput');
    const tag   = type === 'image' ? document.getElementById('imgTag') : document.getElementById('docTag');
    const name  = type === 'image' ? document.getElementById('imgName') : document.getElementById('docName');
    if (input.files.length) {
        name.textContent = input.files[0].name;
        tag.classList.add('show');
    }
}
function clearFile(type) {
    const input = type === 'image' ? document.getElementById('imageInput') : document.getElementById('docInput');
    const tag   = type === 'image' ? document.getElementById('imgTag') : document.getElementById('docTag');
    input.value = '';
    tag.classList.remove('show');
}

// حالة الإرسال
document.getElementById('chatForm').addEventListener('submit', function() {
    const btn = document.getElementById('sendBtn');
    btn.disabled = true;
    btn.parentElement.classList.add('sending');
});
</script>

</body>
</html>
