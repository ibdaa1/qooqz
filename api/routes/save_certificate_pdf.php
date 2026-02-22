<?php
/**
 * api/routes/save_certificate_pdf.php
 *
 * POST /api/save_certificate_pdf
 * Body: { "issued_id": <int>, "request_id": <int>, "lang": "<string>" }
 *
 * Renders the certificate HTML template, converts it to a PDF file using
 * Chromium headless, saves it under /uploads/certificates/pdf/ and updates
 * certificates_issued.pdf_path in the database.
 *
 * Returns JSON: { success, data: { issued_id, pdf_path } }
 */
declare(strict_types=1);

$baseDir = dirname(__DIR__);
require_once $baseDir . '/bootstrap.php';
require_once $baseDir . '/shared/core/ResponseFormatter.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');

$sessionUser = $_SESSION['user'] ?? [];
if (!isset($sessionUser['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

/** @var PDO $pdo */
$pdo = $GLOBALS['ADMIN_DB'] ?? null;
if (!$pdo instanceof PDO) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $raw   = file_get_contents('php://input');
    $input = $raw ? (json_decode($raw, true) ?? []) : [];

    $issuedId  = isset($input['issued_id'])  && is_numeric($input['issued_id'])  ? (int)$input['issued_id']  : null;
    $requestId = isset($input['request_id']) && is_numeric($input['request_id']) ? (int)$input['request_id'] : null;
    $lang      = isset($input['lang']) ? trim($input['lang']) : 'ar';

    if (!$issuedId || !$requestId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'issued_id and request_id required'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── Fetch issued record ───────────────────────────────────────────────
    $stmt = $pdo->prepare("SELECT * FROM certificates_issued WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $issuedId]);
    $issued = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$issued) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Issued record not found'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── Fetch request + template data ────────────────────────────────────
    $sql = "
        SELECT cr.*,
               c_imp.name AS importer_country,
               e.store_name AS exporter_name,
               ce.certificate_version,
               ce.scope,
               ci.certificate_number,
               ci.issued_at,
               ci.verification_code,
               ci.qr_code_path,
               ci.pdf_path
        FROM certificates_requests cr
        LEFT JOIN countries c_imp ON c_imp.id = cr.importer_country_id
        LEFT JOIN entities e ON e.id = cr.entity_id
        LEFT JOIN certificate_editions ce ON ce.id = cr.certificate_edition_id
        LEFT JOIN certificates_issued ci ON ci.id = cr.issued_id
        WHERE cr.id = :id
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $requestId]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Request not found'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── Fetch items ───────────────────────────────────────────────────────
    require_once API_VERSION_PATH . '/models/certificates/repositories/Pdocertificatesrequestitemsrepository.php';
    $itemRepo = new PdoCertificatesRequestItemsRepository($pdo);
    $items = $itemRepo->getItemsWithDetails($requestId, $lang);

    // ── Determine template version ────────────────────────────────────────
    $version = $data['certificate_version'] ?? null;
    if (!$version && isset($data['scope'])) {
        $version = $lang . '_' . $data['scope'];
    }
    if (!$version) $version = 'ar_gcc';

    // ── Load template config ──────────────────────────────────────────────
    require_once API_VERSION_PATH . '/models/certificates/repositories/PdoCertificatesTemplatesRepository.php';
    $templateRepo = new PdoCertificatesTemplatesRepository($pdo);
    $template = $templateRepo->findByCode($version) ?? [
        'font_family'      => 'Arial',
        'font_size'        => '12.00',
        'background_image' => null,
        'table_start_x'    => '10.00',
        'table_start_y'    => '50.00',
        'table_row_height' => '12.00',
        'table_max_rows'   => 12,
        'qr_x'             => '180.00',
        'qr_y'             => '250.00',
        'qr_width'         => '50.00',
        'qr_height'        => '50.00',
        'signature_x'      => '100.00',
        'signature_y'      => '250.00',
        'signature_width'  => '50.00',
        'signature_height' => '50.00',
        'stamp_x'          => '150.00',
        'stamp_y'          => '250.00',
        'stamp_width'      => '50.00',
        'stamp_height'     => '50.00',
        'logo_x'           => '10.00',
        'logo_y'           => '10.00',
        'logo_width'       => '50.00',
        'logo_height'      => '50.00',
    ];

    // ── Resolve document root ─────────────────────────────────────────────
    $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? dirname($baseDir), '/');

    // ── Normalise background image path ──────────────────────────────────
    if (!empty($template['background_image'])) {
        $bgRelative = $template['background_image'];
        $bgFullPath = $docRoot . '/' . $bgRelative;
        if (!file_exists($bgFullPath)) {
            $bgAlt = str_replace('admin/templates/', 'admin/assets/templates/', $bgRelative);
            if (file_exists($docRoot . '/' . $bgAlt)) {
                $template['background_image'] = $bgAlt;
            }
        }
    }

    // ── Build QR path (file-system absolute for offline rendering) ────────
    $verificationCode = $issued['verification_code'] ?? '';
    $qrPath           = '';

    if (!empty($issued['qr_code_path'])) {
        $qrAbsolute = $docRoot . $issued['qr_code_path'];
        if (file_exists($qrAbsolute)) {
            $qrPath = _cert_pdf_data_uri($qrAbsolute);
        }
    }

    // If not saved yet — download QR on the fly for this PDF
    if ($qrPath === '' && $verificationCode !== '') {
        $scheme    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host      = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $verifyUrl = $scheme . '://' . $host . '/api/verify_certificate?code=' . rawurlencode($verificationCode);
        $qrApiUrl  = 'https://api.qrserver.com/v1/create-qr-code/?'
                   . http_build_query(['data' => $verifyUrl, 'size' => '200x200', 'format' => 'png']);
        $ctx       = stream_context_create(['http' => ['timeout' => 10, 'ignore_errors' => true]]);
        $qrPng     = @file_get_contents($qrApiUrl, false, $ctx);
        if ($qrPng !== false && strlen($qrPng) > 10) {
            $qrPath = 'data:image/png;base64,' . base64_encode($qrPng);
        }
    }

    // Background as data URI for offline Chromium rendering
    if (!empty($template['background_image'])) {
        $bgFull = $docRoot . '/' . $template['background_image'];
        if (file_exists($bgFull)) {
            $template['background_image_data_uri'] = _cert_pdf_data_uri($bgFull);
        }
    }

    $signaturePath = '';
    $stampPath     = '';

    // ── Render HTML via template ──────────────────────────────────────────
    $adminDir     = dirname($baseDir) . '/admin';
    $templateFile = $adminDir . "/assets/templates/certificates/{$version}.php";
    if (!file_exists($templateFile)) {
        $templateFile = $adminDir . "/assets/templates/certificates/ar_gcc.php";
    }

    // Render to string — suppress the "Print" button for PDF output
    define('CERT_PDF_MODE', true);
    ob_start();
    include $templateFile;
    $html = ob_get_clean();

    // ── Write temp HTML ───────────────────────────────────────────────────
    $tmpHtml = sys_get_temp_dir() . '/cert_' . $issuedId . '_' . time() . '.html';
    if (file_put_contents($tmpHtml, $html) === false) {
        throw new RuntimeException('Cannot write temp HTML file');
    }

    // ── Ensure PDF output directory exists ───────────────────────────────
    $pdfDir = $docRoot . '/uploads/certificates/pdf';
    if (!is_dir($pdfDir)) {
        @mkdir($pdfDir, 0755, true);
    }

    $pdfFileName = 'cert_' . $requestId . '_' . $issuedId . '.pdf';
    $pdfFullPath = $pdfDir . '/' . $pdfFileName;
    $pdfWebPath  = '/uploads/certificates/pdf/' . $pdfFileName;

    // ── Run Chromium headless ─────────────────────────────────────────────
    $chromiumBin = _cert_pdf_find_chromium();
    if (!$chromiumBin) {
        @unlink($tmpHtml);
        throw new RuntimeException('Chromium not found. Cannot generate PDF.');
    }

    $cmd = escapeshellarg($chromiumBin)
         . ' --headless'
         . ' --no-sandbox'
         . ' --disable-gpu'
         . ' --disable-software-rasterizer'
         . ' --run-all-compositor-stages-before-draw'
         . ' --virtual-time-budget=5000'
         . ' --print-to-pdf=' . escapeshellarg($pdfFullPath)
         . ' ' . escapeshellarg('file://' . $tmpHtml)
         . ' 2>/dev/null';

    exec($cmd, $output, $exitCode);
    @unlink($tmpHtml);

    if ($exitCode !== 0 || !file_exists($pdfFullPath) || filesize($pdfFullPath) < 100) {
        throw new RuntimeException('Chromium PDF generation failed (exit ' . $exitCode . ')');
    }

    // ── Update DB ─────────────────────────────────────────────────────────
    $update = $pdo->prepare(
        "UPDATE certificates_issued SET pdf_path = :pdf WHERE id = :id"
    );
    $update->execute([':pdf' => $pdfWebPath, ':id' => $issuedId]);

    echo json_encode([
        'success' => true,
        'message' => 'PDF saved successfully',
        'data'    => [
            'issued_id' => $issuedId,
            'pdf_path'  => $pdfWebPath,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}

/* ── Helpers ─────────────────────────────────────────────────────────────── */

/**
 * Convert a local file to a base64 data URI so Chromium can render it offline.
 */
function _cert_pdf_data_uri(string $filePath): string
{
    $content = @file_get_contents($filePath);
    if ($content === false) return '';

    $ext  = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mime = match ($ext) {
        'png'  => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        default => 'application/octet-stream',
    };
    return 'data:' . $mime . ';base64,' . base64_encode($content);
}

/**
 * Locate the Chromium/Chrome binary.
 */
function _cert_pdf_find_chromium(): ?string
{
    $candidates = [
        getenv('CHROMIUM_BIN') ?: '',
        '/usr/bin/chromium',
        '/usr/bin/chromium-browser',
        '/usr/bin/google-chrome',
        '/usr/bin/google-chrome-stable',
        '/usr/local/bin/chromium',
    ];
    foreach ($candidates as $bin) {
        if ($bin !== '' && is_executable($bin)) {
            return $bin;
        }
    }
    return null;
}
