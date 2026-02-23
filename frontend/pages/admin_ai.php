<?php
/**
 * 🛠️ لوحة إدارة قاعدة المعرفة
 * إضافة/عرض/حذف البيانات في كل الجداول
 */
session_start();

$API_BASE = "http://127.0.0.1:8888";
$ctx = stream_context_create(['http' => ['timeout' => 5]]);

// ====== معالجة الأفعال ======
$flash = '';
$flash_type = 'ok';

// --- إضافة قاعدة معرفة ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_kb') {
    $ch = curl_init($API_BASE . '/api/v1/knowledge-bases');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'name' => $_POST['kb_name'],
            'description' => $_POST['kb_desc'] ?? '',
            'is_public' => isset($_POST['kb_public']),
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_TIMEOUT => 10,
    ]);
    $r = json_decode(curl_exec($ch), true);
    curl_close($ch);
    $flash = ($r && ($r['status'] ?? '') === 'ok') ? "✅ تم إنشاء قاعدة المعرفة" : "❌ خطأ: " . ($r['detail'] ?? 'غير معروف');
}

// --- إضافة مستند + تقطيع ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_doc') {
    $kb_id = $_POST['doc_kb_id'];
    $ch = curl_init($API_BASE . "/api/v1/knowledge-bases/{$kb_id}/documents");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'title' => $_POST['doc_title'],
            'content' => $_POST['doc_content'],
            'language' => $_POST['doc_lang'] ?? 'ar',
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_TIMEOUT => 30,
    ]);
    $r = json_decode(curl_exec($ch), true);
    curl_close($ch);
    $flash = ($r && ($r['status'] ?? '') === 'ok')
        ? "✅ تم إضافة المستند — {$r['chunks_created']} قطعة أُنشئت"
        : "❌ خطأ: " . ($r['detail'] ?? 'غير معروف');
}

// --- إضافة قطعة مباشرة ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_chunk') {
    $chunk_id = 'chunk-' . substr(uniqid(), -8) . '-uuid';
    $doc_id = $_POST['chunk_doc_id'] ?: 'doc-001-uuid';
    $content = $_POST['chunk_content'];
    $lang = $_POST['chunk_lang'] ?? 'ar';
    $tokens = str_word_count($content);

    // مباشرة إلى قاعدة البيانات عبر health trick - أو عبر API مخصص
    // نستخدم curl لإضافة مستند بمحتوى واحد
    $ch = curl_init($API_BASE . "/api/v1/chunks/add");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'id' => $chunk_id,
            'document_id' => $doc_id,
            'content' => $content,
            'language' => $lang,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_TIMEOUT => 10,
    ]);
    $r = json_decode(curl_exec($ch), true);
    curl_close($ch);
    $flash = ($r && ($r['status'] ?? '') === 'ok')
        ? "✅ تم إضافة القطعة"
        : "❌ خطأ: " . ($r['detail'] ?? 'غير معروف');
}

// --- رفع ملف ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_file') {
    if (!empty($_FILES['file_upload']['tmp_name'])) {
        $ch = curl_init($API_BASE . '/api/v1/files/upload');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'file' => new CURLFile(
                    $_FILES['file_upload']['tmp_name'],
                    $_FILES['file_upload']['type'],
                    $_FILES['file_upload']['name']
                ),
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
        ]);
        $r = json_decode(curl_exec($ch), true);
        curl_close($ch);
        $flash = ($r && ($r['status'] ?? '') === 'ok')
            ? "✅ تم رفع الملف: {$r['filename']}"
            : "❌ خطأ في رفع الملف";
    } else {
        $flash = "❌ الرجاء اختيار ملف";
        $flash_type = 'err';
    }
}

// --- إرسال تقييم ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_feedback') {
    $ch = curl_init($API_BASE . '/api/v1/feedback');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'message_id' => $_POST['fb_message_id'],
            'rating' => (int)$_POST['fb_rating'],
            'comment' => $_POST['fb_comment'] ?? '',
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_TIMEOUT => 10,
    ]);
    $r = json_decode(curl_exec($ch), true);
    curl_close($ch);
    $flash = ($r && ($r['status'] ?? '') === 'ok') ? "✅ شكراً لتقييمك!" : "❌ خطأ";
}

