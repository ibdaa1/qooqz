<?php
declare(strict_types=1);

/**
 * frontend/register.php
 * QOOQZ — Registration with WhatsApp Number Verification
 *
 * Single-page flow:
 *   Step 1 — Fill registration form (username, email, password, phone, lang, country, city)
 *   Step 2 — Generate a WhatsApp verification link (session-bound token)
 *   Step 3 — Copy / open WhatsApp link; countdown until expiry
 *
 * Uses the same shared session config as the API so that the session cookie
 * (APP_SESSID) is shared between this page and all /api/* endpoints.
 */

if (session_status() === PHP_SESSION_NONE) {
    $__sharedSess = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/api/shared/config/session.php';
    if (file_exists($__sharedSess)) {
        require_once $__sharedSess;
    } else {
        $__sp = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/api/storage/sessions';
        session_name('APP_SESSID');
        if (is_dir($__sp)) ini_set('session.save_path', $__sp);
        session_start();
    }
    unset($__sharedSess, $__sp);
}

ini_set('display_errors', '0');
error_reporting(E_ALL);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

// Language / direction detection
$lang   = $_SESSION['pub_lang'] ?? $_SESSION['user']['preferred_language'] ?? 'ar';
$dir    = in_array($lang, ['ar', 'fa', 'ur', 'he'], true) ? 'rtl' : 'ltr';
$isRtl  = $dir === 'rtl';

