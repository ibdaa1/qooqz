<?php
declare(strict_types=1);
session_start();

$lang = $_SESSION['lang'] ?? 'ar';
$direction = ($lang === 'ar') ? 'rtl' : 'ltr';

/**
 * دالة جلب البيانات مع دعم استكشاف الأخطاء
 */
function fetchFromApi(string $path) {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $baseUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . '/api';
    // إضافة public=1 لضمان تخطي حماية الجلسة في الـ API للزوار
    $url = $baseUrl . $path . (strpos($path, '?') !== false ? '&' : '?') . 'public=1';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);

    $result = curl_exec($ch);
    $data = json_decode((string)$result, true);
    curl_close($ch);
    return $data;
}

function e($v): string {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

$vendorId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$vendors = [];
$vendorData = null;

if ($vendorId > 0) {
    $apiRes = fetchFromApi("/vendors?id=$vendorId");
    
    // فحص ذكي: إذا كان الـ API يعيد البيانات داخل data أو يعيدها مباشرة
    if (isset($apiRes['success']) && $apiRes['success']) {
        if (isset($apiRes['data']['id'])) {
            // الحالة 1: البيانات داخل مفتاح data (وهو المتوقع)
            $vendorData = $apiRes['data'];
        } elseif (isset($apiRes['data'][0])) {
            // الحالة 2: البيانات تأتي كمصفوفة داخل data
            $vendorData = $apiRes['data'][0];
        }
    } elseif (isset($apiRes['id'])) {
        // الحالة 3: الـ API يعيد بيانات المتجر مباشرة بدون مفتاح success
        $vendorData = $apiRes;
    }
} else {
    $apiRes = fetchFromApi("/vendors");
    if (isset($apiRes['success']) && $apiRes['success']) {
        $vendors = $apiRes['data'] ?? [];
    }
}

$pageTitle = ($vendorId > 0 && $vendorData) ? $vendorData['store_name'] : ($lang === 'ar' ? 'المتاجر' : 'Vendors');
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $direction ?>">
<head>
    <meta charset="utf-8">
    <title><?= e($pageTitle) ?> | QOOQZ</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        :root { --primary: #0d6efd; --bg: #f4f7f6; }
        body { background-color: var(--bg); font-family: 'Segoe UI', sans-serif; }
        .vendor-hero { height: 350px; background-size: cover; background-position: center; border-radius: 0 0 30px 30px; position: relative; background-color: #ccc; }
        .vendor-hero::after { content: ''; position: absolute; inset: 0; background: linear-gradient(to bottom, transparent, rgba(0,0,0,0.5)); border-radius: 0 0 30px 30px; }
        .profile-container { margin-top: -100px; position: relative; z-index: 5; }
        .main-card { background: #fff; border-radius: 20px; border: none; box-shadow: 0 10px 40px rgba(0,0,0,0.1); padding: 30px; }
        .vendor-logo-large { width: 160px; height: 160px; border-radius: 20px; object-fit: cover; border: 6px solid #fff; box-shadow: 0 5px 20px rgba(0,0,0,0.1); background: #fff; }
        .contact-box { background: #f8f9fa; border-radius: 12px; padding: 12px; margin-bottom: 10px; border: 1px solid #eee; font-size: 0.95rem; }
        .stats-badge { background: #eef2ff; color: #4338ca; padding: 8px 15px; border-radius: 10px; font-weight: 600; display: inline-block; margin: 5px; font-size: 0.85rem; }
        .grid-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
        .v-card { background: #fff; border-radius: 15px; overflow: hidden; text-decoration: none; color: inherit; transition: 0.3s; height: 100%; border: 1px solid #eee; }
        .v-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.08); }
    </style>
</head>
<body>

<?php include __DIR__ . '/partials/header.php'; ?>

<main class="py-5">
    <div class="container">

    <?php if ($vendorId > 0): ?>
        <?php if ($vendorData): ?>
            <div class="vendor-hero shadow" style="background-image: url('<?= e($vendorData['cover_image_url'] ?: '/assets/images/default-cover.jpg') ?>');"></div>
            
            <div class="profile-container container">
                <div class="main-card">
                    <div class="row">
                        <div class="col-lg-3 text-center">
                            <img src="<?= e($vendorData['logo_url'] ?: '/assets/images/no-vendor.png') ?>" class="vendor-logo-large mb-3" alt="Logo">
                        </div>
                        <div class="col-lg-6 mt-lg-4 text-center text-lg-start">
                            <h1 class="fw-bold mb-1"><?= e($vendorData['store_name']) ?></h1>
                            <p class="text-muted mb-3"><?= e($vendorData['description'] ?: 'متجر معتمد في منصة QOOQZ') ?></p>
                            
                            <div class="d-flex flex-wrap justify-content-center justify-content-lg-start">
                                <div class="stats-badge">⭐ 4.8</div>
                                <div class="stats-badge">📍 <?= e($vendorData['city_name'] ?? 'نشط') ?></div>
                                <?php if(!empty($vendorData['is_verified'])): ?>
                                    <div class="stats-badge text-success" style="background: #dcfce7;">✔️ موثق</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-lg-3 mt-lg-4">
                            <div class="contact-box">📞 <?= e($vendorData['phone'] ?: ($vendorData['mobile'] ?: '---')) ?></div>
                            <div class="contact-box">📧 <?= e($vendorData['email'] ?: '---') ?></div>
                            <div class="contact-box">🏠 <?= e($vendorData['address'] ?: '---') ?></div>
                        </div>
                    </div>
                    
                    <hr class="my-5">
                    
                    <div class="row">
                        <div class="col-12">
                            <h4 class="fw-bold mb-4">🛒 منتجات المتجر</h4>
                            <div class="alert alert-light border text-center py-5">
                                <p class="text-muted mb-0">قريباً.. سيتم إدراج المنتجات التابعة لـ <?= e($vendorData['store_name']) ?> هنا.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-warning text-center py-5 shadow-sm">
                <h4>عذراً، لم نتمكن من جلب بيانات التاجر!</h4>
                <p>تأكد أن التاجر نشط أو حاول مرة أخرى لاحقاً.</p>
                <a href="vendors.php" class="btn btn-primary mt-3">العودة للمتاجر</a>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <h2 class="mb-4 fw-bold">الشركاء والمتاجر</h2>
        <div class="grid-container">
            <?php foreach ($vendors as $v): ?>
            <a href="vendors.php?id=<?= $v['id'] ?>" class="v-card">
                <div style="height: 140px; background: url('<?= e($v['cover_image_url'] ?: '/assets/images/default-cover.jpg') ?>') center/cover;"></div>
                <div class="p-3 text-center">
                    <img src="<?= e($v['logo_url'] ?: '/assets/images/no-vendor.png') ?>" style="width: 75px; height: 75px; border-radius: 50%; margin-top: -55px; border: 4px solid #fff; object-fit: cover;">
                    <h5 class="mt-2 fw-bold mb-1"><?= e($v['store_name']) ?></h5>
                    <p class="small text-muted mb-0"><?= e($v['city_name'] ?? 'متجر موثق') ?></p>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    </div>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>