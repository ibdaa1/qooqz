<?php
declare(strict_types=1);
session_start();

// إعدادات اللغة والاتجاه
$lang = $_SESSION['lang'] ?? 'ar';
$direction = ($lang === 'ar') ? 'rtl' : 'ltr';

/**
 * دالة جلب البيانات من الـ API
 */
function fetchProductsFromApi($search = '', $page = 1) {
    // رابط الـ API الخاص بك
    $apiUrl = "http://mzmz.rf.gd/api/product?page=" . $page;
    if (!empty($search)) {
        $apiUrl .= "&search=" . urlencode($search);
    }

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    
    $response = curl_exec($ch);
    $data = json_decode((string)$response, true);
    curl_close($ch);
    
    return $data;
}

// جلب البيانات بناءً على البحث والترقيم
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$apiRes = fetchProductsFromApi($search, $page);

// استخراج المصفوفة الصحيحة (بناءً على الـ JSON الذي أرسلته)
$products = [];
if (isset($apiRes['success']) && $apiRes['success'] == true) {
    // التعديل الجوهري هنا: الدخول مباشرة لـ ['data']
    $products = $apiRes['data'] ?? [];
}

$pageTitle = ($lang === 'ar' ? 'متجرنا - المنتجات' : 'Our Shop - Products');
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $direction ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7f6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .product-card {
            border: none; border-radius: 15px; transition: all 0.3s ease; background: #fff;
        }
        .product-card:hover { transform: translateY(-10px); box-shadow: 0 15px 30px rgba(0,0,0,0.1); }
        .product-img-container {
            height: 250px; border-radius: 15px 15px 0 0; overflow: hidden; background: #f9f9f9;
            display: flex; align-items: center; justify-content: center;
        }
        .product-img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .price-tag { color: #2ecc71; font-weight: bold; font-size: 1.2rem; }
        .btn-view { border-radius: 20px; padding: 8px 20px; }
    </style>
</head>
<body>

<div class="container py-5">
    <h2 class="text-center mb-5 fw-bold"><?= $pageTitle ?></h2>

    <div class="row mb-5 justify-content-center">
        <div class="col-md-6">
            <form method="GET" class="input-group shadow-sm">
                <input type="text" name="search" class="form-control border-0 p-3" placeholder="ابحث عن المنتجات..." value="<?= htmlspecialchars($search) ?>">
                <button class="btn btn-primary px-4" type="submit"><i class="fas fa-search"></i></button>
            </form>
        </div>
    </div>

    <div class="row g-4">
        <?php if (!empty($products)): ?>
            <?php foreach ($products as $p): ?>
                <div class="col-md-4 col-lg-3">
                    <div class="card h-100 product-card shadow-sm">
                        <div class="product-img-container">
                            <?php 
                                $img = (!empty($p['primary_image'])) ? $p['primary_image'] : 'https://via.placeholder.com/300x300?text=No+Image';
                                // إذا كانت الصورة مسار داخلي أضف الدومين
                                if (strpos($img, 'http') === false) $img = "https://mzmz.rf.gd" . $img;
                            ?>
                            <img src="<?= $img ?>" class="product-img" alt="<?= htmlspecialchars($p['display_name'] ?? $p['sku']) ?>">
                        </div>

                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title fs-6 fw-bold">
                                <?= htmlspecialchars($p['display_name'] ?: ($p['sku'] ?: 'منتج بدون اسم')) ?>
                            </h5>
                            
                            <p class="text-muted small mb-3">SKU: <?= htmlspecialchars($p['sku']) ?></p>

                            <div class="mt-auto d-flex justify-content-between align-items-center">
                                <span class="price-tag">
                                    <?= !empty($p['price']) ? number_format((float)$p['price'], 2) . ' ر.س' : 'حسب الطلب' ?>
                                </span>
                                <a href="product_details.php?id=<?= $p['id'] ?>" class="btn btn-outline-primary btn-sm btn-view">
                                    <i class="fas fa-eye me-1"></i> عرض
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5">
                <div class="alert alert-info">عذراً، لم يتم العثور على أي منتجات.</div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>