// ====== جلب البيانات ======
$kbs = json_decode(@file_get_contents($API_BASE . '/api/v1/knowledge-bases', false, $ctx), true);
$kbs = ($kbs && isset($kbs['knowledge_bases'])) ? $kbs['knowledge_bases'] : [];

$files_data = json_decode(@file_get_contents($API_BASE . '/api/v1/files', false, $ctx), true);
$files_list = ($files_data && isset($files_data['files'])) ? $files_data['files'] : [];

$feedback_data = json_decode(@file_get_contents($API_BASE . '/api/v1/feedback', false, $ctx), true);
$feedbacks = ($feedback_data && isset($feedback_data['feedbacks'])) ? $feedback_data['feedbacks'] : [];
$avg_rating = $feedback_data['average_rating'] ?? 0;

$threads_data = json_decode(@file_get_contents($API_BASE . '/api/v1/threads?limit=10', false, $ctx), true);
$threads = ($threads_data && isset($threads_data['threads'])) ? $threads_data['threads'] : [];

$health_data = json_decode(@file_get_contents($API_BASE . '/api/v1/health', false, $ctx), true);
$chunks_count = $health_data['total_chunks_found'] ?? 0;
$sample_chunks = $health_data['sample_chunks'] ?? [];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🛠️ لوحة الإدارة — AI Engine</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        :root{
            --bg:#090b10;--bg2:#0f1218;--card:#151921;--card2:#1a1f2b;
            --brd:#252a36;--brd2:#363d4e;
            --text:#e4e8f1;--text2:#8892a6;--text3:#5d6577;
            --accent:#7c6aff;--accent2:#6555e0;
            --green:#2dd4a0;--red:#ff5c6a;--orange:#ffa94d;--blue:#5eaeff;
            --radius:12px;
        }
        body{font-family:'Tajawal',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;padding:0}

        .topbar{
            background:var(--card);border-bottom:1px solid var(--brd);
            padding:14px 24px;display:flex;align-items:center;justify-content:space-between;
            position:sticky;top:0;z-index:100;
        }
        .topbar h1{font-size:1.2rem;display:flex;align-items:center;gap:8px}
        .topbar .links{display:flex;gap:8px}
        .topbar .links a{
            color:var(--text2);text-decoration:none;padding:5px 12px;
            border:1px solid var(--brd);border-radius:8px;font-size:.78rem;transition:all .2s;
        }
        .topbar .links a:hover{color:var(--accent);border-color:var(--accent)}

        .container{max-width:1100px;margin:0 auto;padding:20px}

        /* Stats */
        .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:24px}
        .stat{
            background:var(--card);border:1px solid var(--brd);border-radius:var(--radius);
            padding:16px;text-align:center;
        }
        .stat .num{font-size:1.6rem;font-weight:700;color:var(--accent)}
        .stat .label{font-size:.78rem;color:var(--text2);margin-top:4px}

        /* Tabs */
        .tabs{display:flex;gap:6px;margin-bottom:20px;flex-wrap:wrap}
        .tab{
            padding:8px 18px;border-radius:8px;cursor:pointer;font-size:.85rem;
            background:var(--card);border:1px solid var(--brd);color:var(--text2);
            transition:all .2s;font-family:inherit;
        }
        .tab.active,.tab:hover{background:var(--accent);color:#fff;border-color:var(--accent)}

        .panel{display:none}
        .panel.active{display:block}

        /* Card */
        .card{
            background:var(--card);border:1px solid var(--brd);border-radius:var(--radius);
            padding:20px;margin-bottom:16px;
        }
        .card h3{font-size:1rem;margin-bottom:14px;display:flex;align-items:center;gap:6px}

        /* Forms */
        .form-group{margin-bottom:12px}
        .form-group label{display:block;font-size:.82rem;color:var(--text2);margin-bottom:4px}
        input[type="text"],input[type="number"],textarea,select{
            width:100%;background:var(--bg2);border:1px solid var(--brd);border-radius:8px;
            padding:10px 14px;color:var(--text);font-size:.9rem;font-family:inherit;
            outline:none;transition:border-color .2s;
        }
        input:focus,textarea:focus,select:focus{border-color:var(--accent)}
        textarea{min-height:100px;resize:vertical}
        .checkbox-row{display:flex;align-items:center;gap:8px;font-size:.85rem}
        .checkbox-row input[type="checkbox"]{width:16px;height:16px}

        .btn{
            display:inline-flex;align-items:center;gap:6px;
            padding:10px 20px;border-radius:8px;border:none;cursor:pointer;
            font-size:.88rem;font-weight:600;font-family:inherit;transition:all .2s;
        }
        .btn-primary{background:linear-gradient(135deg,#7c6aff,#5a45e0);color:#fff}
        .btn-primary:hover{transform:translateY(-1px);box-shadow:0 4px 16px rgba(124,106,255,.3)}
        .btn-green{background:var(--green);color:#000}
        .btn-sm{padding:6px 12px;font-size:.78rem;border-radius:6px}

        /* Tables */
        table{width:100%;border-collapse:collapse;font-size:.82rem;margin-top:10px}
        th,td{padding:10px 12px;text-align:right;border-bottom:1px solid var(--brd)}
        th{background:rgba(255,255,255,.03);color:var(--text2);font-size:.75rem;font-weight:600}
        tr:hover td{background:rgba(255,255,255,.02)}
        .id-cell{font-family:monospace;font-size:.72rem;color:var(--text3);max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .content-cell{max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

        /* Flash */
        .flash{
            padding:12px 18px;border-radius:8px;margin-bottom:16px;font-size:.88rem;
            animation:fadeIn .3s ease;
        }
        .flash.ok{background:rgba(45,212,160,.1);border:1px solid rgba(45,212,160,.2);color:var(--green)}
        .flash.err{background:rgba(255,92,106,.1);border:1px solid rgba(255,92,106,.2);color:var(--red)}
        @keyframes fadeIn{from{opacity:0;transform:translateY(-5px)}to{opacity:1;transform:translateY(0)}}

        /* Stars */
        .stars{color:var(--orange);font-size:1.1rem;letter-spacing:2px}

        /* File input */
        .file-input-wrap{position:relative}
        .file-input-wrap input[type="file"]{
            width:100%;padding:10px;background:var(--bg2);border:1px dashed var(--brd2);
            border-radius:8px;color:var(--text);font-family:inherit;cursor:pointer;
        }

        .row-2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        @media(max-width:640px){.row-2{grid-template-columns:1fr}.stats{grid-template-columns:repeat(2,1fr)}}
    </style>
</head>
<body>

<div class="topbar">
    <h1>🛠️ لوحة إدارة AI Engine</h1>
    <div class="links">
        <a href="test_api.php">💬 الدردشة</a>
        <a href="http://hcsfcs.top:8888/docs" target="_blank">📖 API Docs</a>
        <a href="?">🔄 تحديث</a>
    </div>
</div>

<div class="container">

    <?php if ($flash): ?>
        <div class="flash <?= strpos($flash, '❌') !== false ? 'err' : 'ok' ?>"><?= $flash ?></div>
    <?php endif; ?>

    <!-- ====== إحصائيات ====== -->
    <div class="stats">
        <div class="stat"><div class="num"><?= count($kbs) ?></div><div class="label">قواعد معرفة</div></div>
        <div class="stat"><div class="num"><?= $chunks_count ?></div><div class="label">قطع نصية</div></div>
        <div class="stat"><div class="num"><?= count($files_list) ?></div><div class="label">ملفات</div></div>
        <div class="stat"><div class="num"><?= count($threads) ?></div><div class="label">محادثات</div></div>
        <div class="stat"><div class="num"><?= count($feedbacks) ?></div><div class="label">تقييمات</div></div>
        <div class="stat"><div class="num"><?= $avg_rating ?></div><div class="label">⭐ متوسط التقييم</div></div>
    </div>

    <!-- ====== تبويبات ====== -->
    <div class="tabs">
        <button class="tab active" onclick="showPanel('kb')">📚 قواعد المعرفة</button>
        <button class="tab" onclick="showPanel('docs')">📄 مستندات + قطع</button>
        <button class="tab" onclick="showPanel('files')">📁 ملفات</button>
        <button class="tab" onclick="showPanel('feedback')">⭐ تقييمات</button>
        <button class="tab" onclick="showPanel('threads')">💬 محادثات</button>
        <button class="tab" onclick="showPanel('chunks')">🔍 القطع النصية</button>
    </div>

    <!-- ====== 1. قواعد المعرفة ====== -->
    <div class="panel active" id="panel-kb">
        <div class="row-2">
            <div class="card">
                <h3>➕ إنشاء قاعدة معرفة جديدة</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_kb">
                    <div class="form-group">
                        <label>اسم القاعدة *</label>
                        <input type="text" name="kb_name" placeholder="مثال: أسئلة تقنية" required>
                    </div>
                    <div class="form-group">
                        <label>الوصف</label>
                        <input type="text" name="kb_desc" placeholder="وصف اختياري...">
                    </div>
                    <div class="form-group">
                        <div class="checkbox-row">
                            <input type="checkbox" name="kb_public" id="kb_public" checked>
                            <label for="kb_public">عامة (يمكن للجميع البحث فيها)</label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">➕ إنشاء</button>
                </form>
            </div>
            <div class="card">
                <h3>📋 القواعد الموجودة</h3>
                <?php if (empty($kbs)): ?>
                    <p style="color:var(--text3)">لا توجد قواعد بعد</p>
                <?php else: ?>
                    <table>
                        <tr><th>ID</th><th>الاسم</th><th>الوصف</th><th>عامة</th></tr>
                        <?php foreach ($kbs as $kb): ?>
                            <tr>
                                <td class="id-cell"><?= htmlspecialchars($kb['id'] ?? '') ?></td>
                                <td><strong><?= htmlspecialchars($kb['name'] ?? '') ?></strong></td>
                                <td class="content-cell"><?= htmlspecialchars($kb['description'] ?? '-') ?></td>
                                <td><?= ($kb['is_public'] ?? 0) ? '✅' : '❌' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ====== 2. مستندات + قطع ====== -->
    <div class="panel" id="panel-docs">
        <div class="row-2">
            <div class="card">
                <h3>📄 إضافة مستند (يتم تقطيعه تلقائياً)</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_doc">
                    <div class="form-group">
                        <label>قاعدة المعرفة *</label>
                        <select name="doc_kb_id" required>
                            <option value="">اختر...</option>
                            <?php foreach ($kbs as $kb): ?>
                                <option value="<?= htmlspecialchars($kb['id']) ?>">
                                    <?= htmlspecialchars($kb['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>عنوان المستند</label>
                        <input type="text" name="doc_title" placeholder="مثال: أسئلة عن البرمجة">
                    </div>
                    <div class="form-group">
                        <label>اللغة</label>
                        <select name="doc_lang"><option value="ar">عربي</option><option value="en">English</option></select>
                    </div>
                    <div class="form-group">
                        <label>المحتوى * (يقبل نص عادي أو نمط سؤال/جواب)</label>
                        <textarea name="doc_content" rows="8" required
                            placeholder="سؤال: ما هو Python؟
جواب: Python هي لغة برمجة عالية المستوى سهلة التعلم...

سؤال: ما هو JavaScript؟
جواب: JavaScript هي لغة برمجة تُستخدم بشكل أساسي لتطوير الويب..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">📄 إضافة وتقطيع</button>
                </form>
            </div>
            <div class="card">
                <h3>✏️ إضافة قطعة نصية مباشرة</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_chunk">
                    <div class="form-group">
                        <label>معرف المستند</label>
                        <input type="text" name="chunk_doc_id" placeholder="doc-001-uuid (اختياري)" value="doc-001-uuid">
                    </div>
                    <div class="form-group">
                        <label>اللغة</label>
                        <select name="chunk_lang"><option value="ar">عربي</option><option value="en">English</option></select>
                    </div>
                    <div class="form-group">
                        <label>المحتوى *</label>
                        <textarea name="chunk_content" rows="5" required
                            placeholder="سؤال: ما هي الحوسبة السحابية؟
جواب: الحوسبة السحابية هي تقديم خدمات الحوسبة عبر الإنترنت..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-green">✏️ إضافة قطعة</button>
                </form>
            </div>
        </div>
    </div>

    <!-- ====== 3. ملفات ====== -->
    <div class="panel" id="panel-files">
        <div class="row-2">
            <div class="card">
                <h3>📁 رفع ملف</h3>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_file">
                    <div class="form-group">
                        <label>اختر ملف (TXT, PDF, صورة)</label>
                        <div class="file-input-wrap">
                            <input type="file" name="file_upload" accept=".txt,.pdf,.doc,.docx,.csv,.jpg,.jpeg,.png,.gif" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">📤 رفع</button>
                </form>
            </div>
            <div class="card">
                <h3>📋 الملفات المرفوعة</h3>
                <?php if (empty($files_list)): ?>
                    <p style="color:var(--text3)">لا توجد ملفات</p>
                <?php else: ?>
                    <table>
                        <tr><th>الاسم</th><th>النوع</th><th>الحجم</th><th>التاريخ</th></tr>
                        <?php foreach ($files_list as $f): ?>
                            <tr>
                                <td><?= htmlspecialchars($f['filename'] ?? '') ?></td>
                                <td><?= htmlspecialchars($f['mime_type'] ?? '') ?></td>
                                <td><?= number_format(($f['file_size'] ?? 0) / 1024, 1) ?> KB</td>
                                <td style="font-size:.72rem"><?= htmlspecialchars($f['created_at'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ====== 4. تقييمات ====== -->
    <div class="panel" id="panel-feedback">
        <div class="row-2">
            <div class="card">
                <h3>⭐ إرسال تقييم</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_feedback">
                    <div class="form-group">
                        <label>معرف الرسالة *</label>
                        <input type="text" name="fb_message_id" placeholder="msg-xxx-uuid" required>
                    </div>
                    <div class="form-group">
                        <label>التقييم * (1-5)</label>
                        <select name="fb_rating" required>
                            <option value="5">⭐⭐⭐⭐⭐ ممتاز</option>
                            <option value="4">⭐⭐⭐⭐ جيد جداً</option>
                            <option value="3">⭐⭐⭐ جيد</option>
                            <option value="2">⭐⭐ مقبول</option>
                            <option value="1">⭐ ضعيف</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>تعليق</label>
                        <input type="text" name="fb_comment" placeholder="تعليق اختياري...">
                    </div>
                    <button type="submit" class="btn btn-primary">⭐ إرسال</button>
                </form>
            </div>
            <div class="card">
                <h3>📊 التقييمات (متوسط: <?= $avg_rating ?> ⭐)</h3>
                <?php if (empty($feedbacks)): ?>
                    <p style="color:var(--text3)">لا توجد تقييمات</p>
                <?php else: ?>
                    <table>
                        <tr><th>الرسالة</th><th>التقييم</th><th>التعليق</th><th>التاريخ</th></tr>
                        <?php foreach ($feedbacks as $fb): ?>
                            <tr>
                                <td class="id-cell"><?= htmlspecialchars($fb['message_id'] ?? '') ?></td>
                                <td><span class="stars"><?= str_repeat('⭐', $fb['rating'] ?? 0) ?></span></td>
                                <td><?= htmlspecialchars($fb['comment'] ?? '-') ?></td>
                                <td style="font-size:.72rem"><?= htmlspecialchars($fb['created_at'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ====== 5. محادثات ====== -->
    <div class="panel" id="panel-threads">
        <div class="card">
            <h3>💬 آخر المحادثات</h3>
            <?php if (empty($threads)): ?>
                <p style="color:var(--text3)">لا توجد محادثات</p>
            <?php else: ?>
                <table>
                    <tr><th>ID</th><th>العنوان</th><th>التاريخ</th></tr>
                    <?php foreach ($threads as $t): ?>
                        <tr>
                            <td class="id-cell"><?= htmlspecialchars($t['id'] ?? '') ?></td>
                            <td><strong><?= htmlspecialchars($t['title'] ?? 'بدون عنوان') ?></strong></td>
                            <td style="font-size:.72rem"><?= htmlspecialchars($t['created_at'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- ====== 6. القطع النصية ====== -->
    <div class="panel" id="panel-chunks">
        <div class="card">
            <h3>🔍 القطع النصية في قاعدة البيانات (<?= $chunks_count ?> قطعة)</h3>
            <?php if (empty($sample_chunks)): ?>
                <p style="color:var(--text3)">لا توجد قطع</p>
            <?php else: ?>
                <table>
                    <tr><th>ID</th><th>المحتوى</th><th>اللغة</th><th>كلمات</th></tr>
                    <?php foreach ($sample_chunks as $ch): ?>
                        <tr>
                            <td class="id-cell"><?= htmlspecialchars($ch['id'] ?? '') ?></td>
                            <td class="content-cell"><?= htmlspecialchars(mb_substr($ch['content'] ?? '', 0, 100)) ?></td>
                            <td><?= htmlspecialchars($ch['language'] ?? 'ar') ?></td>
                            <td><?= htmlspecialchars($ch['token_count'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
function showPanel(name) {
    document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.getElementById('panel-' + name).classList.add('active');
    event.target.classList.add('active');
}
</script>

</body>
</html>