// If already logged in, redirect to home
if (!empty($_SESSION['user']['id'])) {
    header('Location: /frontend/public/index.php');
    exit;
}
?>
<!doctype html>
<html lang="<?= htmlspecialchars($lang) ?>" dir="<?= $dir ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>QOOQZ — <?= $isRtl ? 'إنشاء حساب عبر واتساب' : 'Register via WhatsApp' ?></title>
    <?php if ($isRtl): ?>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?php else: ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?php endif; ?>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --wa:   #25D366;
            --wa-dk:#128C7E;
            --pri:  #0b6f00;
            --gray: #6c757d;
            --err:  #721c24;
            --err-bg:#f8d7da;
            --ok:   #155724;
            --ok-bg:#d4edda;
            --warn-bg:#fff3cd;
            --warn: #856404;
            --radius:10px;
            --shadow:0 4px 24px rgba(0,0,0,.12);
        }

        body {
            font-family: <?= $isRtl ? "'Cairo'" : "'Inter'" ?>, system-ui, Arial, sans-serif;
            background: linear-gradient(135deg, var(--wa) 0%, var(--wa-dk) 100%);
            min-height: 100vh;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 32px 16px 48px;
        }

        .rw-card {
            background: #fff;
            border-radius: 18px;
            box-shadow: var(--shadow);
            width: 100%;
            max-width: 520px;
            overflow: hidden;
        }

        /* ── Header ── */
        .rw-header {
            background: var(--wa);
            padding: 24px 28px 20px;
            text-align: center;
            color: #fff;
        }
        .rw-header .rw-logo { font-size: 2.2rem; margin-bottom: 4px; }
        .rw-header h1 { font-size: 1.35rem; font-weight: 700; }
        .rw-header p  { font-size: .85rem; opacity: .9; margin-top: 4px; }

        /* ── Steps indicator ── */
        .rw-steps {
            display: flex;
            justify-content: center;
            gap: 0;
            background: #f0faf3;
            padding: 14px 0 10px;
            border-bottom: 1px solid #e0f0e6;
        }
        .rw-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
            position: relative;
            font-size: .75rem;
            color: #aaa;
        }
        .rw-step::after {
            content: '';
            position: absolute;
            top: 13px;
            <?= $isRtl ? 'left' : 'right' ?>: 0;
            width: 50%;
            height: 2px;
            background: #ddd;
        }
        .rw-step:last-child::after { display: none; }
        .rw-step-num {
            width: 28px; height: 28px;
            border-radius: 50%;
            background: #ddd;
            color: #888;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700;
            font-size: .8rem;
            margin-bottom: 4px;
            transition: background .3s, color .3s;
        }
        .rw-step.active .rw-step-num  { background: var(--wa); color: #fff; }
        .rw-step.done  .rw-step-num   { background: var(--pri); color: #fff; }
        .rw-step.active, .rw-step.done { color: #333; }

        /* ── Body ── */
        .rw-body { padding: 28px; }

        /* ── Form fields ── */
        .rw-field { margin-bottom: 16px; }
        .rw-field label {
            display: block;
            font-size: .82rem;
            font-weight: 600;
            color: #444;
            margin-bottom: 5px;
        }
        .rw-field input,
        .rw-field select {
            width: 100%;
            padding: 10px 12px;
            border: 1.5px solid #ccc;
            border-radius: var(--radius);
            font-size: .95rem;
            font-family: inherit;
            transition: border-color .2s;
            background: #fafafa;
        }
        .rw-field input:focus,
        .rw-field select:focus {
            outline: none;
            border-color: var(--wa);
            background: #fff;
        }
        .rw-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

        /* ── Buttons ── */
        .rw-btn {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            width: 100%;
            padding: 12px 16px;
            border: none;
            border-radius: var(--radius);
            font-size: .95rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: filter .2s, transform .1s;
        }
        .rw-btn:active { transform: scale(.98); }
        .rw-btn:hover  { filter: brightness(1.07); }
        .rw-btn-wa    { background: var(--wa);   color: #fff; }
        .rw-btn-pri   { background: var(--pri);  color: #fff; }
        .rw-btn-gray  { background: var(--gray); color: #fff; }
        .rw-btn-out   { background: transparent; color: var(--gray); border: 1.5px solid var(--gray); width: auto; padding: 8px 14px; }
        .rw-btn-sm    { font-size: .82rem; padding: 8px 14px; width: auto; }
        .rw-btn-row   { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 14px; }

        /* ── Alerts ── */
        .rw-alert {
            padding: 12px 14px;
            border-radius: var(--radius);
            font-size: .88rem;
            margin-top: 14px;
            display: none;
        }
        .rw-alert.ok   { background: var(--ok-bg);   color: var(--ok);   display: block; }
        .rw-alert.err  { background: var(--err-bg);  color: var(--err);  display: block; }
        .rw-alert.info { background: #e3f2fd;         color: #1565c0;     display: block; }
        .rw-alert.warn { background: var(--warn-bg);  color: var(--warn); display: block; }

        /* ── Link box ── */
        .rw-link-box {
            background: #f8f9fa;
            border: 2px dashed #25D366;
            border-radius: var(--radius);
            padding: 14px;
            margin-top: 16px;
            word-break: break-all;
            font-size: .85rem;
            color: #333;
        }
        .rw-link-box a { color: #0d6efd; text-decoration: none; }
        .rw-link-box a:hover { text-decoration: underline; }

        /* ── Message preview ── */
        .rw-msg-preview {
            width: 100%;
            margin-top: 10px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: monospace;
            font-size: .82rem;
            resize: none;
            background: #fff;
        }

        /* ── Countdown ── */
        .rw-timer {
            text-align: center;
            padding: 14px;
            background: var(--warn-bg);
            border-radius: var(--radius);
            margin-top: 16px;
        }
        .rw-timer .rw-clock {
            font-size: 2rem;
            font-weight: 700;
            color: #c0392b;
            font-variant-numeric: tabular-nums;
        }
        .rw-timer p { font-size: .8rem; color: var(--warn); margin-top: 4px; }

        /* ── Action cards (copy / open WA) ── */
        .rw-actions {
            display: grid;
            grid-template-columns: repeat(3,1fr);
            gap: 8px;
            margin-top: 14px;
        }
        .rw-action-btn {
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            gap: 4px;
            padding: 12px 8px;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            font-family: inherit;
            font-size: .78rem;
            font-weight: 600;
            transition: filter .2s, transform .1s;
        }
        .rw-action-btn:active  { transform: scale(.97); }
        .rw-action-btn:hover   { filter: brightness(1.08); }
        .rw-action-btn .icon   { font-size: 1.4rem; }
        .rw-action-wa  { background: var(--wa);  color: #fff; }
        .rw-action-cp  { background: #28a745;    color: #fff; }
        .rw-action-msg { background: #0088cc;    color: #fff; }

        /* ── Instructions ── */
        .rw-instructions {
            background: #e3f2fd;
            border-radius: var(--radius);
            padding: 14px;
            margin-top: 16px;
            font-size: .82rem;
        }
        .rw-instructions h5 { margin-bottom: 8px; color: #1565c0; }
        .rw-instructions ol { padding-<?= $isRtl ? 'right' : 'left' ?>: 18px; }
        .rw-instructions li { margin-bottom: 6px; }

        /* ── Meta info ── */
        .rw-meta {
            font-size: .8rem;
            color: #666;
            background: #f5f5f5;
            border-radius: 6px;
            padding: 8px 10px;
            margin-bottom: 14px;
        }
        .rw-meta span { font-weight: 600; color: #333; }

        /* ── Footer link ── */
        .rw-footer-link {
            text-align: center;
            font-size: .83rem;
            color: #666;
            margin-top: 20px;
        }
        .rw-footer-link a { color: var(--wa-dk); font-weight: 600; text-decoration: none; }
        .rw-footer-link a:hover { text-decoration: underline; }

        /* ── Spinner ── */
        .rw-spinner {
            display: inline-block;
            width: 18px; height: 18px;
            border: 3px solid rgba(255,255,255,.5);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin .7s linear infinite;
            vertical-align: middle;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        @media (max-width: 480px) {
            .rw-row { grid-template-columns: 1fr; }
            .rw-actions { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>

<div class="rw-card">

    <!-- Header -->
    <div class="rw-header">
        <div class="rw-logo">
            <svg viewBox="0 0 24 24" width="40" height="40" fill="#fff" style="display:inline-block;vertical-align:middle;margin-<?= $isRtl ? 'left' : 'right' ?>:8px">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.76.982.998-3.675-.236-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.9 6.994c-.004 5.45-4.438 9.88-9.888 9.88m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.333.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.333 11.893-11.893 0-3.18-1.24-6.162-3.495-8.411"/>
            </svg>
            QOOQZ
        </div>
        <h1><?= $isRtl ? 'إنشاء حساب عبر واتساب' : 'Register with WhatsApp Verification' ?></h1>
        <p><?= $isRtl ? 'سجّل بياناتك وتحقق من رقمك عبر واتساب في نفس الجلسة' : 'Register and verify your WhatsApp number in one session' ?></p>
    </div>

    <!-- Step indicators -->
    <div class="rw-steps" id="rwSteps">
        <div class="rw-step active" id="sInd1">
            <div class="rw-step-num">1</div>
            <span><?= $isRtl ? 'البيانات' : 'Details' ?></span>
        </div>
        <div class="rw-step" id="sInd2">
            <div class="rw-step-num">2</div>
            <span><?= $isRtl ? 'إرسال الرابط' : 'Send Link' ?></span>
        </div>
        <div class="rw-step" id="sInd3">
            <div class="rw-step-num">3</div>
            <span><?= $isRtl ? 'التفعيل' : 'Activate' ?></span>
        </div>
    </div>

    <div class="rw-body">

        <!-- ══════════════════════════════════════
             STEP 1 — Registration form
        ══════════════════════════════════════ -->
        <div id="step1">

            <div class="rw-row">
                <div class="rw-field">
                    <label for="rw_username"><?= $isRtl ? 'اسم المستخدم *' : 'Username *' ?></label>
                    <input id="rw_username" type="text" autocomplete="username" required
                           placeholder="<?= $isRtl ? 'أدخل اسم المستخدم' : 'Enter username' ?>">
                </div>
                <div class="rw-field">
                    <label for="rw_email"><?= $isRtl ? 'البريد الإلكتروني *' : 'Email *' ?></label>
                    <input id="rw_email" type="email" autocomplete="email" required
                           placeholder="example@mail.com">
                </div>
            </div>

            <div class="rw-field">
                <label for="rw_phone"><?= $isRtl ? 'رقم واتساب *' : 'WhatsApp Number *' ?></label>
                <input id="rw_phone" type="tel" autocomplete="tel" required
                       placeholder="+971 50 000 0000"
                       style="font-size:1.05rem;font-weight:600;letter-spacing:.5px;">
                <small style="color:#888;font-size:.75rem;display:block;margin-top:4px;">
                    <?= $isRtl ? 'سيُرسَل رابط التفعيل إلى هذا الرقم عبر واتساب' : 'A verification link will be sent to this number via WhatsApp' ?>
                </small>
            </div>

            <div class="rw-field">
                <label for="rw_password"><?= $isRtl ? 'كلمة المرور *' : 'Password *' ?></label>
                <input id="rw_password" type="password" autocomplete="new-password" required
                       minlength="6" placeholder="••••••••">
            </div>

            <div class="rw-row">
                <div class="rw-field">
                    <label for="rw_country"><?= $isRtl ? 'البلد' : 'Country' ?></label>
                    <select id="rw_country">
                        <option value=""><?= $isRtl ? '-- اختر البلد --' : '-- Select Country --' ?></option>
                    </select>
                </div>
                <div class="rw-field">
                    <label for="rw_city"><?= $isRtl ? 'المدينة' : 'City' ?></label>
                    <select id="rw_city" disabled>
                        <option value=""><?= $isRtl ? '-- اختر المدينة --' : '-- Select City --' ?></option>
                    </select>
                </div>
            </div>

            <div id="regAlert" class="rw-alert"></div>

            <button id="btnRegister" class="rw-btn rw-btn-wa" style="margin-top:6px;">
                <span id="btnRegisterIcon">📋</span>
                <?= $isRtl ? 'إنشاء الحساب والمتابعة' : 'Create Account & Continue' ?>
            </button>

            <div class="rw-footer-link">
                <?= $isRtl ? 'لديك حساب؟' : 'Already have an account?' ?>
                <a href="/frontend/login.php"><?= $isRtl ? 'تسجيل الدخول' : 'Sign In' ?></a>
            </div>
        </div>

        <!-- ══════════════════════════════════════
             STEP 2 — Generate & send WhatsApp link
        ══════════════════════════════════════ -->
        <div id="step2" style="display:none">

            <div class="rw-meta" id="metaInfo">
                <?= $isRtl ? 'المستخدم:' : 'User:' ?> <span id="metaUser">—</span> &nbsp;|&nbsp;
                <?= $isRtl ? 'رقم واتساب:' : 'WhatsApp:' ?> <span id="metaPhone">—</span>
            </div>

            <p style="font-size:.88rem;color:#555;margin-bottom:14px;">
                <?= $isRtl
                    ? 'اضغط الزر أدناه لإنشاء رابط التفعيل المرتبط بجلستك الحالية وإرساله إلى واتساب.'
                    : 'Click the button below to generate a session-bound verification link and send it to WhatsApp.' ?>
            </p>

            <button id="btnGenLink" class="rw-btn rw-btn-wa">
                <span>🔗</span>
                <?= $isRtl ? 'إنشاء رابط التفعيل' : 'Generate Verification Link' ?>
            </button>

            <div id="genAlert" class="rw-alert"></div>

            <!-- Link display (shown after generation) -->
            <div id="linkSection" style="display:none">

                <!-- Quick action buttons -->
                <div class="rw-actions">
                    <button id="btnOpenWA" class="rw-action-btn rw-action-wa">
                        <span class="icon">📱</span>
                        <?= $isRtl ? 'فتح واتساب' : 'Open WhatsApp' ?>
                    </button>
                    <button id="btnCopyLink" class="rw-action-btn rw-action-cp">
                        <span class="icon">📋</span>
                        <?= $isRtl ? 'نسخ الرابط' : 'Copy Link' ?>
                    </button>
                    <button id="btnCopyMsg" class="rw-action-btn rw-action-msg">
                        <span class="icon">📝</span>
                        <?= $isRtl ? 'نسخ الرسالة' : 'Copy Message' ?>
                    </button>
                </div>

                <p id="copyStatus" style="font-size:.78rem;color:#555;margin-top:6px;min-height:1em;text-align:center;"></p>

                <!-- Verification link -->
                <div class="rw-link-box">
                    <strong><?= $isRtl ? 'رابط التفعيل:' : 'Verification Link:' ?></strong>
                    <p id="verLink" style="margin-top:6px;"></p>
                </div>

                <!-- WhatsApp message preview -->
                <details style="margin-top:12px;">
                    <summary style="cursor:pointer;font-size:.82rem;color:#555;font-weight:600;">
                        <?= $isRtl ? 'عرض رسالة واتساب الجاهزة' : 'Preview WhatsApp Message' ?>
                    </summary>
                    <textarea id="waMsgPreview" class="rw-msg-preview" rows="4" readonly></textarea>
                </details>

                <!-- Countdown -->
                <div class="rw-timer" id="timerBox" style="display:none">
                    <div id="rwClock" class="rw-clock">15:00</div>
                    <p><?= $isRtl ? 'ينتهي رابط التفعيل خلال هذه المدة' : 'Verification link expires in' ?></p>
                </div>

                <!-- Instructions -->
                <div class="rw-instructions">
                    <h5><?= $isRtl ? '🚀 خطوات التفعيل السريع:' : '🚀 Quick Activation Steps:' ?></h5>
                    <ol>
                        <?php if ($isRtl): ?>
                        <li>اضغط على زر <strong>"فتح واتساب"</strong></li>
                        <li>سيفتح واتساب مع رسالة جاهزة للإرسال</li>
                        <li>أرسل الرسالة إلى <strong>نفس رقم هاتفك</strong></li>
                        <li>افتح الرابط المرسَل <strong>في هذا المتصفح</strong></li>
                        <li>سيتم تفعيل حسابك تلقائياً</li>
                        <?php else: ?>
                        <li>Tap <strong>"Open WhatsApp"</strong></li>
                        <li>WhatsApp will open with a ready-to-send message</li>
                        <li>Send it to <strong>your own number</strong></li>
                        <li>Open the link <strong>in this same browser</strong></li>
                        <li>Your account will be activated automatically</li>
                        <?php endif; ?>
                    </ol>
                    <p style="margin-top:8px;font-size:.78rem;color:#666;">
                        <strong>⚠️ <?= $isRtl ? 'ملاحظة:' : 'Note:' ?></strong>
                        <?= $isRtl
                            ? 'الرابط مرتبط بجلستك الحالية ولن يعمل في متصفح آخر.'
                            : 'The link is bound to your current session and will not work in another browser.' ?>
                    </p>
                </div>

                <!-- Extra actions -->
                <div class="rw-btn-row">
                    <button id="btnRegenLink" class="rw-btn rw-btn-pri rw-btn-sm">
                        🔄 <?= $isRtl ? 'إنشاء رابط جديد' : 'Generate New Link' ?>
                    </button>
                    <button id="btnCheckStatus" class="rw-btn rw-btn-out rw-btn-sm">
                        ✔ <?= $isRtl ? 'تحقق من حالة التفعيل' : 'Check Activation Status' ?>
                    </button>
                </div>
            </div>

            <div class="rw-btn-row" style="margin-top:16px;">
                <button id="btnBackToStep1" class="rw-btn rw-btn-out rw-btn-sm">
                    ← <?= $isRtl ? 'تعديل البيانات' : 'Edit Details' ?>
                </button>
                <button id="btnStartOver" class="rw-btn rw-btn-out rw-btn-sm">
                    ↺ <?= $isRtl ? 'بدء من جديد' : 'Start Over' ?>
                </button>
            </div>
        </div>

        <!-- ══════════════════════════════════════
             STEP 3 — Account activated
        ══════════════════════════════════════ -->
        <div id="step3" style="display:none;text-align:center;">
            <div style="font-size:4rem;margin-bottom:12px;">🎉</div>
            <h2 style="color:var(--ok);font-size:1.3rem;"><?= $isRtl ? 'تم تفعيل حسابك بنجاح!' : 'Account Activated!' ?></h2>
            <p style="color:#555;margin-top:8px;font-size:.9rem;">
                <?= $isRtl ? 'يمكنك الآن تسجيل الدخول باستخدام بريدك وكلمة المرور.' : 'You can now sign in with your email and password.' ?>
            </p>
            <a href="/frontend/login.php" class="rw-btn rw-btn-wa" style="display:inline-flex;margin-top:20px;width:auto;padding:12px 28px;text-decoration:none;">
                <?= $isRtl ? 'تسجيل الدخول الآن' : 'Sign In Now' ?>
            </a>
        </div>

    </div><!-- /.rw-body -->
</div><!-- /.rw-card -->

<script>
(function () {
    'use strict';

    // ── DOM refs ──────────────────────────────────────────────────────
    const step1       = document.getElementById('step1');
    const step2       = document.getElementById('step2');
    const step3       = document.getElementById('step3');
    const sInd1       = document.getElementById('sInd1');
    const sInd2       = document.getElementById('sInd2');
    const sInd3       = document.getElementById('sInd3');
    const regAlert    = document.getElementById('regAlert');
    const genAlert    = document.getElementById('genAlert');
    const linkSection = document.getElementById('linkSection');
    const metaUser    = document.getElementById('metaUser');
    const metaPhone   = document.getElementById('metaPhone');
    const verLink     = document.getElementById('verLink');
    const waMsgPreview= document.getElementById('waMsgPreview');
    const rwClock     = document.getElementById('rwClock');
    const timerBox    = document.getElementById('timerBox');
    const copyStatus  = document.getElementById('copyStatus');
    const csrfToken   = <?= json_encode($csrf) ?>;

    // ── State ─────────────────────────────────────────────────────────
    let state = {
        user_id:       null,
        username:      null,
        phone:         null,
        session_token: null,
        link:          null,
        waMessage:     null,
        expiresAt:     null,
    };

    // Restore partial session from sessionStorage (same tab only)
    try {
        const saved = JSON.parse(sessionStorage.getItem('rw_state') || 'null');
        if (saved && saved.user_id) { Object.assign(state, saved); }
    } catch (_) {}

    let countdownInterval = null;

    // ── Helpers ───────────────────────────────────────────────────────
    function showAlert(el, type, msg) {
        el.className = 'rw-alert ' + type;
        el.textContent = msg;
    }
    function hideAlert(el) { el.className = 'rw-alert'; el.textContent = ''; }

    function setStep(n) {
        step1.style.display = n === 1 ? '' : 'none';
        step2.style.display = n === 2 ? '' : 'none';
        step3.style.display = n === 3 ? '' : 'none';
        [sInd1, sInd2, sInd3].forEach((s, i) => {
            s.className = 'rw-step' + (i + 1 < n ? ' done' : i + 1 === n ? ' active' : '');
        });
    }

    async function postJSON(url, body) {
        const r = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });
        const txt = await r.text();
        try { return { ok: true, status: r.status, data: JSON.parse(txt) }; }
        catch (_) { return { ok: false, status: r.status, text: txt }; }
    }

    async function getJSON(url) {
        try {
            const r = await fetch(url, { cache: 'no-store' });
            return await r.json();
        } catch (_) { return []; }
    }

    // ── Countries / Cities ────────────────────────────────────────────
    const countrySelect = document.getElementById('rw_country');
    const citySelect    = document.getElementById('rw_city');

    async function loadCountries() {
        const data = await getJSON('/api/locations/countries.php');
        countrySelect.innerHTML = '<option value=""><?= $isRtl ? '-- اختر البلد --' : '-- Select Country --' ?></option>';
        for (const c of data) {
            const o = document.createElement('option');
            o.value = c.id;
            o.textContent = c.name;
            countrySelect.appendChild(o);
        }
    }

    async function loadCities(cid) {
        citySelect.innerHTML = '<option value=""><?= $isRtl ? '-- اختر المدينة --' : '-- Select City --' ?></option>';
        citySelect.disabled = true;
        if (!cid) return;
        const data = await getJSON('/api/locations/cities.php?country_id=' + encodeURIComponent(cid));
        for (const ct of data) {
            const o = document.createElement('option');
            o.value = ct.id;
            o.textContent = ct.name;
            citySelect.appendChild(o);
        }
        citySelect.disabled = false;
    }

    countrySelect.addEventListener('change', () => {
        if (countrySelect.value) loadCities(countrySelect.value);
        else {
            citySelect.innerHTML = '<option value=""><?= $isRtl ? '-- اختر المدينة --' : '-- Select City --' ?></option>';
            citySelect.disabled = true;
        }
    });

    // ── Step 1: Register ──────────────────────────────────────────────
    document.getElementById('btnRegister').addEventListener('click', async () => {
        hideAlert(regAlert);

        const username = document.getElementById('rw_username').value.trim();
        const email    = document.getElementById('rw_email').value.trim();
        const phone    = document.getElementById('rw_phone').value.trim();
        const password = document.getElementById('rw_password').value;
        const country  = countrySelect.value;
        const city     = citySelect.value;

        if (!username || !email || !password) {
            showAlert(regAlert, 'err', '<?= $isRtl ? 'يرجى تعبئة جميع الحقول المطلوبة (*).' : 'Please fill all required fields (*).' ?>');
            return;
        }
        if (!phone) {
            showAlert(regAlert, 'err', '<?= $isRtl ? 'رقم واتساب مطلوب.' : 'WhatsApp number is required.' ?>');
            return;
        }

        const btn = document.getElementById('btnRegister');
        btn.disabled = true;
        btn.innerHTML = '<span class="rw-spinner"></span> <?= $isRtl ? 'جارٍ التسجيل...' : 'Registering...' ?>';

        const body = {
            username,
            email,
            password,
            phone,
            is_active: 0,
            channel: 'whatsapp',
            csrf_token: csrfToken,
        };
        if (country) body.country_id = parseInt(country, 10);
        if (city)    body.city_id    = parseInt(city, 10);

        const res = await postJSON('/api/users/register_user.php', body);

        btn.disabled = false;
        btn.innerHTML = '📋 <?= $isRtl ? 'إنشاء الحساب والمتابعة' : 'Create Account & Continue' ?>';

        if (!res.ok) {
            showAlert(regAlert, 'err', '<?= $isRtl ? 'خطأ من السيرفر: ' : 'Server error: ' ?>' + (res.text || res.status));
            return;
        }

        const j = res.data;
        if (!j.ok) {
            showAlert(regAlert, 'err', (j.error || j.message || JSON.stringify(j)));
            return;
        }

        state.user_id       = j.user_id;
        state.username      = j.user?.username  ?? username;
        state.phone         = j.user?.phone      ?? phone;
        state.session_token = j.session_token    ?? null;

        // Persist state for this tab so page refresh or verify redirect works
        try { sessionStorage.setItem('rw_state', JSON.stringify(state)); } catch (_) {}

        metaUser.textContent  = state.username;
        metaPhone.textContent = state.phone;

        setStep(2);
    });

    // ── Step 2: Generate link ─────────────────────────────────────────
    async function generateLink() {
        if (!state.user_id) {
            showAlert(genAlert, 'err', '<?= $isRtl ? 'لا يوجد مستخدم مسجّل.' : 'No registered user found.' ?>');
            return;
        }

        linkSection.style.display = 'none';
        hideAlert(genAlert);
        showAlert(genAlert, 'info', '<?= $isRtl ? 'جارٍ إنشاء رابط التفعيل...' : 'Generating verification link...' ?>');

        if (countdownInterval) { clearInterval(countdownInterval); countdownInterval = null; }
        timerBox.style.display = 'none';

        const body = {
            user_id:       state.user_id,
            channel:       'whatsapp',
            ttl:           900,
        };
        if (state.session_token) body.session_token = state.session_token;

        const res = await postJSON('/api/users/send_verification_code.php', body);
        hideAlert(genAlert);

        if (!res.ok) {
            showAlert(genAlert, 'err', '<?= $isRtl ? 'خطأ من السيرفر: ' : 'Server error: ' ?>' + (res.text || res.status));
            return;
        }

        const j = res.data;
        if (!j.ok) {
            showAlert(genAlert, 'err', (j.error || j.message || JSON.stringify(j)));
            return;
        }

        state.link      = j.link;
        state.waMessage = j.whatsapp_message ?? '';
        state.expiresAt = j.expires_at ?? null;
        try { sessionStorage.setItem('rw_state', JSON.stringify(state)); } catch (_) {}

        showAlert(genAlert, 'ok', '✅ ' + (j.message || '<?= $isRtl ? 'تم إنشاء رابط التفعيل.' : 'Verification link generated.' ?>'));

        verLink.textContent   = state.link;
        waMsgPreview.value    = state.waMessage;
        linkSection.style.display = '';

        if (state.expiresAt) {
            timerBox.style.display = '';
            startCountdown(state.expiresAt);
        }
    }

    document.getElementById('btnGenLink').addEventListener('click', generateLink);
    document.getElementById('btnRegenLink').addEventListener('click', generateLink);

    // ── Open WhatsApp ─────────────────────────────────────────────────
    document.getElementById('btnOpenWA').addEventListener('click', () => {
        if (!state.phone || !state.waMessage) {
            alert('<?= $isRtl ? 'الرسالة أو الرقم غير متاحين.' : 'Message or phone not available.' ?>');
            return;
        }
        let clean = state.phone.replace(/[^\d]/g, '');
        if (clean.startsWith('0')) clean = clean.substring(1);
        window.open('https://wa.me/' + clean + '?text=' + encodeURIComponent(state.waMessage), '_blank');
    });

    // ── Copy link ─────────────────────────────────────────────────────
    document.getElementById('btnCopyLink').addEventListener('click', async () => {
        if (!state.link) return;
        try {
            await navigator.clipboard.writeText(state.link);
            copyStatus.textContent = '✅ <?= $isRtl ? 'تم نسخ الرابط' : 'Link copied' ?>';
        } catch (_) {
            copyStatus.textContent = '<?= $isRtl ? 'انسخ الرابط يدوياً' : 'Copy the link manually' ?>';
        }
        setTimeout(() => { copyStatus.textContent = ''; }, 3000);
    });

    // ── Copy message ──────────────────────────────────────────────────
    document.getElementById('btnCopyMsg').addEventListener('click', async () => {
        if (!state.waMessage) return;
        try {
            await navigator.clipboard.writeText(state.waMessage);
            copyStatus.textContent = '✅ <?= $isRtl ? 'تم نسخ الرسالة' : 'Message copied' ?>';
        } catch (_) {
            copyStatus.textContent = '<?= $isRtl ? 'انسخ الرسالة يدوياً' : 'Copy the message manually' ?>';
        }
        setTimeout(() => { copyStatus.textContent = ''; }, 3000);
    });

    // ── Check activation ──────────────────────────────────────────────
    document.getElementById('btnCheckStatus').addEventListener('click', async () => {
        if (!state.user_id) return;
        showAlert(genAlert, 'info', '<?= $isRtl ? 'جارٍ التحقق من حالة التفعيل...' : 'Checking activation status...' ?>');

        const res = await postJSON('/api/users/check_activation.php', { user_id: state.user_id, csrf_token: csrfToken });
        hideAlert(genAlert);

        if (res.ok && res.data && res.data.is_active) {
            // Move to success step
            setStep(3);
            sInd2.className = 'rw-step done';
            sInd3.className = 'rw-step active';
            try { sessionStorage.removeItem('rw_state'); } catch (_) {}
        } else {
            showAlert(genAlert, 'warn', '⏳ <?= $isRtl ? 'لم يتم التفعيل بعد. تأكد من فتح الرابط في هذا المتصفح.' : 'Not activated yet. Make sure you opened the link in this browser.' ?>');
        }
    });

    // ── Navigation ────────────────────────────────────────────────────
    document.getElementById('btnBackToStep1').addEventListener('click', () => setStep(1));
    document.getElementById('btnStartOver').addEventListener('click', () => {
        state = {};
        try { sessionStorage.removeItem('rw_state'); } catch (_) {}
        if (countdownInterval) { clearInterval(countdownInterval); countdownInterval = null; }
        location.reload();
    });

    // ── Countdown ─────────────────────────────────────────────────────
    function startCountdown(expiresAt) {
        const expiry = new Date(expiresAt);
        if (isNaN(expiry.getTime())) { return; } // invalid timestamp — skip countdown
        function tick() {
            const diff = expiry - Date.now();
            if (diff <= 0) {
                rwClock.textContent = '00:00';
                clearInterval(countdownInterval);
                showAlert(genAlert, 'err', '⏰ <?= $isRtl ? 'انتهت صلاحية الرابط. اضغط "إنشاء رابط جديد".' : 'Link expired. Click "Generate New Link".' ?>');
                return;
            }
            const m = Math.floor(diff / 60000).toString().padStart(2, '0');
            const s = Math.floor((diff % 60000) / 1000).toString().padStart(2, '0');
            rwClock.textContent = m + ':' + s;
        }
        tick();
        countdownInterval = setInterval(tick, 1000);
    }

    // ── Init ──────────────────────────────────────────────────────────
    (function init() {
        loadCountries().catch(() => {});

        // If state was restored from sessionStorage, jump to step 2
        if (state.user_id) {
            metaUser.textContent  = state.username  ?? '—';
            metaPhone.textContent = state.phone      ?? '—';
            setStep(2);
        }
    })();

})();
</script>

</body>
</html>
