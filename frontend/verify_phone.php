<?php
declare(strict_types=1);
/**
 * frontend/verify_phone.php
 *
 * Phone verification landing page.
 * Opened automatically when the user clicks the SMS activation link.
 *
 * Two modes:
 *   ?t=RAW_TOKEN          — Fresh activation attempt (from SMS link)
 *   ?status=success       — Already activated; show success message
 *   ?status=error&msg=…   — Activation failed; show error message
 */

if (session_status() === PHP_SESSION_NONE) {
    $__sharedSess = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/api/shared/config/session.php';
    if (file_exists($__sharedSess)) {
        require_once $__sharedSess;
    } else {
        session_name('APP_SESSID');
        session_start();
    }
    unset($__sharedSess);
}

ini_set('display_errors', '0');

$status   = trim($_GET['status']  ?? '');
$rawMsg   = trim($_GET['msg']     ?? '');
$rawToken = trim($_GET['t']       ?? '');
$waiting  = !empty($_GET['waiting']);
$rawPhone = trim($_GET['phone']   ?? '');
// Sanitise phone for display only (never trusted for auth)
$displayPhone = preg_replace('/[^\d+]/', '', $rawPhone);
$displayPhone = htmlspecialchars(substr($displayPhone, 0, 20), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

// If we already have a status, just render the result page
$autoVerify = ($rawToken !== '' && $status === '');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفعيل الحساب</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
            background: #f0f4f8;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,.10);
            padding: 48px 40px;
            max-width: 420px;
            width: 100%;
            text-align: center;
        }
        .icon { font-size: 64px; margin-bottom: 20px; line-height: 1; }
        h1 { font-size: 22px; margin-bottom: 10px; color: #1a1a2e; }
        p  { font-size: 15px; color: #555; line-height: 1.7; }
        .spinner {
            width: 52px; height: 52px;
            border: 5px solid #e2e8f0;
            border-top-color: #3b82f6;
            border-radius: 50%;
            animation: spin .85s linear infinite;
            margin: 0 auto 24px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .btn {
            display: inline-block;
            margin-top: 24px;
            padding: 12px 28px;
            background: #3b82f6;
            color: #fff;
            border-radius: 8px;
            text-decoration: none;
            font-size: 15px;
            font-weight: 600;
        }
        .btn:hover { background: #2563eb; }
        .err { color: #dc2626; }
        .btn-resend {
            background: none;
            border: 2px solid #3b82f6;
            color: #3b82f6;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }
        .btn-resend:hover { background: #eff6ff; }
        .btn-resend:disabled { opacity: .5; cursor: not-allowed; }
        #resendMsg { font-size: 13px; margin-top: 10px; }
    </style>
</head>
<body>
<div class="card" id="card">

<?php if ($status === 'success'): ?>
    <div class="icon">✅</div>
    <h1>تم تفعيل حسابك بنجاح!</h1>
    <p>مرحباً بك. يمكنك الآن تسجيل الدخول والاستمتاع بخدماتنا.</p>
    <a href="/login" class="btn">تسجيل الدخول</a>

<?php elseif ($status === 'error'): ?>
    <div class="icon">❌</div>
    <h1 class="err">فشل التفعيل</h1>
    <p class="err"><?= htmlspecialchars($rawMsg ?: 'حدث خطأ غير متوقع.') ?></p>
    <a href="/register" class="btn" style="background:#6b7280">العودة للتسجيل</a>

<?php elseif ($autoVerify): ?>
    <div class="spinner" id="spinner"></div>
    <h1 id="titleMsg">جاري التحقق…</h1>
    <p id="bodyMsg">يرجى الانتظار بينما نتحقق من هويتك.</p>

<?php elseif ($waiting): ?>
    <div class="icon">📱</div>
    <h1>تحقق من رسائل SMS</h1>
    <?php if ($displayPhone !== ''): ?>
    <p style="font-size:1rem;font-weight:700;color:#1a1a2e;margin:8px 0 4px;"><?= $displayPhone ?></p>
    <?php endif; ?>
    <p>تم إنشاء حسابك! أرسلنا رابط التفعيل إلى رقمك. افتح الرابط على نفس الجهاز لتفعيل حسابك.</p>
    <div style="background:#f0faf3;border:1px solid #c3e6cb;border-radius:10px;padding:14px;margin:18px 0;font-size:.87rem;color:#155724;text-align:right;">
        <strong>📌 خطوات التفعيل:</strong>
        <ol style="margin:8px 0 0;padding-right:18px;">
            <li>افتح الرسالة النصية الواردة على رقمك</li>
            <li>اضغط رابط التفعيل <strong>على نفس الجهاز</strong></li>
            <li>سيتم تفعيل حسابك تلقائياً</li>
        </ol>
    </div>
    <button id="btnResend" class="btn btn-resend" style="display:inline-block;margin-top:8px;padding:10px 24px;border-radius:8px;">
        🔁 إعادة إرسال رسالة التفعيل
    </button>
    <div id="resendMsg"></div>
    <a href="/register" class="btn" style="background:#6b7280;margin-top:12px;display:inline-block;">العودة للتسجيل</a>

<?php else: ?>
    <div class="icon">🔗</div>
    <h1>رابط التفعيل</h1>
    <p>لم يتم التعرف على رابط التفعيل. يرجى فتح الرابط المرسل عبر SMS مرة أخرى.</p>
    <a href="/register" class="btn" style="background:#6b7280">إعادة التسجيل</a>

<?php endif; ?>

</div>

<?php if ($autoVerify): ?>
<script>
(function () {
    'use strict';
    const token = <?= json_encode($rawToken, JSON_UNESCAPED_UNICODE) ?>;

    async function activate() {
        let data;
        try {
            const res = await fetch('/api/verify_phone', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ token: token })
            });
            data = await res.json();
        } catch (e) {
            showResult(false, 'تعذّر الاتصال بالخادم. يرجى المحاولة مجدداً.');
            return;
        }

        if (data && data.ok) {
            showResult(true);
            setTimeout(() => { window.location.href = '/'; }, 1800);
        } else {
            showResult(false, data.error || 'فشل التفعيل');
        }
    }

    function showResult(success, errMsg) {
        document.getElementById('spinner').style.display = 'none';
        const title = document.getElementById('titleMsg');
        const body  = document.getElementById('bodyMsg');
        if (success) {
            document.getElementById('card').insertAdjacentHTML(
                'afterbegin', '<div class="icon">✅</div>'
            );
            title.textContent = 'تم تفعيل حسابك بنجاح!';
            body.textContent  = 'مرحباً بك. جاري تحويلك…';
        } else {
            document.getElementById('card').insertAdjacentHTML(
                'afterbegin', '<div class="icon">❌</div>'
            );
            title.textContent = 'فشل التفعيل';
            title.className   = 'err';
            // Use textContent for the message to avoid XSS
            const errSpan = document.createElement('span');
            errSpan.className = 'err';
            errSpan.textContent = errMsg;
            const backLink = document.createElement('a');
            backLink.href = '/register';
            backLink.className = 'btn';
            backLink.style.background = '#6b7280';
            backLink.style.marginTop  = '16px';
            backLink.textContent = 'إعادة التسجيل';
            body.textContent = '';
            body.appendChild(errSpan);
            body.appendChild(document.createElement('br'));
            body.appendChild(backLink);
        }
    }

    activate();
})();
</script>
<?php endif; ?>

<?php if ($waiting): ?>
<script>
(function () {
    'use strict';
    const btn = document.getElementById('btnResend');
    const msg = document.getElementById('resendMsg');
    if (!btn) return;

    let cooldown = 0;
    let timer = null;

    function setCooldown(secs) {
        cooldown = secs;
        btn.disabled = true;
        clearInterval(timer);
        timer = setInterval(function () {
            cooldown--;
            if (cooldown <= 0) {
                clearInterval(timer);
                btn.disabled = false;
                btn.textContent = '🔁 إعادة إرسال رسالة التفعيل';
            } else {
                btn.textContent = '⏳ إعادة الإرسال بعد ' + cooldown + 'ث';
            }
        }, 1000);
    }

    btn.addEventListener('click', async function () {
        msg.textContent = '';
        msg.style.color = '';
        btn.disabled = true;
        btn.textContent = '⏳ جارٍ الإرسال…';

        let data;
        try {
            const res = await fetch('/api/auth', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'resend_verification' })
            });
            data = await res.json();
        } catch (e) {
            msg.textContent = 'تعذّر الاتصال بالخادم. يرجى المحاولة مجدداً.';
            msg.style.color = '#dc2626';
            btn.disabled = false;
            btn.textContent = '🔁 إعادة إرسال رسالة التفعيل';
            return;
        }

        if (data && data.ok) {
            msg.textContent = '✅ تم إرسال رسالة التفعيل بنجاح!';
            msg.style.color = '#155724';
            setCooldown(60);
        } else {
            msg.textContent = '❌ ' + (data.error || 'فشل إرسال الرسالة. يرجى المحاولة مجدداً.');
            msg.style.color = '#dc2626';
            btn.disabled = false;
            btn.textContent = '🔁 إعادة إرسال رسالة التفعيل';
        }
    });
})();
</script>
<?php endif; ?>

</body>
</html>
