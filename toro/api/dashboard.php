<?php
/**
 * TORO — dashboard.php
 * الصفحة المحمية — تتحقق من الجلسة وتعرض بيانات المستخدم
 */
declare(strict_types=1);

// ── قراءة .env ────────────────────────────────────────────────
$env = [];
$envPath = __DIR__ . '/shared/config/.env';
if (file_exists($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line===''||$line[0]==='#'||!str_contains($line,'=')) continue;
        $eq=strpos($line,'='); $k=trim(substr($line,0,$eq)); $v=trim(substr($line,$eq+1));
        if(strlen($v)>=2&&(($v[0]==='"'&&$v[-1]==='"')||($v[0]==="'"&&$v[-1]==="'"))) $v=substr($v,1,-1);
        $env[$k]=$v;
    }
}

// ── DB ────────────────────────────────────────────────────────
$pdo = null;
try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $env['DB_HOST']??'localhost',$env['DB_PORT']??'3306',$env['DB_NAME']??$env['DB_DATABASE']??''),
        $env['DB_USER']??$env['DB_USERNAME']??'',
        $env['DB_PASS']??$env['DB_PASSWORD']??'',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
    );
} catch (Throwable) {}

// ── Session ───────────────────────────────────────────────────
class ToroSession {
    private string $id=''; private array $data=[]; private const COOKIE='TORO_SID'; private const TTL=86400*30;
    public function __construct(private ?PDO $pdo) {
        if(!$pdo) return;
        $this->id=$_COOKIE[self::COOKIE]??'';
        if($this->id) $this->load();
        if(!$this->id) $this->newSession();
    }
    private function newSession():void{ $this->id=bin2hex(random_bytes(32)); $this->data=[]; $this->setCookie(); $this->persist(); }
    private function load():void{
        try{
            $stmt=$this->pdo->prepare("SELECT payload,last_activity FROM sessions WHERE id=:id LIMIT 1");
            $stmt->execute([':id'=>$this->id]); $row=$stmt->fetch();
            if(!$row||(time()-(int)$row['last_activity'])>self::TTL){$this->newSession();return;}
            $this->data=json_decode($row['payload']??'{}',true)??[];
        }catch(Throwable){$this->data=[];}
    }
    public function destroy():void{
        if($this->pdo&&$this->id) try{$this->pdo->prepare("DELETE FROM sessions WHERE id=:id")->execute([':id'=>$this->id]);}catch(Throwable){}
        setcookie(self::COOKIE,'',time()-3600,'/','',$_SERVER['HTTPS']??false,true);
        $this->id=''; $this->data=[];
    }
    public function persist():void{
        if(!$this->pdo) return;
        try{$this->pdo->prepare("INSERT INTO sessions(id,user_id,ip_address,user_agent,payload,last_activity)VALUES(:id,:uid,:ip,:ua,:p,:ts)ON DUPLICATE KEY UPDATE user_id=VALUES(user_id),payload=VALUES(payload),last_activity=VALUES(last_activity)")->execute([':id'=>$this->id,':uid'=>$this->data['user_id']??null,':ip'=>$_SERVER['REMOTE_ADDR']??null,':ua'=>substr($_SERVER['HTTP_USER_AGENT']??'',0,300),':p'=>json_encode($this->data,JSON_UNESCAPED_UNICODE),':ts'=>time()]);}catch(Throwable){}
    }
    private function setCookie():void{ setcookie(self::COOKIE,$this->id,['expires'=>time()+self::TTL,'path'=>'/','secure'=>isset($_SERVER['HTTPS']),'httponly'=>true,'samesite'=>'Lax']); }
    public function set(string $k,mixed $v):void{$this->data[$k]=$v;$this->persist();}
    public function get(string $k,mixed $d=null):mixed{return $this->data[$k]??$d;}
    public function has(string $k):bool{return array_key_exists($k,$this->data);}
    public function loggedIn():bool{return $this->has('user_id')&&$this->has('user');}
    public function csrfToken():string{ if(!$this->has('csrf'))$this->set('csrf',bin2hex(random_bytes(20))); return(string)$this->get('csrf'); }
    public function csrfValid(string $t):bool{return hash_equals($this->csrfToken(),$t);}
    public function id():string{return $this->id;}
}

$sess = new ToroSession($pdo);

