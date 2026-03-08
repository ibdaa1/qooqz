<?php
/**
 * TORO — login.php
 * ضعه في: /public_html/toro/api/login.php
 */
declare(strict_types=1);

// ── قراءة .env ────────────────────────────────────────────────
$env = [];
$envPath = __DIR__ . '/shared/config/.env';
if (file_exists($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        $eq = strpos($line, '=');
        $k  = trim(substr($line, 0, $eq));
        $v  = trim(substr($line, $eq + 1));
        if (strlen($v) >= 2 && (($v[0]==='"'&&$v[-1]==='"')||($v[0]==="'"&&$v[-1]==="'")))
            $v = substr($v, 1, -1);
        $env[$k] = $v;
    }
}

// ── اتصال DB ─────────────────────────────────────────────────
$pdo = null; $dbErr = null;
try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $env['DB_HOST']??'localhost', $env['DB_PORT']??'3306',
            $env['DB_NAME']??$env['DB_DATABASE']??''
        ),
        $env['DB_USER']??$env['DB_USERNAME']??'',
        $env['DB_PASS']??$env['DB_PASSWORD']??'',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
    );
} catch (Throwable $e) { $dbErr = $e->getMessage(); }

// ══════════════════════════════════════════════════════════════
// SESSION MANAGER — يحفظ الجلسة في جدول sessions
// ══════════════════════════════════════════════════════════════
class ToroSession {
    private string $id      = '';
    private array  $data    = [];
    private bool   $started = false;
    private const  COOKIE   = 'TORO_SID';
    private const  TTL      = 86400 * 30; // 30 يوم

    public function __construct(private ?PDO $pdo) {
        if (!$pdo) return;
        $this->id = $_COOKIE[self::COOKIE] ?? '';
        if ($this->id) $this->load();
        if (!$this->id) $this->newSession();
        $this->started = true;
    }

    private function newSession(): void {
        $this->id   = bin2hex(random_bytes(32));
        $this->data = ['_created' => time()];
        $this->setCookie();
        $this->persist();
    }

