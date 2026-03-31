<?php
declare(strict_types=1);

/**
 * api/tests/test_categories_route.php
 *
 * ملف اختبار للتحقق من مسار /api/categories
 * يتحقق من:
 *  1. تحميل bootstrap وتعريف الثوابت
 *  2. وجود ملفات stub في api/v1/ (إصلاح مسار $baseDir)
 *  3. وجود ملف categories route
 *  4. وجود ملفات النماذج (models)
 *  5. اتصال قاعدة البيانات
 */

require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$results = [];
$allPassed = true;

// -------------------------------------------------------
// 1. ثوابت Bootstrap
// -------------------------------------------------------
$results['bootstrap_constants'] = [
    'API_BASE_PATH'    => defined('API_BASE_PATH')    ? ['ok' => true,  'value' => API_BASE_PATH]    : ['ok' => false, 'error' => 'Not defined'],
    'API_VERSION_PATH' => defined('API_VERSION_PATH') ? ['ok' => true,  'value' => API_VERSION_PATH] : ['ok' => false, 'error' => 'Not defined'],
    'IS_VERSIONED_API' => defined('IS_VERSIONED_API') ? ['ok' => IS_VERSIONED_API === true, 'value' => IS_VERSIONED_API] : ['ok' => false, 'error' => 'Not defined'],
];
foreach ($results['bootstrap_constants'] as $k => $v) {
    if (!$v['ok']) $allPassed = false;
}

// -------------------------------------------------------
// 2. ملفات Stub في api/v1/ (إصلاح مسار $baseDir)
// -------------------------------------------------------
$v1Dir = API_BASE_PATH . '/v1';
$stubFiles = [
    'bootstrap'             => $v1Dir . '/bootstrap.php',
    'ResponseFormatter'     => $v1Dir . '/shared/core/ResponseFormatter.php',
    'safe_helpers'          => $v1Dir . '/shared/helpers/safe_helpers.php',
    'SeoAutoManager'        => $v1Dir . '/shared/helpers/SeoAutoManager.php',
    'AuditLogger'           => $v1Dir . '/shared/helpers/AuditLogger.php',
    'db_config'             => $v1Dir . '/shared/config/db.php',
];
$results['stub_files'] = [];
foreach ($stubFiles as $name => $path) {
    $exists = is_file($path);
    if (!$exists) $allPassed = false;
    $results['stub_files'][$name] = ['ok' => $exists, 'path' => $path];
}

// -------------------------------------------------------
// 3. ملف categories route
// -------------------------------------------------------
$categoriesRoute = $v1Dir . '/routes/categories.php';
$routeExists = is_file($categoriesRoute);
if (!$routeExists) $allPassed = false;
$results['categories_route'] = ['ok' => $routeExists, 'path' => $categoriesRoute];

// -------------------------------------------------------
// 4. ملفات النماذج (models)
// -------------------------------------------------------
$modelsBase = $v1Dir . '/models/categories';
$modelFiles = [
    'repository'  => $modelsBase . '/repositories/PdoCategoriesRepository.php',
    'validator'   => $modelsBase . '/validators/CategoriesValidator.php',
    'service'     => $modelsBase . '/services/CategoriesService.php',
    'controller'  => $modelsBase . '/controllers/CategoriesController.php',
];
$results['model_files'] = [];
foreach ($modelFiles as $name => $path) {
    $exists = is_file($path);
    if (!$exists) $allPassed = false;
    $results['model_files'][$name] = ['ok' => $exists, 'path' => $path];
}

// -------------------------------------------------------
// 5. اتصال قاعدة البيانات
// -------------------------------------------------------
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if ($pdo instanceof PDO) {
    try {
        $stmt = $pdo->query('SELECT COUNT(*) AS cnt FROM categories LIMIT 1');
        $row  = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        $results['database'] = ['ok' => true, 'categories_count_query' => $row ?? 'ok'];
    } catch (\Throwable $e) {
        $results['database'] = ['ok' => false, 'error' => $e->getMessage()];
        $allPassed = false;
    }
} else {
    $results['database'] = ['ok' => false, 'error' => 'ADMIN_DB not initialized'];
    $allPassed = false;
}

// -------------------------------------------------------
// 6. اختبار تحميل stub للـ bootstrap بدون خطأ
// (يختلف عن فحص الوجود في القسم 2 — هذا يتحقق من التنفيذ الفعلي بدون أخطاء)
// -------------------------------------------------------
$v1Bootstrap = $v1Dir . '/bootstrap.php';
if (is_file($v1Bootstrap)) {
    try {
        // require_once is safe here: if already loaded, PHP skips re-execution.
        // We verify that the stub does NOT throw even when API_BASE_PATH is already defined.
        require_once $v1Bootstrap;
        $results['v1_bootstrap_load'] = [
            'ok'      => true,
            'message' => 'Stub executed without error (API_BASE_PATH already defined — no-op as expected)',
        ];
    } catch (\Throwable $e) {
        $results['v1_bootstrap_load'] = ['ok' => false, 'error' => $e->getMessage()];
        $allPassed = false;
    }
} else {
    $results['v1_bootstrap_load'] = ['ok' => false, 'error' => 'Stub file not found'];
    $allPassed = false;
}

// -------------------------------------------------------
// الخلاصة
// -------------------------------------------------------
http_response_code($allPassed ? 200 : 500);
echo json_encode([
    'success'  => $allPassed,
    'message'  => $allPassed ? 'All categories route checks passed' : 'Some checks failed — see results',
    'results'  => $results,
    'php_version' => PHP_VERSION,
    'timestamp'   => date('c'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
