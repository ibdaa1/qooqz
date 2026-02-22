<?php
/**
 * api/routes/generate_certificate_files.php
 *
 * POST /api/generate_certificate_files
 * Body: { "issued_id": <int>, "request_id": <int> }
 *
 * Generates a QR code PNG and saves it under /uploads/certificates/qr/.
 * Updates certificates_issued with the new qr_code_path.
 * Returns JSON with { qr_code_path, pdf_path }.
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

    if (!$issuedId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'issued_id required'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Fetch issued record
    $stmt = $pdo->prepare("SELECT * FROM certificates_issued WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $issuedId]);
    $issued = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$issued) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Issued record not found'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $verificationCode = $issued['verification_code'] ?? '';
    $reqId = $requestId ?? ($issued['request_id'] ?? 0);

    // ── Build verification URL ────────────────────────────────────────────
    $scheme     = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host       = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $verifyUrl  = $scheme . '://' . $host . '/api/verify_certificate?code=' . rawurlencode($verificationCode);

    // ── Ensure uploads directory exists ──────────────────────────────────
    $docRoot  = rtrim($_SERVER['DOCUMENT_ROOT'] ?? dirname($baseDir), '/');
    $qrDir    = $docRoot . '/uploads/certificates/qr';
    if (!is_dir($qrDir)) {
        @mkdir($qrDir, 0755, true);
    }

    // ── Download QR PNG from qrserver.com ────────────────────────────────
    $qrUrl     = 'https://api.qrserver.com/v1/create-qr-code/?'
               . http_build_query(['data' => $verifyUrl, 'size' => '200x200', 'format' => 'png']);

    $qrCodePath = '';
    $ctx = stream_context_create([
        'http' => ['timeout' => 10, 'ignore_errors' => true, 'user_agent' => 'QooqzCertificates/1.0'],
    ]);
    $qrPng = @file_get_contents($qrUrl, false, $ctx);

    if ($qrPng !== false && strlen($qrPng) > 10) {
        $qrFileName = 'qr_' . $issuedId . '_' . time() . '.png';
        $qrFullPath = $qrDir . '/' . $qrFileName;
        if (file_put_contents($qrFullPath, $qrPng) !== false) {
            $qrCodePath = '/uploads/certificates/qr/' . $qrFileName;
        }
    }

    // If saving failed (e.g. no network), fall back to the dynamic endpoint URL
    if ($qrCodePath === '') {
        $qrCodePath = '/api/generate_qr?code=' . rawurlencode($verificationCode);
    }

    // ── PDF path: trigger server-side PDF generation via save_certificate_pdf ──
    $lang    = $issued['language_code'] ?? 'ar';
    $pdfPath = '';

    // Re-use the same logic as save_certificate_pdf (inline, to avoid HTTP self-call)
    $pdfDir = $docRoot . '/uploads/certificates/pdf';
    if (!is_dir($pdfDir)) {
        @mkdir($pdfDir, 0755, true);
    }
    $pdfFileName = 'cert_' . (int)$reqId . '_' . $issuedId . '.pdf';
    $pdfFullPath = $pdfDir . '/' . $pdfFileName;
    $pdfWebPath  = '/uploads/certificates/pdf/' . $pdfFileName;

    // Only generate if not already on disk
    if (!file_exists($pdfFullPath)) {
        $pdfPath = _gcf_generate_pdf($pdo, $issuedId, (int)$reqId, $lang, $qrCodePath, $pdfFullPath, $docRoot);
    }
    if ($pdfPath === '' || $pdfPath === null) {
        // Fallback: point to the HTML print view
        $pdfPath = '/api/print_certificate?id=' . (int)$reqId . '&lang=' . rawurlencode($lang);
    } else {
        $pdfPath = $pdfWebPath;
    }

    // ── Persist changes to certificates_issued ──────────────────────────
    $update = $pdo->prepare(
        "UPDATE certificates_issued SET qr_code_path = :qr, pdf_path = :pdf WHERE id = :id"
    );
    $update->execute([
        ':qr'  => $qrCodePath,
        ':pdf' => $pdfPath,
        ':id'  => $issuedId,
    ]);

    echo json_encode([
        'success'        => true,
        'message'        => 'OK',
        'data'           => [
            'issued_id'    => $issuedId,
            'qr_code_path' => $qrCodePath,
            'pdf_path'     => $pdfPath,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}

/* ── PDF generation helper ───────────────────────────────────────────────── */

/**
 * Render the certificate HTML template and convert it to a PDF file using
 * Chromium headless. Returns the output file path on success, or '' on failure.
 */
