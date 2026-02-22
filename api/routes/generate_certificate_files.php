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

    // ── PDF path: rendered via print_certificate ─────────────────────────
    // The actual PDF is produced by the browser's print-to-PDF when visiting:
    //   /api/print_certificate?id=REQUEST_ID&lang=LANG
    // We store a canonical path so the download link is always available.
    $lang    = $issued['language_code'] ?? 'ar';
    $pdfPath = '/api/print_certificate?id=' . (int)$reqId . '&lang=' . rawurlencode($lang);

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
