<?php
/**
 * api/routes/print_certificate.php
 * Professional print view for certificates (returns HTML)
 */
declare(strict_types=1);

$baseDir = dirname(__DIR__);
require_once $baseDir . '/bootstrap.php';
// Session check
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: text/html; charset=utf-8');

$sessionUser = $_SESSION['user'] ?? [];
if (!isset($sessionUser['id'])) {
    http_response_code(401);
    die("Unauthorized access.");
}

$id   = isset($_GET['id'])   && is_numeric($_GET['id'])   ? (int)$_GET['id']   : null;
$lang = isset($_GET['lang']) ? $_GET['lang']              : 'ar';

if (!$id) {
    http_response_code(400);
    die("Invalid request ID.");
}

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    http_response_code(500);
    die("Database error.");
}

try {
    // Fetch Request Data
    $sql = "
        SELECT cr.*, 
               c_imp.name AS importer_country,
               e.store_name AS exporter_name,
               ce.certificate_version,
               ce.scope,
               ci.certificate_number,
               ci.issued_at
        FROM certificates_requests cr
        LEFT JOIN countries c_imp ON c_imp.id = cr.importer_country_id
        LEFT JOIN entities e ON e.id = cr.entity_id
        LEFT JOIN certificate_editions ce ON ce.id = cr.certificate_edition_id
        LEFT JOIN certificates_issued ci ON ci.id = cr.issued_id
        WHERE cr.id = :id
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        http_response_code(404);
        die("Request not found.");
    }

    // Fetch Items with details & translations
    require_once API_VERSION_PATH . '/models/certificates/repositories/Pdocertificatesrequestitemsrepository.php';
    $itemRepo = new PdoCertificatesRequestItemsRepository($pdo);
    $items = $itemRepo->getItemsWithDetails($id, $lang);

    // Determine Template
    $version = $data['certificate_version'] ?? null;
    if (!$version && isset($data['scope'])) {
        $version = $lang . '_' . $data['scope'];
    }
    if (!$version) $version = 'ar_gcc'; // Hard fallback

    $adminDir = dirname($baseDir) . '/admin';
    $templatePath = $adminDir . "/assets/templates/certificates/{$version}.php";

    if (!file_exists($templatePath)) {
        // Attempt fallback to ar_gcc
        $templatePath = $adminDir . "/assets/templates/certificates/ar_gcc.php";
        if (!file_exists($templatePath)) {
            die("Template not found: {$version}");
        }
    }

    // Render Template
    // Variables $data and $items are available inside the template
    include $templatePath;

} catch (Throwable $e) {
    http_response_code(500);
    echo "<h1>Error Generating Certificate</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    if (ENVIRONMENT === 'development' || true) { // Force true for debugging
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    }
}