function _gcf_generate_pdf(
    PDO    $pdo,
    int    $issuedId,
    int    $requestId,
    string $lang,
    string $qrSavedPath,
    string $pdfOutputPath,
    string $docRoot
): string {
    try {
        // Fetch request data
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
        if (!$data) return '';

        // Fetch items
        $itemsRepoFile = API_VERSION_PATH . '/models/certificates/repositories/Pdocertificatesrequestitemsrepository.php';
        if (!file_exists($itemsRepoFile)) return '';
        require_once $itemsRepoFile;
        $itemRepo = new PdoCertificatesRequestItemsRepository($pdo);
        $items = $itemRepo->getItemsWithDetails($requestId, $lang);

        // Template version
        $version = $data['certificate_version'] ?? null;
        if (!$version && isset($data['scope'])) {
            $version = $lang . '_' . $data['scope'];
        }
        if (!$version) $version = 'ar_gcc';

        // Template config
        $tplRepoFile = API_VERSION_PATH . '/models/certificates/repositories/PdoCertificatesTemplatesRepository.php';
        if (!file_exists($tplRepoFile)) return '';
        require_once $tplRepoFile;
        $templateRepo = new PdoCertificatesTemplatesRepository($pdo);
        $template = $templateRepo->findByCode($version) ?? [
            'font_family' => 'Arial', 'font_size' => '12.00',
            'background_image' => null,
            'table_start_x' => '10.00', 'table_start_y' => '50.00',
            'table_row_height' => '12.00', 'table_max_rows' => 12,
            'qr_x' => '180.00', 'qr_y' => '250.00',
            'qr_width' => '50.00', 'qr_height' => '50.00',
            'signature_x' => '100.00', 'signature_y' => '250.00',
            'signature_width' => '50.00', 'signature_height' => '50.00',
            'stamp_x' => '150.00', 'stamp_y' => '250.00',
            'stamp_width' => '50.00', 'stamp_height' => '50.00',
            'logo_x' => '10.00', 'logo_y' => '10.00',
            'logo_width' => '50.00', 'logo_height' => '50.00',
        ];

        // Normalise background image path
        if (!empty($template['background_image'])) {
            $bgAlt = str_replace('admin/templates/', 'admin/assets/templates/', $template['background_image']);
            if (!file_exists($docRoot . '/' . $template['background_image']) && file_exists($docRoot . '/' . $bgAlt)) {
                $template['background_image'] = $bgAlt;
            }
            $bgFull = $docRoot . '/' . $template['background_image'];
            if (file_exists($bgFull)) {
                $template['background_image_data_uri'] = _gcf_data_uri($bgFull);
            }
        }

        // QR as data URI
        $qrPath = '';
        if ($qrSavedPath !== '') {
            $qrAbsolute = $docRoot . $qrSavedPath;
            if (file_exists($qrAbsolute)) {
                $qrPath = _gcf_data_uri($qrAbsolute);
            }
        }
        if ($qrPath === '') {
            $verificationCode = $data['verification_code'] ?? '';
            if ($verificationCode !== '') {
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
        }

        $signaturePath = '';
        $stampPath     = '';

        // Locate template file
        $adminDir     = dirname($docRoot) . '/admin';
        // If docRoot already contains 'public' or 'htdocs', step back
        $templateFile = $adminDir . "/assets/templates/certificates/{$version}.php";
        if (!file_exists($templateFile)) {
            // Try sibling: api/../admin/
            $templateFile = dirname(dirname(__DIR__)) . "/admin/assets/templates/certificates/{$version}.php";
        }
        if (!file_exists($templateFile)) {
            $templateFile = dirname(dirname(__DIR__)) . "/admin/assets/templates/certificates/ar_gcc.php";
        }
        if (!file_exists($templateFile)) return '';

        // Render to HTML string
        if (!defined('CERT_PDF_MODE')) define('CERT_PDF_MODE', true);
        ob_start();
        include $templateFile;
        $html = ob_get_clean();

        // Write temp HTML
        $tmpHtml = sys_get_temp_dir() . '/cert_' . $issuedId . '_' . getmypid() . '.html';
        if (file_put_contents($tmpHtml, $html) === false) return '';

        // Find Chromium
        $chromiumBin = null;
        foreach ([getenv('CHROMIUM_BIN') ?: '', '/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome', '/usr/bin/google-chrome-stable'] as $b) {
            if ($b !== '' && is_executable($b)) { $chromiumBin = $b; break; }
        }
        if (!$chromiumBin) { @unlink($tmpHtml); return ''; }

        $cmd = escapeshellarg($chromiumBin)
             . ' --headless --no-sandbox --disable-gpu'
             . ' --run-all-compositor-stages-before-draw'
             . ' --virtual-time-budget=5000'
             . ' --print-to-pdf=' . escapeshellarg($pdfOutputPath)
             . ' ' . escapeshellarg('file://' . $tmpHtml)
             . ' 2>/dev/null';

        exec($cmd, $out, $exitCode);
        @unlink($tmpHtml);

        if ($exitCode !== 0 || !file_exists($pdfOutputPath) || filesize($pdfOutputPath) < 100) {
            return '';
        }
        return $pdfOutputPath;

    } catch (Throwable $ex) {
        return '';
    }
}

function _gcf_data_uri(string $filePath): string
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
