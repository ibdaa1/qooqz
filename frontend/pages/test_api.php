<?php
/**
 * frontend/pages/test_api.php
 * واجهة المساعد الذكي — نفس طريقة الاتصال الأصلية + دعم اللغات
 */
session_start();

// ===== اللغة =====
$_allowed = ['ar', 'en'];
$lang = in_array($_GET['lang'] ?? '', $_allowed, true)
    ? $_GET['lang']
    : ($_SESSION['lang'] ?? 'ar');
$_SESSION['lang'] = $lang;
$dir = in_array($lang, ['ar', 'fa', 'ur', 'he'], true) ? 'rtl' : 'ltr';

$_lf = dirname(__DIR__, 2) . '/languages/frontend/main/' . $lang . '.json';
if (!file_exists($_lf)) {
    $_lf = dirname(__DIR__, 2) . '/languages/frontend/main/ar.json';
}
$L = file_exists($_lf) ? (json_decode(file_get_contents($_lf), true) ?? []) : [];

function L(array $t, string $k, string $fb = ''): string {
    return htmlspecialchars($t[$k] ?? $fb, ENT_QUOTES, 'UTF-8');
}

// ===== إعدادات API (نفس طريقة index.php الأصلية) =====
$API_BASE = "http://127.0.0.1:8888";

// ===== حالة الصحة =====
$api_ok = false;
$health_data = null;
$ch_h = curl_init($API_BASE . "/api/v1/health");
curl_setopt_array($ch_h, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 3,
]);
$h_raw = curl_exec($ch_h);
curl_close($ch_h);
if ($h_raw) {
    $health_data = json_decode($h_raw, true);
    $api_ok = ($health_data && ($health_data['status'] ?? '') === 'ok');
}

