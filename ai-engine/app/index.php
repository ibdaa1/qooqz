<?php
/**
 * 🤖 AI Chat Interface - واجهة الدردشة الذكية
 * يتصل بـ FastAPI RAG System
 */

// إعدادات API
$API_BASE = "https://hcsfcs.top/ai-engine";
$API_PORT = 8888;
$API_URL = $API_BASE;

// معالجة الطلب
$response_data = null;
$error_msg = null;
$thread_id = $_POST['thread_id'] ?? $_GET['thread_id'] ?? '';
$question = $_POST['question'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($question)) {
    $ch = curl_init();
    
    $post_data = [
        'question' => $question,
    ];
    if (!empty($thread_id)) {
        $post_data['thread_id'] = $thread_id;
    }
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $API_URL . '/api/v1/chat',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post_data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        $error_msg = "خطأ في الاتصال: " . $curl_error;
    } elseif ($http_code !== 200) {
        $error_msg = "خطأ HTTP: " . $http_code;
        if ($result) {
            $decoded = json_decode($result, true);
            if ($decoded && isset($decoded['detail'])) {
                $error_msg .= " - " . $decoded['detail'];
            }
        }
    } else {
        $response_data = json_decode($result, true);
        if ($response_data && isset($response_data['thread_id'])) {
            $thread_id = $response_data['thread_id'];
        }
    }
}