// ── Logout ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['_logout'])) {
    if ($sess->csrfValid($_POST['_csrf']??'')) {
        $uid = $sess->get('user_id');
        if ($uid && $pdo) {
            try { $pdo->prepare("INSERT INTO audit_logs(user_id,action,entity,entity_id,ip_address,user_agent,created_at)VALUES(:u,'logout','users',:u,:ip,:ua,NOW())")->execute([':u'=>$uid,':ip'=>$_SERVER['REMOTE_ADDR']??'',':ua'=>substr($_SERVER['HTTP_USER_AGENT']??'',0,300)]); } catch(Throwable){}
        }
        $sess->destroy();
        header('Location: login.php'); exit;
    }
}

// ── حماية الصفحة ─────────────────────────────────────────────
if (!$sess->loggedIn()) { header('Location: login.php'); exit; }

$user       = $sess->get('user');
$loggedInAt = $sess->get('logged_in_at');
$csrf       = $sess->csrfToken();

// إحصائيات سريعة من DB
$stats = ['users'=>0,'products'=>0,'orders'=>0,'sessions'=>0];
if ($pdo) {
    try {
        $stats['users']    = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_active=1 AND deleted_at IS NULL")->fetchColumn();
        $stats['products'] = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE is_active=1 AND deleted_at IS NULL")->fetchColumn();
        $stats['orders']   = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
        $stats['sessions'] = (int)$pdo->query("SELECT COUNT(*) FROM sessions WHERE last_activity > UNIX_TIMESTAMP(DATE_SUB(NOW(),INTERVAL 30 MINUTE))")->fetchColumn();
    } catch(Throwable) {}
}

// آخر الجلسات النشطة
$activeSessions = [];
if ($pdo) {
    try {
        $activeSessions = $pdo->query("
            SELECT s.id, s.ip_address, s.user_agent, s.last_activity, s.created_at,
                   u.first_name, u.last_name, u.email, r.name role_name
            FROM sessions s
            JOIN users u ON s.user_id = u.id
            JOIN roles r ON u.role_id = r.id
            WHERE s.last_activity > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 2 HOUR))
            ORDER BY s.last_activity DESC LIMIT 10
        ")->fetchAll();
    } catch(Throwable) {}
}

$roleColors = ['super_admin'=>'#C9A84C','admin'=>'#7DD3FC','editor'=>'#86EFAC','customer'=>'#D8B4FE'];
$roleColor  = $roleColors[$user['role'] ?? 'customer'] ?? '#888';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>TORO — لوحة التحكم</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;600&family=Tajawal:wght@300;400;500;700&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
:root{--gold:#C9A84C;--gold-dim:#9A7A32;--ink:#0B0B0B;--surface:#111008;--card:rgba(18,15,6,.95);--border:rgba(201,168,76,.14);--text:#E8E0CC;--muted:#7A7260;--sidebar:140px}
html,body{height:100%;background:var(--ink);font-family:'Tajawal',sans-serif;color:var(--text)}

.bg{position:fixed;inset:0;z-index:0;background:radial-gradient(ellipse 60% 50% at 10% 5%,rgba(201,168,76,.06) 0%,transparent 70%),#0B0B0B}
.bg-lines{position:absolute;inset:0;background-image:linear-gradient(rgba(201,168,76,.03) 1px,transparent 1px),linear-gradient(90deg,rgba(201,168,76,.03) 1px,transparent 1px);background-size:60px 60px;mask-image:radial-gradient(ellipse 100% 100% at 50% 0%,black 30%,transparent 100%)}

.layout{position:relative;z-index:1;min-height:100vh;display:grid;grid-template-rows:auto 1fr;grid-template-columns:1fr}

/* ── Topbar ─────────── */
.topbar{
  display:flex;align-items:center;justify-content:space-between;
  padding:.9rem 2rem;
  border-bottom:1px solid var(--border);
  background:rgba(11,11,8,.8);
  backdrop-filter:blur(16px);
  position:sticky;top:0;z-index:100;
  animation:fadeDown .5s ease both;
}
@keyframes fadeDown{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
.topbar-brand{font-family:'Cormorant Garamond',serif;font-size:1.4rem;font-weight:400;color:var(--gold);letter-spacing:.2em}
.topbar-right{display:flex;align-items:center;gap:1.2rem}
.user-pill{display:flex;align-items:center;gap:.6rem;padding:.4rem .9rem;background:rgba(201,168,76,.06);border:1px solid var(--border);border-radius:20px}
.user-avatar{width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,var(--gold),#6B5012);display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;color:#0B0B0B;flex-shrink:0}
.user-name{font-size:.82rem;color:var(--text)}
.user-role{font-size:.7rem;padding:.15rem .5rem;border-radius:6px;font-weight:600;background:rgba(201,168,76,.1)}

/* ── Logout button ──── */
.btn-logout{
  display:flex;align-items:center;gap:.45rem;
  padding:.45rem .9rem;
  background:rgba(224,82,82,.08);
  border:1px solid rgba(224,82,82,.2);
  border-radius:8px;
  color:#F08080;font-size:.8rem;
  font-family:'Tajawal',sans-serif;cursor:pointer;
  transition:all .2s;
}
.btn-logout:hover{background:rgba(224,82,82,.15);border-color:rgba(224,82,82,.4)}

/* ── Main ───────────── */
main{padding:2rem;animation:fadeUp .6s .1s ease both}
@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}

.page-title{font-family:'Cormorant Garamond',serif;font-size:1.5rem;font-weight:300;color:var(--text);margin-bottom:.3rem}
.page-sub{font-size:.8rem;color:var(--muted);margin-bottom:2rem}

/* ── Stats grid ─────── */
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:2rem}
.stat-card{
  background:var(--card);border:1px solid var(--border);
  border-radius:14px;padding:1.3rem 1.4rem;
  position:relative;overflow:hidden;
  transition:border-color .2s,transform .2s;
  animation:fadeUp .5s ease both;
}
.stat-card:hover{border-color:rgba(201,168,76,.3);transform:translateY(-2px)}
.stat-card:nth-child(1){animation-delay:.05s}
.stat-card:nth-child(2){animation-delay:.1s}
.stat-card:nth-child(3){animation-delay:.15s}
.stat-card:nth-child(4){animation-delay:.2s}
.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,rgba(201,168,76,.3),transparent)}
.stat-icon{font-size:1.4rem;margin-bottom:.7rem;display:block}
.stat-val{font-family:'Cormorant Garamond',serif;font-size:2.2rem;font-weight:400;color:var(--gold);line-height:1}
.stat-label{font-size:.75rem;color:var(--muted);margin-top:.3rem;letter-spacing:.05em}