// ===== معالجة الإرسال =====
$response_data = null;
$error_msg     = null;
$question      = trim($_POST['question'] ?? '');
$thread_id     = $_POST['thread_id'] ?? ($_SESSION['thread_id'] ?? '');
$uploaded_image = null;
$file_context_text = '';  // always defined (used in rendering too)

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($question)) {
    $ch = curl_init();

    $has_file   = (!empty($_FILES['image']['tmp_name'])        && $_FILES['image']['error']        === 0);
    $has_camera = (!empty($_FILES['camera_image']['tmp_name']) && $_FILES['camera_image']['error'] === 0);
    $has_doc    = (!empty($_FILES['document_file']['tmp_name']) && $_FILES['document_file']['error'] === 0);
    // Treat camera capture as an image upload
    if ($has_camera && !$has_file) {
        $_FILES['image'] = $_FILES['camera_image'];
        $has_file = true;
    }

    // ====== خطوة 1: رفع الملف واستخراج النص (يعمل مع الصور والمستندات) ======
    $file_name_display = '';
    $upload_failed = false;
    if ($has_file || $has_doc) {
        $fkey = $has_file ? $_FILES['image'] : $_FILES['document_file'];
        if ($has_file) $uploaded_image = $fkey['name'];
        $file_name_display = $fkey['name'];

        $uch = curl_init($API_BASE . '/api/v1/files/upload');
        curl_setopt_array($uch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => ['file' => new CURLFile($fkey['tmp_name'], $fkey['type'], $fkey['name'])],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
        ]);
        $up_raw  = curl_exec($uch);
        $up_code = curl_getinfo($uch, CURLINFO_HTTP_CODE);
        curl_close($uch);
        if ($up_code === 200) {
            $up_resp = json_decode($up_raw, true);
            if ($up_resp && !empty($up_resp['extracted_text'])) {
                $file_context_text = $up_resp['extracted_text'];
            }
        } else {
            $upload_failed = true;
        }
    }

    // Fallback to client-side OCR text (Tesseract.js) if server extracted nothing
    $client_ocr = trim($_POST['ocr_text'] ?? '');
    if (!$file_context_text && $client_ocr) {
        $file_context_text = $client_ocr;
    }

    // ====== خطوة 2: بناء السؤال مع محتوى الملف ======
    $full_q = $question;
    if ($file_context_text) {
        $full_q .= "\n\n[محتوى الملف المرفق '" . $file_name_display . "':\n" . mb_substr($file_context_text, 0, 3000) . ']';
    } elseif ($file_name_display) {
        $full_q .= "\n\n[الملف المرفق: " . $file_name_display . ']';
    }

    // ====== خطوة 3: الدردشة مع السياق الكامل ======
    $post_data = ['question' => $full_q];
    if (!empty($thread_id)) $post_data['thread_id'] = $thread_id;

    curl_setopt_array($ch, [
        CURLOPT_URL            => $API_BASE . '/api/v1/chat',
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $post_data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);

    $result    = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    if ($curl_err) {
        $error_msg = L($L, 'ai_conn_error', 'خطأ في الاتصال') . ': ' . $curl_err;
    } elseif ($http_code !== 200) {
        $error_msg = "HTTP {$http_code}";
        $decoded = json_decode($result, true);
        if ($decoded && isset($decoded['detail'])) $error_msg .= ' — ' . $decoded['detail'];
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
    $hch = curl_init($API_BASE . '/api/v1/threads/' . urlencode($thread_id));
    curl_setopt_array($hch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $hraw = curl_exec($hch);
    curl_close($hch);
    if ($hraw) {
        $hdata = json_decode($hraw, true);
        if ($hdata && isset($hdata['messages'])) {
            $history = $hdata['messages'];
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
    header('Location: test_api.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= L($L,'ai_chat_title','المساعد الذكي') ?> — AI Chat</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/ai-chat.css">
</head>
<body>

<!-- HEADER -->
<div class="header">
    <div class="header-right">
        <div class="logo">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 1v4m0 14v4M4.22 4.22l2.83 2.83m9.9 9.9l2.83 2.83M1 12h4m14 0h4M4.22 19.78l2.83-2.83m9.9-9.9l2.83-2.83"/></svg>
            <?= L($L,'ai_chat_title','المساعد الذكي') ?>
            <span class="version">v1.0</span>
        </div>
        <span class="status-label">
            <span class="status-dot <?= $api_ok ? 'on' : 'off' ?>"></span>
            <?= $api_ok ? L($L,'ai_connected','متصل') : L($L,'ai_disconnected','غير متصل') ?>
        </span>
    </div>
    <div class="header-left">
        <?php if (!empty($thread_id)): ?>
            <span style="font-size:.7rem;color:var(--text3);direction:ltr"><?= substr($thread_id,0,8) ?>...</span>
        <?php endif; ?>
        <a href="?new=1&lang=<?= htmlspecialchars($lang) ?>" class="btn-sm">✦ <?= L($L,'ai_new_thread','محادثة جديدة') ?></a>
        <a href="?lang=ar" class="btn-sm <?= $lang==='ar' ? 'lang-active' : '' ?>">ع</a>
        <a href="?lang=en" class="btn-sm <?= $lang==='en' ? 'lang-active' : '' ?>">EN</a>
    </div>
</div>

<!-- HEALTH STRIP -->
<?php if ($api_ok && $health_data): ?>
<div class="health-strip">
    <span class="hs-item">✅ API</span>
    <?php if (!empty($health_data['database_connection'])): ?>
        <span class="hs-item">🗄️ DB</span>
    <?php endif; ?>
    <?php if (isset($health_data['total_chunks_found'])): ?>
        <span class="hs-item">📦 <?= $health_data['total_chunks_found'] ?></span>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- CHAT AREA -->
<div class="chat-wrap" id="chatWrap">
    <div class="chat-inner" id="chatInner">

        <?php if (empty($history) && empty($question)): ?>
        <div class="welcome">
            <div class="welcome-icon">🤖</div>
            <h2><?= L($L,'ai_welcome_title','مرحباً بك في المساعد الذكي') ?></h2>
            <p><?= L($L,'ai_welcome_desc','اطرح أي سؤال وسأبحث في قاعدة المعرفة للعثور على أفضل إجابة.') ?></p>
            <div class="suggestions">
                <button class="sug-btn" onclick='ask(<?= json_encode($lang==='en' ? 'What is Artificial Intelligence?' : 'ما هو الذكاء الاصطناعي؟') ?>)'><?= L($L,'ai_suggest_ai','🧠 ما هو الذكاء الاصطناعي؟') ?></button>
                <button class="sug-btn" onclick='ask(<?= json_encode($lang==='en' ? 'ML vs DL difference' : 'ما الفرق بين Machine Learning و Deep Learning؟') ?>)'><?= L($L,'ai_suggest_ml','⚡ ML vs DL') ?></button>
                <button class="sug-btn" onclick='ask(<?= json_encode($lang==='en' ? 'What is HTTP?' : 'ما هو HTTP؟') ?>)'><?= L($L,'ai_suggest_http','🌐 ما هو HTTP؟') ?></button>
            </div>
        </div>
        <?php else: ?>

            <?php foreach ($history as $msg): ?>
                <?php $role = $msg['role'] ?? 'user'; ?>
                <div class="msg <?= htmlspecialchars($role) ?>">
                    <div class="avatar"><?= $role === 'user' ? '👤' : '🤖' ?></div>
                    <?php if ($role === 'user'): ?>
                    <div class="bubble"><?= nl2br(htmlspecialchars($msg['content'] ?? '')) ?></div>
                    <?php else: ?>
                    <div class="bubble md-bubble" data-md="<?= htmlspecialchars(json_encode($msg['content'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <?php if (!empty($question)): ?>
                <div class="msg user">
                    <div class="avatar">👤</div>
                    <div>
                        <div class="bubble"><?= nl2br(htmlspecialchars($question)) ?></div>
                        <?php if ($uploaded_image): ?>
                            <div style="margin-top:6px;font-size:.72rem;color:var(--text3)">📎 <?= htmlspecialchars($uploaded_image) ?></div>
                            <?php
                            // Show inline preview for images uploaded in this request (max 5MB)
                            $imgExt = strtolower(pathinfo($uploaded_image, PATHINFO_EXTENSION));
                            $imgExts = ['jpg','jpeg','png','gif','bmp','webp'];
                            $imgSize = $_FILES['image']['size'] ?? 0;
                            if (in_array($imgExt, $imgExts) && !empty($_FILES['image']['tmp_name']) && $imgSize <= 5 * 1024 * 1024):
                                $imgB64 = base64_encode(file_get_contents($_FILES['image']['tmp_name']));
                                $mime   = mime_content_type($_FILES['image']['tmp_name']) ?: 'image/png';
                            ?>
                            <img src="data:<?= htmlspecialchars($mime) ?>;base64,<?= $imgB64 ?>"
                                 alt="<?= htmlspecialchars($uploaded_image) ?>"
                                 style="max-width:220px;max-height:160px;border-radius:8px;margin-top:6px;display:block">
                            <?php endif; ?>
                            <?php if (!empty($upload_failed)): ?>
                            <div style="color:#e53e3e;font-size:.72rem;margin-top:4px">⚠️ فشل رفع الملف للخادم — سيتم البحث بدون محتواه</div>
                            <?php endif; ?>
                            <?php if (!empty($file_context_text)): ?>
                            <details class="ocr-block">
                                <summary>📝 <?= L($L,'ai_ocr_title','النص المستخرج') ?> (<?= mb_strlen($file_context_text) ?> <?= L($L,'ai_chars','حرف') ?>)</summary>
                                <pre class="ocr-block-text"><?= htmlspecialchars(mb_substr($file_context_text, 0, 800)) ?></pre>
                            </details>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error_msg): ?>
                <div class="error-box">❌ <?= htmlspecialchars($error_msg) ?></div>
            <?php endif; ?>

            <?php if ($response_data && isset($response_data['answer'])): ?>
                <div class="msg bot">
                    <div class="avatar">🤖</div>
                    <div style="min-width:0">
                        <div class="bubble md-bubble" data-md="<?= htmlspecialchars(json_encode($response_data['answer'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>

                        <?php if (!empty($response_data['sources'])): ?>
                            <div class="sources-panel">
                                <div class="sources-title">📚 <?= L($L,'ai_sources','المصادر') ?></div>
                                <?php foreach ($response_data['sources'] as $i => $src): ?>
                                    <div class="src-item">
                                        <?= $i+1 ?>. <?= htmlspecialchars(mb_substr($src['content'] ?? $src['content_preview'] ?? '', 0, 120)) ?>
                                        <?php if (isset($src['score'])): ?>
                                            <span class="score"><?= round(($src['score'] ?? 0) * 100) ?>%</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php $m = $response_data['metadata'] ?? []; ?>
                        <div class="meta-row">
                            <span class="chip">⚡ <?= $m['latency_ms'] ?? 0 ?><?= L($L,'ai_ms','ms') ?></span>
                            <span class="chip">📝 <?= $m['input_tokens'] ?? 0 ?> <?= L($L,'ai_tokens_in','دخول') ?></span>
                            <span class="chip">📤 <?= $m['output_tokens'] ?? 0 ?> <?= L($L,'ai_tokens_out','خروج') ?></span>
                            <span class="chip">📊 <?= $m['sources_found'] ?? 0 ?> <?= L($L,'ai_sources','مصادر') ?></span>
                            <span class="chip">🧠 <?= htmlspecialchars($m['model'] ?? 'local') ?></span>
                            <?php if (!empty($m['has_memory'])): ?>
                                <span class="chip">💾 <?= L($L,'ai_memory','ذاكرة') ?></span>
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
            <div id="ocrPreview" class="ocr-preview" style="display:none">
                <div id="ocrStatus" class="ocr-status"></div>
                <pre id="ocrExtracted" class="ocr-extracted"></pre>
            </div>
        </div>
        <form method="POST" enctype="multipart/form-data" class="form-row" id="chatForm">
            <input type="hidden" name="thread_id" value="<?= htmlspecialchars($thread_id) ?>">
            <input type="file" name="image"         id="imageInput"  accept="image/*"                                     style="display:none" onchange="showFile('image')">
            <input type="file" name="camera_image"  id="cameraInput" accept="image/*" capture="environment"               style="display:none" onchange="showCameraFile()">
            <input type="file" name="document_file" id="docInput"    accept=".txt,.pdf,.doc,.docx,.csv,.xlsx"              style="display:none" onchange="showFile('document')">
            <input type="hidden" name="ocr_text" id="ocrTextInput">

            <!-- Side buttons (outside textarea) -->
            <div class="side-btns">
                <button type="button" class="side-btn" id="imgBtn" onclick="document.getElementById('imageInput').click()" title="<?= L($L,'ai_attach_img','إرفاق صورة') ?>">🖼️</button>
                <button type="button" class="side-btn" id="cameraBtn" title="<?= L($L,'ai_camera','مسح مستند بالكاميرا') ?>">📷</button>
                <button type="button" class="side-btn" id="docBtn" onclick="document.getElementById('docInput').click()" title="<?= L($L,'ai_attach_doc','إرفاق ملف') ?>">📎</button>
                <button type="button" class="side-btn" id="micBtn" title="<?= L($L,'ai_voice','تسجيل صوتي') ?>">🎤</button>
            </div>

            <div class="textarea-wrap">
                <textarea name="question" id="qInput"
                    placeholder="<?= L($L,'ai_placeholder','اكتب سؤالك هنا...') ?>"
                    rows="1" required></textarea>
            </div>
            <button type="submit" class="send-btn" id="sendBtn" title="<?= L($L,'ai_send','إرسال') ?>">
                <span class="send-icon">➤</span>
                <span class="spinner"></span>
            </button>
        </form>
    </div>
</div>

<script>var AI_LANG='<?= $lang ?>';</script>
<script src="https://cdn.jsdelivr.net/npm/marked@9/marked.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js" crossorigin="anonymous"></script>
<script src="../assets/js/ai-chat.js"></script>

</body>
</html>