// جلب تاريخ المحادثة إذا كان هناك thread_id
$thread_messages = [];
if (!empty($thread_id) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $API_URL . '/api/v1/threads/' . $thread_id,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    
    if ($result) {
        $thread_data = json_decode($result, true);
        if ($thread_data && isset($thread_data['messages'])) {
            $thread_messages = $thread_data['messages'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🤖 المساعد الذكي - AI Chat</title>
    <style>
        :root {
            --primary: #6C63FF;
            --primary-dark: #5A52D5;
            --bg-dark: #0F0F1A;
            --bg-card: #1A1A2E;
            --bg-input: #16213E;
            --text-primary: #E8E8F0;
            --text-secondary: #A0A0B8;
            --accent-green: #00D68F;
            --accent-orange: #FF8C42;
            --border: #2A2A4A;
            --user-bubble: #6C63FF;
            --ai-bubble: #1E1E3A;
            --shadow: 0 8px 32px rgba(0,0,0,0.3);
        }

        * { margin:0; padding:0; box-sizing:border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
            background: var(--bg-dark);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, var(--bg-card), var(--primary-dark));
            padding: 16px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border);
            box-shadow: var(--shadow);
        }

        .header h1 {
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header .badge {
            background: var(--accent-green);
            color: #000;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: bold;
        }

        .header .thread-info {
            font-size: 0.75rem;
            color: var(--text-secondary);
            direction: ltr;
        }

        /* Chat Area */
        .chat-container {
            flex: 1;
            max-width: 900px;
            width: 100%;
            margin: 0 auto;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .message {
            display: flex;
            gap: 12px;
            max-width: 85%;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity:0; transform: translateY(10px); }
            to { opacity:1; transform: translateY(0); }
        }

        .message.user { align-self: flex-end; flex-direction: row-reverse; }
        .message.assistant { align-self: flex-start; }

        .avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .user .avatar { background: var(--user-bubble); }
        .assistant .avatar { background: var(--accent-green); color: #000; }

        .bubble {
            padding: 14px 18px;
            border-radius: 18px;
            line-height: 1.7;
            font-size: 0.95rem;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .user .bubble {
            background: var(--user-bubble);
            border-bottom-right-radius: 4px;
            color: #fff;
        }

        .assistant .bubble {
            background: var(--ai-bubble);
            border-bottom-left-radius: 4px;
            border: 1px solid var(--border);
        }

        /* Sources */
        .sources {
            margin-top: 12px;
            padding: 10px 14px;
            background: rgba(108, 99, 255, 0.1);
            border-radius: 10px;
            border: 1px solid rgba(108, 99, 255, 0.2);
            font-size: 0.8rem;
        }

        .sources-title {
            color: var(--primary);
            font-weight: bold;
            margin-bottom: 6px;
        }

        .source-item {
            padding: 4px 0;
            color: var(--text-secondary);
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .source-item:last-child { border-bottom: none; }

        .score-badge {
            display: inline-block;
            background: var(--primary);
            color: #fff;
            padding: 1px 6px;
            border-radius: 8px;
            font-size: 0.7rem;
            margin-left: 6px;
        }

        /* Metadata */
        .metadata {
            margin-top: 8px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            font-size: 0.72rem;
            color: var(--text-secondary);
        }

        .meta-item {
            background: rgba(255,255,255,0.05);
            padding: 2px 8px;
            border-radius: 6px;
        }

        /* Input Area */
        .input-area {
            background: var(--bg-card);
            border-top: 1px solid var(--border);
            padding: 16px 24px;
        }

        .input-form {
            max-width: 900px;
            margin: 0 auto;
            display: flex;
            gap: 12px;
            align-items: flex-end;
        }

        .input-wrapper {
            flex: 1;
            position: relative;
        }

        .input-wrapper textarea {
            width: 100%;
            background: var(--bg-input);
            border: 2px solid var(--border);
            border-radius: 14px;
            padding: 14px 18px;
            color: var(--text-primary);
            font-size: 1rem;
            font-family: inherit;
            resize: none;
            outline: none;
            transition: border-color 0.3s;
            min-height: 52px;
            max-height: 120px;
        }

        .input-wrapper textarea:focus {
            border-color: var(--primary);
        }

        .input-wrapper textarea::placeholder {
            color: var(--text-secondary);
        }

        .send-btn {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            width: 52px;
            height: 52px;
            border-radius: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: #fff;
            transition: transform 0.2s, box-shadow 0.2s;
            flex-shrink: 0;
        }

        .send-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 20px rgba(108, 99, 255, 0.4);
        }

        .send-btn:active { transform: scale(0.95); }

        /* Welcome */
        .welcome {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }

        .welcome .icon {
            font-size: 3.5rem;
            margin-bottom: 16px;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .welcome h2 {
            color: var(--text-primary);
            margin-bottom: 10px;
            font-size: 1.4rem;
        }

        .welcome p {
            max-width: 500px;
            margin: 0 auto 20px;
            line-height: 1.6;
        }

        .suggestions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
            margin-top: 16px;
        }

        .suggestion-btn {
            background: var(--bg-input);
            border: 1px solid var(--border);
            color: var(--text-primary);
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.2s;
        }

        .suggestion-btn:hover {
            border-color: var(--primary);
            background: rgba(108,99,255,0.1);
        }

        /* Error */
        .error-box {
            background: rgba(255, 70, 70, 0.1);
            border: 1px solid rgba(255, 70, 70, 0.3);
            color: #FF6B6B;
            padding: 12px 18px;
            border-radius: 12px;
            text-align: center;
            font-size: 0.9rem;
        }

        /* New Chat Button */
        .new-chat-btn {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text-secondary);
            padding: 6px 14px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.2s;
            text-decoration: none;
        }

        .new-chat-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        /* Responsive */
        @media (max-width: 600px) {
            .header { padding: 12px 16px; }
            .header h1 { font-size: 1rem; }
            .chat-container { padding: 12px; }
            .input-area { padding: 12px 16px; }
            .message { max-width: 95%; }
        }
    </style>
</head>
<body>

<div class="header">
    <h1>
        🤖 المساعد الذكي
        <span class="badge">RAG v1.0</span>
    </h1>
    <div style="display:flex; align-items:center; gap:12px;">
        <?php if (!empty($thread_id)): ?>
            <span class="thread-info">Thread: <?= htmlspecialchars(substr($thread_id, 0, 8)) ?>...</span>
        <?php endif; ?>
        <a href="?" class="new-chat-btn">+ محادثة جديدة</a>
    </div>
</div>

<div class="chat-container" id="chat">
    <?php if (empty($question) && empty($thread_messages)): ?>
        <!-- شاشة الترحيب -->
        <div class="welcome">
            <div class="icon">🤖</div>
            <h2>مرحباً بك في المساعد الذكي</h2>
            <p>اسألني أي سؤال وسأبحث لك في قاعدة المعرفة. أستطيع الإجابة على أسئلتك، تحليل الصور، والاحتفاظ بسياق المحادثة.</p>
            <div class="suggestions">
                <button class="suggestion-btn" onclick="askQuestion('ما هو الذكاء الاصطناعي؟')">ما هو الذكاء الاصطناعي؟</button>
                <button class="suggestion-btn" onclick="askQuestion('ما الفرق بين Machine Learning و Deep Learning؟')">ML vs DL</button>
                <button class="suggestion-btn" onclick="askQuestion('ما هو HTTP؟')">ما هو HTTP؟</button>
            </div>
        </div>
    <?php else: ?>
        <!-- عرض تاريخ المحادثة -->
        <?php foreach ($thread_messages as $msg): ?>
            <div class="message <?= htmlspecialchars($msg['role']) ?>">
                <div class="avatar">
                    <?= $msg['role'] === 'user' ? '👤' : '🤖' ?>
                </div>
                <div class="bubble"><?= nl2br(htmlspecialchars($msg['content'])) ?></div>
            </div>
        <?php endforeach; ?>

        <!-- السؤال الحالي -->
        <?php if (!empty($question)): ?>
            <div class="message user">
                <div class="avatar">👤</div>
                <div class="bubble"><?= nl2br(htmlspecialchars($question)) ?></div>
            </div>
        <?php endif; ?>

        <!-- الخطأ -->
        <?php if ($error_msg): ?>
            <div class="error-box">❌ <?= htmlspecialchars($error_msg) ?></div>
        <?php endif; ?>

        <!-- الإجابة -->
        <?php if ($response_data && isset($response_data['answer'])): ?>
            <div class="message assistant">
                <div class="avatar">🤖</div>
                <div>
                    <div class="bubble"><?= nl2br(htmlspecialchars($response_data['answer'])) ?></div>
                    
                    <?php if (!empty($response_data['sources'])): ?>
                        <div class="sources">
                            <div class="sources-title">📚 المصادر:</div>
                            <?php foreach ($response_data['sources'] as $i => $src): ?>
                                <div class="source-item">
                                    <?= ($i + 1) ?>. <?= htmlspecialchars(mb_substr($src['content_preview'] ?? '', 0, 100)) ?>...
                                    <?php if (isset($src['score'])): ?>
                                        <span class="score-badge"><?= round(($src['score'] ?? 0) * 100) ?>%</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($response_data['metadata'])): ?>
                        <div class="metadata">
                            <span class="meta-item">⚡ <?= $response_data['metadata']['latency_ms'] ?? 0 ?>ms</span>
                            <span class="meta-item">📝 <?= $response_data['metadata']['input_tokens'] ?? 0 ?> tokens</span>
                            <span class="meta-item">📊 <?= $response_data['metadata']['sources_found'] ?? 0 ?> مصادر</span>
                            <span class="meta-item">🧠 <?= $response_data['metadata']['model'] ?? 'local' ?></span>
                            <?php if ($response_data['metadata']['has_memory'] ?? false): ?>
                                <span class="meta-item">💾 ذاكرة</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<div class="input-area">
    <form method="POST" class="input-form" id="chatForm">
        <input type="hidden" name="thread_id" value="<?= htmlspecialchars($thread_id) ?>">
        <div class="input-wrapper">
            <textarea name="question" id="questionInput" 
                      placeholder="اكتب سؤالك هنا..." 
                      rows="1" required
                      onkeydown="handleKey(event)"
            ></textarea>
        </div>
        <button type="submit" class="send-btn" title="إرسال">➤</button>
    </form>
</div>

<script>
    // تمرير للأسفل
    const chat = document.getElementById('chat');
    chat.scrollTop = chat.scrollHeight;

    // إرسال بـ Enter
    function handleKey(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            document.getElementById('chatForm').submit();
        }
    }

    // أزرار الاقتراحات
    function askQuestion(q) {
        document.getElementById('questionInput').value = q;
        document.getElementById('chatForm').submit();
    }

    // تكبير textarea تلقائياً
    const textarea = document.getElementById('questionInput');
    textarea.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });
</script>

</body>
</html>