<?php
/**
 * TORO — generate_hash.php
 * شغّله مرة واحدة على سيرفرك لإنشاء حسابات تجريبية بـ hash صحيح
 * ثم احذفه فوراً!
 * الرابط: https://yourdomain.com/toro/api/generate_hash.php
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
try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $env['DB_HOST'] ?? 'localhost',
            $env['DB_PORT'] ?? '3306',
            $env['DB_NAME'] ?? $env['DB_DATABASE'] ?? ''
        ),
        $env['DB_USER'] ?? $env['DB_USERNAME'] ?? '',
        $env['DB_PASS'] ?? $env['DB_PASSWORD'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (Throwable $e) {
    die('<div style="font:16px monospace;padding:2rem;color:red">DB Error: ' . htmlspecialchars($e->getMessage()) . '</div>');
}

// ── إنشاء جدول sessions ───────────────────────────────────────
$pdo->exec("
    CREATE TABLE IF NOT EXISTS sessions (
        id            VARCHAR(128)  NOT NULL,
        user_id       INT UNSIGNED  DEFAULT NULL,
        ip_address    VARCHAR(45)   DEFAULT NULL,
        user_agent    VARCHAR(300)  DEFAULT NULL,
        payload       TEXT          NOT NULL,
        last_activity INT UNSIGNED  NOT NULL,
        created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_sess_user     (user_id),
        KEY idx_sess_activity (last_activity)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── اللغات والأدوار ───────────────────────────────────────────
$pdo->exec("INSERT IGNORE INTO languages (code,name,native,direction,is_active,is_default,sort_order) VALUES
    ('ar','Arabic','العربية','rtl',1,1,1),('en','English','English','ltr',1,0,2)");

$pdo->exec("INSERT IGNORE INTO roles (id,name,slug,description) VALUES
    (1,'Super Admin','super_admin','كامل الصلاحيات'),
    (2,'Admin','admin','إدارة المتجر'),
    (3,'Editor','editor','المحتوى فقط'),
    (4,'Customer','customer','عميل مسجل')");

$langId = (int)($pdo->query("SELECT id FROM languages WHERE is_default=1 LIMIT 1")->fetchColumn() ?: 1);

// ── حسابات الاختبار ──────────────────────────────────────────
$users = [
    ['role_id'=>1, 'first_name'=>'محمد',  'last_name'=>'المدير',   'email'=>'admin@toro.com',    'password'=>'Admin@1234',    'phone'=>'+966500000001'],
    ['role_id'=>2, 'first_name'=>'سارة',  'last_name'=>'المشرفة',  'email'=>'manager@toro.com',  'password'=>'Admin@1234',    'phone'=>'+966500000002'],
    ['role_id'=>4, 'first_name'=>'أحمد',  'last_name'=>'العميل',   'email'=>'customer@toro.com', 'password'=>'Customer@1234', 'phone'=>'+966500000003'],
];

$results = [];
foreach ($users as $u) {
    $hash = password_hash($u['password'], PASSWORD_ARGON2ID, [
        'memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1
    ]);
    $pdo->prepare("
        INSERT INTO users (role_id,first_name,last_name,email,password_hash,phone,is_active,email_verified_at,language_id)
        VALUES (:rid,:fn,:ln,:email,:hash,:phone,1,NOW(),:lid)
        ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash), first_name=VALUES(first_name), is_active=1, email_verified_at=NOW()
    ")->execute([':rid'=>$u['role_id'],':fn'=>$u['first_name'],':ln'=>$u['last_name'],
                 ':email'=>$u['email'],':hash'=>$hash,':phone'=>$u['phone'],':lid'=>$langId]);

    $id = $pdo->query("SELECT id FROM users WHERE email=".$pdo->quote($u['email']))->fetchColumn();
    $results[] = array_merge($u, ['id'=>$id, 'hash_preview'=>substr($hash,0,40).'...']);
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>TORO — حسابات تجريبية</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Segoe UI',sans-serif;background:#0a0a0a;color:#e5e5e5;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem}
.wrap{max-width:640px;width:100%}
h1{color:#B8860B;text-align:center;font-size:1.5rem;margin-bottom:.4rem;letter-spacing:2px}
p{text-align:center;color:#666;font-size:.85rem;margin-bottom:2rem}
.card{background:#141414;border:1px solid #2a2a2a;border-radius:14px;padding:1.4rem;margin-bottom:1rem}
.card h3{color:#B8860B;font-size:.9rem;margin-bottom:1rem;padding-bottom:.6rem;border-bottom:1px solid #1f1f1f}
.row{display:flex;justify-content:space-between;align-items:center;padding:.4rem 0;font-size:.85rem;border-bottom:1px solid #1a1a1a}
.row:last-child{border:none}
.label{color:#555}.value{color:#e5e5e5;font-weight:600}
.value.pass{color:#86efac;font-family:monospace;font-size:.9rem}
.value.email{color:#7dd3fc}
.value.hash{color:#444;font-size:.72rem;font-family:monospace;max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.warn{background:#1c0f00;border:1px solid #7c3d00;border-radius:10px;padding:1rem;text-align:center;color:#fb923c;font-size:.85rem;margin-top:1.5rem}
.warn code{display:block;margin-top:.5rem;color:#f97316;font-size:.82rem;background:#0d0d0d;padding:.5rem;border-radius:6px}
.ok{color:#4ade80}
</style>
</head>
<body>
<div class="wrap">
  <h1>✅ الحسابات جاهزة</h1>
  <p>انتقل إلى <a href="login.php" style="color:#B8860B">login.php</a> للاختبار</p>

  <?php foreach ($results as $r): ?>
  <div class="card">
    <h3>👤 <?= $r['first_name'].' '.$r['last_name'] ?> — <span style="color:#888;font-weight:400">ID: <?= $r['id'] ?></span></h3>
    <div class="row"><span class="label">البريد</span><span class="value email"><?= $r['email'] ?></span></div>
    <div class="row"><span class="label">كلمة المرور</span><span class="value pass"><?= $r['password'] ?></span></div>
    <div class="row"><span class="label">Hash</span><span class="value hash" title="<?= htmlspecialchars($r['hash_preview']) ?>"><?= htmlspecialchars($r['hash_preview']) ?></span></div>
    <div class="row"><span class="label">الحالة</span><span class="ok">✅ جاهز</span></div>
  </div>
  <?php endforeach ?>

  <div class="warn">
    ⚠️ <strong>احذف هذا الملف فوراً!</strong>
    <code>rm <?= htmlspecialchars(__FILE__) ?></code>
  </div>
</div>
</body>
</html>