/* ── Sessions table ─── */
.section-title{font-size:.75rem;color:var(--muted);letter-spacing:.15em;text-transform:uppercase;margin-bottom:.9rem;padding-bottom:.6rem;border-bottom:1px solid var(--border)}
.card-box{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:1.4rem;margin-bottom:1.5rem}

.sess-table{width:100%;border-collapse:collapse;font-size:.82rem}
.sess-table th{color:var(--muted);font-weight:500;padding:.55rem .7rem;text-align:right;font-size:.73rem;letter-spacing:.06em;border-bottom:1px solid var(--border)}
.sess-table td{padding:.6rem .7rem;border-bottom:1px solid rgba(201,168,76,.05);vertical-align:middle}
.sess-table tr:last-child td{border:none}
.sess-table tr:hover td{background:rgba(201,168,76,.03)}

.badge{display:inline-block;padding:.18rem .55rem;border-radius:6px;font-size:.7rem;font-weight:600}
.badge-gold{background:rgba(201,168,76,.12);color:var(--gold)}
.badge-blue{background:rgba(125,211,252,.1);color:#7DD3FC}
.badge-green{background:rgba(134,239,172,.1);color:#86EFAC}
.badge-purple{background:rgba(216,180,254,.1);color:#D8B4FE}

.current-dot{display:inline-block;width:7px;height:7px;background:#4ade80;border-radius:50%;box-shadow:0 0 6px #4ade80;flex-shrink:0}

/* Session info card */
.sess-info{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.5rem}
.info-item{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:1.1rem 1.3rem}
.info-label{font-size:.72rem;color:var(--muted);letter-spacing:.08em;text-transform:uppercase;margin-bottom:.4rem}
.info-val{font-size:.9rem;color:var(--text);font-weight:500;word-break:break-all}
.info-val.mono{font-family:monospace;font-size:.78rem;color:rgba(201,168,76,.7)}

@media(max-width:600px){.stats{grid-template-columns:1fr 1fr}.sess-info{grid-template-columns:1fr}.topbar{padding:.8rem 1rem}main{padding:1.2rem}}
</style>
</head>
<body>

<div class="bg"><div class="bg-lines"></div></div>

<div class="layout">

  <!-- ── Topbar ── -->
  <header class="topbar">
    <div class="topbar-brand">TORO</div>
    <div class="topbar-right">
      <div class="user-pill">
        <div class="user-avatar"><?= mb_substr($user['first_name']??'U', 0, 1) ?></div>
        <div>
          <div class="user-name"><?= htmlspecialchars($user['first_name']??'') ?></div>
          <div class="user-role" style="color:<?= $roleColor ?>"><?= htmlspecialchars($user['role_name']??$user['role']??'') ?></div>
        </div>
      </div>
      <form method="POST" onsubmit="return confirm('تأكيد تسجيل الخروج؟')" style="margin:0">
        <input type="hidden" name="_logout" value="1">
        <input type="hidden" name="_csrf"   value="<?= htmlspecialchars($csrf) ?>">
        <button type="submit" class="btn-logout">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          خروج
        </button>
      </form>
    </div>
  </header>

  <!-- ── Main ── -->
  <main>
    <div class="page-title">أهلاً، <?= htmlspecialchars($user['first_name']??'') ?> 👋</div>
    <div class="page-sub">آخر دخول: <?= htmlspecialchars($loggedInAt??'—') ?></div>

    <!-- Stats -->
    <div class="stats">
      <div class="stat-card">
        <span class="stat-icon">👤</span>
        <div class="stat-val"><?= $stats['users'] ?></div>
        <div class="stat-label">مستخدمون نشطون</div>
      </div>
      <div class="stat-card">
        <span class="stat-icon">🧴</span>
        <div class="stat-val"><?= $stats['products'] ?></div>
        <div class="stat-label">منتجات</div>
      </div>
      <div class="stat-card">
        <span class="stat-icon">🛒</span>
        <div class="stat-val"><?= $stats['orders'] ?></div>
        <div class="stat-label">طلبات</div>
      </div>
      <div class="stat-card">
        <span class="stat-icon">🟢</span>
        <div class="stat-val"><?= $stats['sessions'] ?></div>
        <div class="stat-label">جلسات نشطة (30د)</div>
      </div>
    </div>

    <!-- بيانات الجلسة الحالية -->
    <div class="card-box">
      <div class="section-title">الجلسة الحالية</div>
      <div class="sess-info" style="margin:0">
        <div class="info-item">
          <div class="info-label">البريد الإلكتروني</div>
          <div class="info-val"><?= htmlspecialchars($user['email']??'—') ?></div>
        </div>
        <div class="info-item">
          <div class="info-label">الدور</div>
          <div class="info-val" style="color:<?= $roleColor ?>"><?= htmlspecialchars($user['role_name']??$user['role']??'—') ?></div>
        </div>
        <div class="info-item">
          <div class="info-label">وقت الدخول</div>
          <div class="info-val"><?= htmlspecialchars($loggedInAt??'—') ?></div>
        </div>
        <div class="info-item">
          <div class="info-label">IP العميل</div>
          <div class="info-val mono"><?= htmlspecialchars($_SERVER['REMOTE_ADDR']??'—') ?></div>
        </div>
      </div>
    </div>

    <!-- جلسات نشطة -->
    <div class="card-box">
      <div class="section-title">الجلسات النشطة — آخر ساعتين (<?= count($activeSessions) ?>)</div>
      <?php if (empty($activeSessions)): ?>
        <p style="color:var(--muted);font-size:.85rem;text-align:center;padding:1.5rem 0">لا توجد جلسات نشطة</p>
      <?php else: ?>
      <div style="overflow-x:auto">
        <table class="sess-table">
          <thead>
            <tr>
              <th></th>
              <th>المستخدم</th>
              <th>الدور</th>
              <th>IP</th>
              <th>المتصفح</th>
              <th>آخر نشاط</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($activeSessions as $s):
            $isMe = ($s['ip_address'] === ($_SERVER['REMOTE_ADDR']??''));
            $ua   = substr($s['user_agent']??'', 0, 55);
            $diff = time() - (int)$s['last_activity'];
            $ago  = $diff < 60 ? 'الآن' : round($diff/60).' د';
            $rSlug= strtolower(str_replace(' ','_',$s['role_name']));
            $bc   = $roleColors[$rSlug] ?? '#888';
          ?>
          <tr>
            <td style="width:20px"><?= $isMe ? '<span class="current-dot" title="أنت"></span>' : '' ?></td>
            <td>
              <div style="font-weight:500"><?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?></div>
              <div style="font-size:.72rem;color:var(--muted)"><?= htmlspecialchars($s['email']) ?></div>
            </td>
            <td><span class="badge" style="background:<?= $bc ?>18;color:<?= $bc ?>"><?= htmlspecialchars($s['role_name']) ?></span></td>
            <td style="font-family:monospace;font-size:.78rem;color:rgba(201,168,76,.6)"><?= htmlspecialchars($s['ip_address']??'—') ?></td>
            <td style="color:var(--muted);max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($s['user_agent']??'') ?>"><?= htmlspecialchars($ua) ?></td>
            <td style="white-space:nowrap"><span class="badge badge-green"><?= $ago ?></span></td>
          </tr>
          <?php endforeach ?>
          </tbody>
        </table>
      </div>
      <?php endif ?>
    </div>

  </main>
</div>

</body>
</html>