    private function load(): void {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT payload, last_activity FROM sessions WHERE id=:id LIMIT 1"
            );
            $stmt->execute([':id' => $this->id]);
            $row = $stmt->fetch();
            // إذا انتهت الجلسة أو غير موجودة
            if (!$row || (time() - (int)$row['last_activity']) > self::TTL) {
                $this->newSession();
                return;
            }
            $this->data = json_decode($row['payload'] ?? '{}', true) ?? [];
        } catch (Throwable) { $this->data = []; }
    }

    public function regenerate(): void {
        // منع Session Fixation
        $old = $this->id;
        try { $this->pdo->prepare("DELETE FROM sessions WHERE id=:id")->execute([':id'=>$old]); }
        catch (Throwable) {}
        $this->id   = bin2hex(random_bytes(32));
        $this->setCookie();
        $this->persist();
    }

    public function persist(): void {
        if (!$this->pdo) return;
        try {
            $this->pdo->prepare("
                INSERT INTO sessions (id, user_id, ip_address, user_agent, payload, last_activity)
                VALUES (:id, :uid, :ip, :ua, :payload, :ts)
                ON DUPLICATE KEY UPDATE
                    user_id=VALUES(user_id), payload=VALUES(payload), last_activity=VALUES(last_activity)
            ")->execute([
                ':id'      => $this->id,
                ':uid'     => $this->data['user_id'] ?? null,
                ':ip'      => $_SERVER['REMOTE_ADDR'] ?? null,
                ':ua'      => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 300),
                ':payload' => json_encode($this->data, JSON_UNESCAPED_UNICODE),
                ':ts'      => time(),
            ]);
        } catch (Throwable) {}
    }

    public function destroy(): void {
        if ($this->pdo && $this->id) {
            try { $this->pdo->prepare("DELETE FROM sessions WHERE id=:id")->execute([':id'=>$this->id]); }
            catch (Throwable) {}
        }
        setcookie(self::COOKIE, '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
        $this->id   = '';
        $this->data = [];
    }

    private function setCookie(): void {
        setcookie(self::COOKIE, $this->id, [
            'expires'  => time() + self::TTL,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public function set(string $k, mixed $v): void { $this->data[$k] = $v; $this->persist(); }
    public function get(string $k, mixed $d = null): mixed { return $this->data[$k] ?? $d; }
    public function has(string $k): bool { return array_key_exists($k, $this->data); }
    public function forget(string $k): void { unset($this->data[$k]); $this->persist(); }
    public function loggedIn(): bool { return $this->has('user_id') && $this->has('user'); }
    public function csrfToken(): string {
        if (!$this->has('csrf')) $this->set('csrf', bin2hex(random_bytes(20)));
        return (string)$this->get('csrf');
    }
    public function csrfValid(string $t): bool { return hash_equals($this->csrfToken(), $t); }
}

// ── بدء الجلسة ───────────────────────────────────────────────
$sess = new ToroSession($pdo);

// إذا مسجل — وجّهه للوحة التحكم
if ($sess->loggedIn()) { header('Location: dashboard.php'); exit; }

// ── معالجة POST ───────────────────────────────────────────────
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $email    = trim(strtolower($_POST['email']    ?? ''));
    $password = $_POST['password'] ?? '';
    $csrf     = $_POST['_csrf']    ?? '';

    if (!$sess->csrfValid($csrf)) {
        $error = 'طلب غير صالح، أعد المحاولة';

    } elseif (empty($email) || empty($password)) {
        $error = 'يرجى إدخال البريد وكلمة المرور';

    } else {
        // ── Rate limit: 10 محاولات / 5 دقائق ─────────────────
        $rlKey = 'login:' . ($_SERVER['REMOTE_ADDR'] ?? '0');
        try {
            $pdo->prepare("
                INSERT INTO rate_limits (`key`, attempts, last_attempt)
                VALUES (:k, 1, NOW())
                ON DUPLICATE KEY UPDATE
                    attempts     = IF(last_attempt < DATE_SUB(NOW(), INTERVAL 5 MINUTE), 1, attempts+1),
                    last_attempt = NOW()
            ")->execute([':k' => $rlKey]);

            $att = (int)($pdo->prepare("SELECT attempts FROM rate_limits WHERE `key`=:k")
                ->execute([':k'=>$rlKey]) ? $pdo->query("SELECT attempts FROM rate_limits WHERE `key`=".$pdo->quote($rlKey))->fetchColumn() : 0);

            if ($att > 10) {
                $error = '⛔ محاولات كثيرة جداً، انتظر 5 دقائق';
            } else {
                // ── جلب المستخدم ───────────────────────────────
                $stmt = $pdo->prepare("
                    SELECT u.*, r.slug role_slug, r.name role_name
                    FROM users u
                    LEFT JOIN roles r ON u.role_id = r.id
                    WHERE u.email = :email AND u.deleted_at IS NULL
                    LIMIT 1
                ");
                $stmt->execute([':email' => $email]);
                $user = $stmt->fetch();

                if (!$user || !password_verify($password, (string)$user['password_hash'])) {
                    $error = 'البريد الإلكتروني أو كلمة المرور غير صحيحة';
                } elseif (!(bool)$user['is_active']) {
                    $error = 'هذا الحساب موقوف — تواصل مع الدعم';
                } else {
                    // ✅ نجح — أعد توليد الجلسة منعاً للـ fixation
                    $sess->regenerate();
                    $sess->set('user_id', (int)$user['id']);
                    $sess->set('user', [
                        'id'         => $user['id'],
                        'name'       => $user['first_name'] . ' ' . $user['last_name'],
                        'first_name' => $user['first_name'],
                        'email'      => $user['email'],
                        'role'       => $user['role_slug'],
                        'role_name'  => $user['role_name'],
                        'avatar'     => $user['avatar'] ?? null,
                    ]);
                    $sess->set('logged_in_at', date('Y-m-d H:i:s'));
                    $sess->forget('csrf');

                    // تحديث last_login
                    $pdo->prepare("UPDATE users SET last_login_at=NOW() WHERE id=:id")
                        ->execute([':id' => $user['id']]);

                    // سجل الدخول
                    try {
                        $pdo->prepare("INSERT INTO audit_logs (user_id,action,entity,entity_id,ip_address,user_agent,created_at)
                            VALUES (:uid,'login','users',:uid,:ip,:ua,NOW())")
                            ->execute([':uid'=>$user['id'],':ip'=>$_SERVER['REMOTE_ADDR']??'',
                                       ':ua'=>substr($_SERVER['HTTP_USER_AGENT']??'',0,300)]);
                    } catch (Throwable) {}

                    header('Location: dashboard.php'); exit;
                }
            }
        } catch (Throwable $e) {
            $error = 'خطأ في النظام: ' . $e->getMessage();
        }
    }
}

$csrf = $sess->csrfToken();
$prevEmail = htmlspecialchars($_POST['email'] ?? '');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>TORO — دخول</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;600&family=Tajawal:wght@300;400;500;700&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box }

:root {
  --gold:      #C9A84C;
  --gold-dim:  #9A7A32;
  --gold-glow: rgba(201,168,76,.18);
  --ink:       #0B0B0B;
  --surface:   #111008;
  --panel:     rgba(20,17,8,.92);
  --border:    rgba(201,168,76,.18);
  --text:      #E8E0CC;
  --muted:     #7A7260;
  --err:       #E05252;
  --err-bg:    rgba(224,82,82,.08);
}

html, body {
  height: 100%;
  background: var(--ink);
  font-family: 'Tajawal', sans-serif;
  color: var(--text);
  overflow: hidden;
}

/* ── Background ────────────────────────────────────── */
.bg {
  position: fixed; inset: 0; z-index: 0;
  background:
    radial-gradient(ellipse 70% 55% at 20% 10%, rgba(201,168,76,.07) 0%, transparent 70%),
    radial-gradient(ellipse 50% 40% at 85% 85%, rgba(201,168,76,.05) 0%, transparent 60%),
    #0B0B0B;
}
.bg-lines {
  position: absolute; inset: 0;
  background-image:
    linear-gradient(rgba(201,168,76,.04) 1px, transparent 1px),
    linear-gradient(90deg, rgba(201,168,76,.04) 1px, transparent 1px);
  background-size: 60px 60px;
  mask-image: radial-gradient(ellipse 80% 80% at 50% 50%, black 30%, transparent 100%);
}
.orb {
  position: absolute; border-radius: 50%;
  filter: blur(80px); pointer-events: none;
  animation: drift 12s ease-in-out infinite alternate;
}
.orb-1 { width:400px; height:400px; top:-100px; left:-100px; background:rgba(201,168,76,.05); animation-delay:0s }
.orb-2 { width:300px; height:300px; bottom:-80px; right:-80px; background:rgba(201,168,76,.04); animation-delay:-6s }
@keyframes drift { from{transform:translate(0,0) scale(1)} to{transform:translate(30px,20px) scale(1.05)} }

/* ── Layout ────────────────────────────────────────── */
.page {
  position: relative; z-index: 1;
  height: 100vh; display: grid;
  grid-template-columns: 1fr 480px;
}

/* ── Left pane ─────────────────────────────────────── */
.left-pane {
  display: flex; flex-direction: column;
  justify-content: center; align-items: flex-start;
  padding: 4rem 5rem; gap: 2rem;
}
.brand-word {
  font-family: 'Cormorant Garamond', serif;
  font-size: clamp(5rem, 10vw, 9rem);
  font-weight: 300;
  color: transparent;
  -webkit-text-stroke: 1px rgba(201,168,76,.3);
  letter-spacing: 0.25em;
  line-height: 1;
  animation: fadeUp .8s ease both;
}
.brand-tagline {
  font-size: .85rem;
  color: var(--muted);
  letter-spacing: .25em;
  text-transform: uppercase;
  border-right: 2px solid var(--gold-dim);
  padding-right: 1rem;
  animation: fadeUp .8s .15s ease both;
}
.left-scents {
  display: flex; flex-direction: column; gap: .6rem;
  margin-top: 1rem;
  animation: fadeUp .8s .3s ease both;
}
.scent-pill {
  display: inline-flex; align-items: center; gap: .5rem;
  font-size: .78rem; color: rgba(201,168,76,.45);
  letter-spacing: .1em;
}
.scent-pill::before {
  content: '◈';
  color: var(--gold-dim);
  font-size: .6rem;
}
@keyframes fadeUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }

/* ── Right pane ────────────────────────────────────── */
.right-pane {
  display: flex; align-items: center; justify-content: center;
  padding: 2rem;
  border-right: 1px solid var(--border);
  background: var(--panel);
  backdrop-filter: blur(20px);
  position: relative;
}
.right-pane::before {
  content: '';
  position: absolute; inset: 0;
  background: linear-gradient(180deg, rgba(201,168,76,.03) 0%, transparent 100%);
  pointer-events: none;
}

/* ── Card ──────────────────────────────────────────── */
.card {
  width: 100%; max-width: 360px;
  animation: slideIn .6s cubic-bezier(.16,1,.3,1) both;
}
@keyframes slideIn { from{opacity:0;transform:translateX(24px)} to{opacity:1;transform:translateX(0)} }

.card-top { text-align: center; margin-bottom: 2.5rem }
.logo-icon {
  width: 52px; height: 52px; margin: 0 auto 1.2rem;
  border: 1px solid var(--border);
  border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  font-family: 'Cormorant Garamond', serif;
  font-size: 1.6rem; font-weight: 600;
  color: var(--gold);
  background: rgba(201,168,76,.06);
  position: relative; overflow: hidden;
}
.logo-icon::after {
  content: '';
  position: absolute; inset: -50%;
  background: conic-gradient(from 0deg, transparent 0deg, rgba(201,168,76,.15) 60deg, transparent 120deg);
  animation: shimmer 4s linear infinite;
}
@keyframes shimmer { to { transform: rotate(360deg) } }
.card-title {
  font-family: 'Cormorant Garamond', serif;
  font-size: 1.6rem; font-weight: 400;
  color: var(--text); letter-spacing: .05em;
}
.card-sub { font-size: .8rem; color: var(--muted); margin-top: .35rem; letter-spacing: .05em }

/* ── Alert ─────────────────────────────────────────── */
.alert {
  background: var(--err-bg);
  border: 1px solid rgba(224,82,82,.25);
  border-radius: 10px;
  padding: .75rem 1rem;
  font-size: .83rem;
  color: #F08080;
  margin-bottom: 1.4rem;
  display: flex; align-items: center; gap: .6rem;
  animation: shake .35s ease;
}
.alert svg { flex-shrink: 0 }
@keyframes shake {
  0%,100%{transform:translateX(0)}
  20%,60%{transform:translateX(-5px)}
  40%,80%{transform:translateX(5px)}
}

.db-err {
  background: #1a0505;
  border: 1px solid rgba(224,82,82,.3);
  border-radius: 10px; padding: 1.2rem;
  font-size: .82rem; color: #f87171; text-align: center;
}

/* ── Form fields ───────────────────────────────────── */
.field { margin-bottom: 1.1rem }
.field label {
  display: block;
  font-size: .75rem;
  color: var(--muted);
  letter-spacing: .08em;
  text-transform: uppercase;
  margin-bottom: .45rem;
}
.input-wrap { position: relative }
.input-wrap .ico {
  position: absolute; right: .85rem; top: 50%;
  transform: translateY(-50%);
  color: var(--muted); width: 15px; height: 15px;
  pointer-events: none;
}
input[type=email], input[type=password], input[type=text] {
  width: 100%;
  padding: .75rem 2.5rem .75rem .85rem;
  background: rgba(255,255,255,.03);
  border: 1px solid rgba(201,168,76,.15);
  border-radius: 10px;
  color: var(--text);
  font-size: .9rem;
  font-family: 'Tajawal', sans-serif;
  outline: none;
  transition: border-color .2s, background .2s, box-shadow .2s;
  direction: ltr; text-align: right;
}
input:focus {
  border-color: rgba(201,168,76,.5);
  background: rgba(201,168,76,.04);
  box-shadow: 0 0 0 3px rgba(201,168,76,.08);
}
input.invalid { border-color: rgba(224,82,82,.5) }

.eye-btn {
  position: absolute; left: .75rem; top: 50%;
  transform: translateY(-50%);
  background: none; border: none; cursor: pointer;
  color: var(--muted); padding: .2rem;
  transition: color .2s;
}
.eye-btn:hover { color: var(--gold) }

/* ── Row ───────────────────────────────────────────── */
.row-meta {
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: 1.5rem;
}
.check-label {
  display: flex; align-items: center; gap: .45rem;
  font-size: .8rem; color: var(--muted); cursor: pointer;
}
.check-label input[type=checkbox] { accent-color: var(--gold); cursor: pointer }
.forgot-link {
  font-size: .78rem; color: var(--gold-dim);
  text-decoration: none; letter-spacing: .03em;
  transition: color .2s;
}
.forgot-link:hover { color: var(--gold) }

/* ── Submit ────────────────────────────────────────── */
.btn-submit {
  width: 100%;
  padding: .85rem;
  background: linear-gradient(135deg, #C9A84C, #8B6914);
  border: none; border-radius: 10px;
  color: #0B0B0B;
  font-size: .92rem; font-weight: 700;
  font-family: 'Tajawal', sans-serif;
  letter-spacing: .08em;
  cursor: pointer;
  position: relative; overflow: hidden;
  transition: box-shadow .25s, transform .15s, opacity .2s;
}
.btn-submit::before {
  content: '';
  position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,.15), transparent);
  transition: left .4s;
}
.btn-submit:hover::before { left: 100% }
.btn-submit:hover {
  box-shadow: 0 6px 28px rgba(201,168,76,.35);
  transform: translateY(-1px);
}
.btn-submit:active { transform: translateY(0); opacity: .9 }
.btn-submit:disabled { opacity: .5; cursor: not-allowed; transform: none }
.btn-submit .label { display: flex; align-items: center; justify-content: center; gap: .5rem }
.btn-submit .spinner {
  display: none;
  width: 18px; height: 18px;
  border: 2px solid rgba(11,11,11,.3);
  border-top-color: #0B0B0B;
  border-radius: 50%;
  animation: spin .6s linear infinite;
  margin: 0 auto;
}
.btn-submit.loading .label { display: none }
.btn-submit.loading .spinner { display: block }
@keyframes spin { to{transform:rotate(360deg)} }

/* ── Divider ───────────────────────────────────────── */
.divider {
  display: flex; align-items: center; gap: .8rem;
  margin: 1.4rem 0;
  font-size: .73rem; color: var(--muted); letter-spacing: .08em;
}
.divider::before, .divider::after {
  content: ''; flex: 1;
  height: 1px; background: var(--border);
}

/* ── OAuth ─────────────────────────────────────────── */
.oauth-row { display: grid; grid-template-columns: 1fr 1fr; gap: .6rem; margin-bottom: 1.4rem }
.oauth-btn {
  display: flex; align-items: center; justify-content: center; gap: .5rem;
  padding: .65rem;
  background: rgba(255,255,255,.03);
  border: 1px solid var(--border);
  border-radius: 10px;
  color: var(--muted); font-size: .8rem;
  font-family: 'Tajawal', sans-serif;
  cursor: pointer; transition: all .2s;
}
.oauth-btn:hover { border-color: rgba(201,168,76,.4); color: var(--text); background: rgba(201,168,76,.05) }

/* ── Footer ────────────────────────────────────────── */
.card-foot { text-align: center; font-size: .78rem; color: var(--muted); margin-top: 1.4rem }
.card-foot a { color: var(--gold-dim); text-decoration: none; transition: color .2s }
.card-foot a:hover { color: var(--gold) }

/* ── Session info ──────────────────────────────────── */
.sess-dbg {
  position: fixed; bottom: 1rem; left: 1rem;
  font-size: .68rem; color: rgba(201,168,76,.25);
  font-family: monospace; letter-spacing: .03em;
  pointer-events: none;
}

/* ── Responsive ────────────────────────────────────── */
@media (max-width: 768px) {
  html, body { overflow: auto }
  .page { grid-template-columns: 1fr; height: auto; min-height: 100vh }
  .left-pane { display: none }
  .right-pane { border: none; min-height: 100vh; padding: 2rem 1.5rem }
}
</style>
</head>
<body>

<div class="bg">
  <div class="bg-lines"></div>
  <div class="orb orb-1"></div>
  <div class="orb orb-2"></div>
</div>

<div class="page">

  <!-- ── اليسار ── -->
  <div class="left-pane">
    <div class="brand-word">TORO</div>
    <div class="brand-tagline">عطور فاخرة · Luxury Perfumes</div>
    <div class="left-scents">
      <span class="scent-pill">Oud Al Arabiya — عود ملكي</span>
      <span class="scent-pill">Musk Blanc — مسك أبيض</span>
      <span class="scent-pill">Amber Rose — عنبر الورد</span>
      <span class="scent-pill">Saffron Noir — زعفران أسود</span>
    </div>
  </div>

  <!-- ── اليمين ── -->
  <div class="right-pane">
    <div class="card">

      <div class="card-top">
        <div class="logo-icon">T</div>
        <div class="card-title">أهلاً بعودتك</div>
        <div class="card-sub">سجّل دخولك إلى لوحة التحكم</div>
      </div>

      <?php if ($dbErr): ?>
      <div class="db-err">
        ❌ تعذّر الاتصال بقاعدة البيانات<br>
        <small style="opacity:.6"><?= htmlspecialchars($dbErr) ?></small>
      </div>

      <?php else: ?>

      <?php if ($error): ?>
      <div class="alert">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?= htmlspecialchars($error) ?>
      </div>
      <?php endif ?>

      <form method="POST" id="frm" novalidate>
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

        <div class="field">
          <label>البريد الإلكتروني</label>
          <div class="input-wrap">
            <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            <input type="email" id="email" name="email"
                   value="<?= $prevEmail ?>"
                   placeholder="admin@toro.com"
                   autocomplete="email" required>
          </div>
        </div>

        <div class="field">
          <label>كلمة المرور</label>
          <div class="input-wrap">
            <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <input type="password" id="pass" name="password" placeholder="••••••••" autocomplete="current-password" required>
            <button type="button" class="eye-btn" onclick="toggleEye()" title="إظهار">
              <svg id="eyeIco" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
              </svg>
            </button>
          </div>
        </div>

        <div class="row-meta">
          <label class="check-label">
            <input type="checkbox" name="remember" <?= isset($_POST['remember'])?'checked':'' ?>>
            تذكرني
          </label>
          <a href="#" class="forgot-link">نسيت كلمة المرور؟</a>
        </div>

        <button type="submit" class="btn-submit" id="btn">
          <div class="label">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
            دخول
          </div>
          <div class="spinner"></div>
        </button>
      </form>

      <div class="divider">أو الدخول عبر</div>

      <div class="oauth-row">
        <button class="oauth-btn" onclick="alert('أضف GOOGLE_CLIENT_ID في .env')">
          <svg width="15" height="15" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
          Google
        </button>
        <button class="oauth-btn" onclick="alert('أضف FACEBOOK_APP_ID في .env')">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="#1877F2"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
          Facebook
        </button>
      </div>

      <div class="card-foot">
        ليس لديك حساب؟ <a href="register.php">إنشاء حساب</a>
      </div>

      <?php endif ?>
    </div>
  </div>
</div>

<!-- معلومات الجلسة (للتطوير فقط) -->
<?php if (($env['APP_ENV']??'production') === 'development'): ?>
<div class="sess-dbg">SESSION: <?= substr($csrf, 0, 12) ?>…</div>
<?php endif ?>

<script>
// إظهار/إخفاء كلمة المرور
function toggleEye() {
  const p = document.getElementById('pass');
  const i = document.getElementById('eyeIco');
  p.type = p.type === 'password' ? 'text' : 'password';
  i.innerHTML = p.type === 'text'
    ? '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>'
    : '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
}

// Loading state + validation
document.getElementById('frm')?.addEventListener('submit', function(e) {
  const email = document.getElementById('email');
  const pass  = document.getElementById('pass');
  const btn   = document.getElementById('btn');

  email.classList.remove('invalid');
  pass.classList.remove('invalid');
  let ok = true;

  if (!email.value.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
    email.classList.add('invalid'); ok = false;
  }
  if (!pass.value) { pass.classList.add('invalid'); ok = false; }
  if (!ok) { e.preventDefault(); return; }

  btn.classList.add('loading');
  btn.disabled = true;
});

// منع إعادة submit عند refresh
if (window.history.replaceState)
  window.history.replaceState(null, null, window.location.href);
</script>
</body>
</html>
