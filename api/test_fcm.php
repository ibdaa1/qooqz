<?php
/**
 * FCM Push Notification Diagnostic Test
 * ======================================
 * ملف اختبار مباشر لإرسال إشعارات Firebase Cloud Messaging
 * يفحص جميع المتطلبات ويعرض تقرير تشخيصي مفصل
 *
 * الاستخدام:
 *   GET  /api/test_fcm.php              → تشخيص فقط (بدون إرسال)
 *   GET  /api/test_fcm.php?send=1       → تشخيص + إرسال حقيقي لأول token (مباشر عبر FCM API)
 *   GET  /api/test_fcm.php?send_class=1 → إرسال عبر Notification::send() (المسار الكامل: DB + push)
 *   GET  /api/test_fcm.php?user_id=1    → فحص tokens لمستخدم محدد
 *   GET  /api/test_fcm.php?send=1&user_id=1 → إرسال فعلي لمستخدم محدد
 *
 * ⚠️ احذف هذا الملف من الإنتاج بعد التشخيص!
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$report = [
    'timestamp'    => date('c'),
    'php_version'  => PHP_VERSION,
    'checks'       => [],
    'errors'       => [],
    'warnings'     => [],
    'send_result'  => null,
];

// -----------------------------------------------
// 1. Load .env
// -----------------------------------------------
$envPath = __DIR__ . '/.env';
if (!file_exists($envPath)) {
    $envPath = __DIR__ . '/shared/config/.env';
}
if (file_exists($envPath) && is_readable($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        [$key, $value] = explode('=', $line, 2) + [1 => ''];
        putenv(trim($key) . '=' . trim($value));
    }
    $report['checks']['env_loaded'] = ['status' => 'OK', 'path' => $envPath];
} else {
    $report['checks']['env_loaded'] = ['status' => 'FAIL', 'message' => '.env not found'];
    $report['errors'][] = '.env file not found at ' . __DIR__ . '/.env or ' . __DIR__ . '/shared/config/.env';
}

// -----------------------------------------------
// 2. Load config files
// -----------------------------------------------
require_once __DIR__ . '/shared/config/config.php';
require_once __DIR__ . '/shared/config/constants.php';
require_once __DIR__ . '/shared/config/db.php';

// -----------------------------------------------
// 3. Check FCM configuration
// -----------------------------------------------

// FCM_PROJECT_ID
$projectId = getenv('FCM_PROJECT_ID') ?: '';
if (!empty($projectId)) {
    $report['checks']['fcm_project_id'] = ['status' => 'OK', 'value' => $projectId];
} else {
    $report['checks']['fcm_project_id'] = ['status' => 'FAIL', 'message' => 'FCM_PROJECT_ID not set in .env'];
    $report['errors'][] = 'FCM_PROJECT_ID is missing. Set it in .env (e.g., FCM_PROJECT_ID=qooqz-2011)';
}

// FCM_SERVER_KEY (Legacy)
$serverKey = getenv('FCM_SERVER_KEY') ?: '';
if (!empty($serverKey) && $serverKey !== 'REPLACE_WITH_YOUR_SERVER_KEY') {
    $report['checks']['fcm_server_key'] = ['status' => 'OK', 'value' => substr($serverKey, 0, 20) . '...'];
} else {
    $report['checks']['fcm_server_key'] = [
        'status'  => 'WARN',
        'message' => 'FCM_SERVER_KEY not configured (Legacy API unavailable). This is OK if using Service Account.',
    ];
    $report['warnings'][] = 'Legacy FCM_SERVER_KEY not set. Must use Service Account (v1 API) instead.';
}

// FCM_VAPID_KEY
$vapidKey = getenv('FCM_VAPID_KEY') ?: '';
if (!empty($vapidKey) && $vapidKey !== 'REPLACE_WITH_YOUR_VAPID_KEY') {
    $report['checks']['fcm_vapid_key'] = ['status' => 'OK', 'value' => substr($vapidKey, 0, 30) . '...'];
} else {
    $report['checks']['fcm_vapid_key'] = ['status' => 'FAIL', 'message' => 'FCM_VAPID_KEY not set — frontend cannot obtain FCM tokens'];
    $report['errors'][] = 'FCM_VAPID_KEY is missing or placeholder. Frontend firebase.js cannot get tokens.';
}

// Service Account JSON
$saPath = getenv('FCM_SERVICE_ACCOUNT_PATH') ?: '';
$saCandidates = [
    __DIR__ . '/shared/config/firebase-service-account.json',
    __DIR__ . '/firebase-service-account.json',
];
if ($saPath && file_exists($saPath)) {
    // explicit path from env
} else {
    $saPath = '';
    foreach ($saCandidates as $c) {
        if (file_exists($c)) { $saPath = $c; break; }
    }
}

if ($saPath && file_exists($saPath)) {
    $saContent = json_decode(file_get_contents($saPath), true);
    if ($saContent && !empty($saContent['private_key']) && !empty($saContent['client_email']) && !empty($saContent['token_uri'])) {
        $report['checks']['service_account'] = [
            'status'       => 'OK',
            'path'         => $saPath,
            'client_email' => $saContent['client_email'],
            'project_id'   => $saContent['project_id'] ?? '(missing)',
        ];
    } else {
        $report['checks']['service_account'] = [
            'status'  => 'FAIL',
            'path'    => $saPath,
            'message' => 'JSON file exists but missing required fields (private_key, client_email, token_uri)',
        ];
        $report['errors'][] = "Service account JSON at {$saPath} is invalid or incomplete.";
    }
} else {
    $report['checks']['service_account'] = [
        'status'   => 'FAIL',
        'message'  => 'firebase-service-account.json not found',
        'searched' => $saCandidates,
    ];
    $report['errors'][] = 'Service Account JSON file not found. Download from Firebase Console → Project Settings → Service accounts → Generate new private key. Place at api/shared/config/firebase-service-account.json';
}

// -----------------------------------------------
// 4. Check PHP extensions
// -----------------------------------------------
$requiredExts = ['curl', 'openssl', 'json', 'pdo', 'pdo_mysql'];
foreach ($requiredExts as $ext) {
    $loaded = extension_loaded($ext);
    $report['checks']['ext_' . $ext] = ['status' => $loaded ? 'OK' : 'FAIL'];
    if (!$loaded) {
        $report['errors'][] = "PHP extension '{$ext}' is not loaded.";
    }
}

// -----------------------------------------------
// 5. Database connection & user_devices check
// -----------------------------------------------
$pdo = null;
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 1;
$tokens = [];

try {
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $report['checks']['database'] = ['status' => 'OK', 'host' => DB_HOST, 'db' => DB_NAME];

    // Check user_devices table
    $stmt = $pdo->prepare("
        SELECT id, user_id, fcm_token, device_type, device_name, is_active, created_at, updated_at
        FROM user_devices
        WHERE user_id = ? AND is_active = 1
        ORDER BY updated_at DESC
    ");
    $stmt->execute([$userId]);
    $devices = $stmt->fetchAll();

    $report['checks']['user_devices'] = [
        'status'       => 'OK',
        'user_id'      => $userId,
        'total_active' => count($devices),
        'devices'      => [],
    ];

    foreach ($devices as $d) {
        $hasToken = !empty($d['fcm_token']);
        $tokenPreview = $hasToken ? substr($d['fcm_token'], 0, 30) . '...' : '(null)';
        $report['checks']['user_devices']['devices'][] = [
            'id'          => $d['id'],
            'device_type' => $d['device_type'],
            'device_name' => $d['device_name'],
            'has_token'   => $hasToken,
            'token_start' => $tokenPreview,
            'created_at'  => $d['created_at'],
            'updated_at'  => $d['updated_at'],
        ];
        if ($hasToken) {
            $tokens[] = $d['fcm_token'];
        }
    }

    // Check ALL devices (not just this user)
    $stmt2 = $pdo->query("SELECT COUNT(*) as total, SUM(fcm_token IS NOT NULL AND fcm_token != '') as with_token, SUM(is_active = 1) as active FROM user_devices");
    $stats = $stmt2->fetch();
    $report['checks']['user_devices_global'] = [
        'total_devices'     => (int)$stats['total'],
        'with_fcm_token'    => (int)$stats['with_token'],
        'active_devices'    => (int)$stats['active'],
    ];

    if (empty($tokens)) {
        $report['warnings'][] = "No active FCM tokens found for user_id={$userId}. Push cannot be sent.";
    }

} catch (PDOException $e) {
    $report['checks']['database'] = ['status' => 'FAIL', 'message' => $e->getMessage()];
    $report['errors'][] = 'Database connection failed: ' . $e->getMessage();
}

// -----------------------------------------------
// 6. Test OAuth2 Access Token generation
// -----------------------------------------------
$accessToken = null;
if ($saPath && file_exists($saPath)) {
    $sa = json_decode(file_get_contents($saPath), true);
    if ($sa && !empty($sa['private_key']) && !empty($sa['client_email']) && !empty($sa['token_uri'])) {
        try {
            $now = time();
            $header = base64url_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claims = base64url_encode(json_encode([
                'iss'   => $sa['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud'   => $sa['token_uri'],
                'iat'   => $now,
                'exp'   => $now + 3600,
            ]));

            $signingInput = "{$header}.{$claims}";
            $privateKey = openssl_pkey_get_private($sa['private_key']);

            if (!$privateKey) {
                $report['checks']['oauth2_token'] = ['status' => 'FAIL', 'message' => 'Failed to parse private key from service account'];
                $report['errors'][] = 'openssl_pkey_get_private() failed. Private key in service account may be corrupted.';
            } else {
                $signature = '';
                openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
                $jwt = $signingInput . '.' . base64url_encode($signature);

                $ch = curl_init($sa['token_uri']);
                curl_setopt_array($ch, [
                    CURLOPT_POST           => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
                    CURLOPT_POSTFIELDS     => http_build_query([
                        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                        'assertion'  => $jwt,
                    ]),
                    CURLOPT_TIMEOUT => 15,
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlErr  = curl_error($ch);
                curl_close($ch);

                if ($curlErr) {
                    $report['checks']['oauth2_token'] = ['status' => 'FAIL', 'message' => "cURL error: {$curlErr}"];
                    $report['errors'][] = "OAuth2 token exchange failed (cURL): {$curlErr}";
                } elseif ($httpCode !== 200) {
                    $report['checks']['oauth2_token'] = [
                        'status'    => 'FAIL',
                        'http_code' => $httpCode,
                        'response'  => json_decode($response, true) ?: $response,
                    ];
                    $report['errors'][] = "OAuth2 token exchange failed (HTTP {$httpCode})";
                } else {
                    $tokenData = json_decode($response, true);
                    if (!empty($tokenData['access_token'])) {
                        $accessToken = $tokenData['access_token'];
                        $report['checks']['oauth2_token'] = [
                            'status'     => 'OK',
                            'token_type' => $tokenData['token_type'] ?? 'Bearer',
                            'expires_in' => $tokenData['expires_in'] ?? 'unknown',
                            'token_start' => substr($accessToken, 0, 30) . '...',
                        ];
                    } else {
                        $report['checks']['oauth2_token'] = ['status' => 'FAIL', 'message' => 'No access_token in response', 'response' => $tokenData];
                        $report['errors'][] = 'OAuth2 response did not contain access_token.';
                    }
                }
            }
        } catch (Throwable $e) {
            $report['checks']['oauth2_token'] = ['status' => 'FAIL', 'message' => $e->getMessage()];
            $report['errors'][] = 'OAuth2 exception: ' . $e->getMessage();
        }
    }
} else {
    $report['checks']['oauth2_token'] = ['status' => 'SKIP', 'message' => 'No service account file — cannot test OAuth2'];
}

// -----------------------------------------------
// 7. Actual FCM Send Test (only if ?send=1)
// -----------------------------------------------
$doSend = isset($_GET['send']) && $_GET['send'] === '1';

if ($doSend) {
    if (empty($tokens)) {
        $report['send_result'] = [
            'attempted' => false,
            'reason'    => 'No FCM tokens available for sending',
        ];
    } elseif (!$accessToken && (empty($serverKey) || $serverKey === 'REPLACE_WITH_YOUR_SERVER_KEY')) {
        $report['send_result'] = [
            'attempted' => false,
            'reason'    => 'Neither OAuth2 access token nor Legacy server key available',
        ];
    } else {
        $testToken = $tokens[0]; // first token
        $testTitle = '🔔 FCM Test — ' . date('H:i:s');
        $testBody  = 'This is a direct FCM test notification from test_fcm.php';
        $testData  = [
            'notification_id' => '0',
            'test'            => 'true',
            'timestamp'       => (string)time(),
        ];

        if ($accessToken && !empty($projectId)) {
            // v1 API
            $endpoint = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
            $payload = [
                'message' => [
                    'token'        => $testToken,
                    'notification' => [
                        'title' => $testTitle,
                        'body'  => $testBody,
                    ],
                    'data'    => $testData,
                    'android' => [
                        'priority' => 'high',
                        'notification' => [
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        ],
                    ],
                    'webpush' => [
                        'notification' => [
                            'icon'  => '/frontend/assets/images/logo.png',
                            'badge' => '/frontend/assets/images/logo.png',
                        ],
                        'fcm_options' => ['link' => '/'],
                    ],
                ],
            ];

            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $accessToken,
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
                CURLOPT_TIMEOUT    => 15,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            $report['send_result'] = [
                'attempted'   => true,
                'api_version' => 'v1',
                'endpoint'    => $endpoint,
                'token_used'  => substr($testToken, 0, 30) . '...',
                'http_code'   => $httpCode,
                'success'     => ($httpCode === 200),
                'curl_error'  => $curlErr ?: null,
                'response'    => json_decode($response, true) ?: $response,
            ];
        } elseif (!empty($serverKey) && $serverKey !== 'REPLACE_WITH_YOUR_SERVER_KEY') {
            // Legacy API
            $payload = [
                'to'           => $testToken,
                'notification' => [
                    'title' => $testTitle,
                    'body'  => $testBody,
                    'icon'  => '/frontend/assets/images/logo.png',
                ],
                'data'     => $testData,
                'priority' => 'high',
            ];

            $ch = curl_init('https://fcm.googleapis.com/fcm/send');
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: key=' . $serverKey,
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_TIMEOUT    => 15,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            $decoded = json_decode($response, true);
            $report['send_result'] = [
                'attempted'   => true,
                'api_version' => 'legacy',
                'token_used'  => substr($testToken, 0, 30) . '...',
                'http_code'   => $httpCode,
                'success'     => ($httpCode === 200 && isset($decoded['success']) && $decoded['success'] > 0),
                'curl_error'  => $curlErr ?: null,
                'response'    => $decoded ?: $response,
            ];
        }
    }
} else {
    $report['send_result'] = [
        'attempted' => false,
        'reason'    => 'Add ?send=1 to URL to attempt actual FCM send',
    ];
}

// -----------------------------------------------
// 8. Test via Notification::send() class (?send_class=1)
// -----------------------------------------------
$doSendClass = isset($_GET['send_class']) && $_GET['send_class'] === '1';

if ($doSendClass) {
    if (!$pdo) {
        $report['class_send_result'] = [
            'attempted' => false,
            'reason'    => 'Database connection not available',
        ];
    } else {
        try {
            require_once __DIR__ . '/shared/helpers/notification.php';

            Notification::setPDO($pdo);

            $classChannels = ['database', 'push'];
            $classTitle    = '🔔 Class Test — ' . date('H:i:s');
            $classBody     = 'Test notification sent via Notification::send() class';

            $classResult = Notification::send(
                recipientId:    $userId,
                recipientType:  'user',
                tenantId:       1,
                typeCode:       'general',
                title:          $classTitle,
                message:        $classBody,
                data:           ['test' => 'true', 'source' => 'test_fcm.php'],
                channels:       $classChannels,
                priority:       'high'
            );

            $report['class_send_result'] = [
                'attempted'  => true,
                'channels'   => $classChannels,
                'user_id'    => $userId,
                'result'     => $classResult,
            ];

            // Check deliveries in DB
            if (!empty($classResult['notification_id'])) {
                $nid = $classResult['notification_id'];
                $dStmt = $pdo->prepare("
                    SELECT nd.id, nc.code AS channel_code, nd.delivery_status, nd.error_message, nd.sent_at, nd.created_at
                    FROM notification_deliveries nd
                    LEFT JOIN notification_channels nc ON nc.id = nd.channel_id
                    WHERE nd.notification_id = ?
                    ORDER BY nd.id
                ");
                $dStmt->execute([$nid]);
                $report['class_send_result']['deliveries'] = $dStmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Throwable $e) {
            $report['class_send_result'] = [
                'attempted' => true,
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ];
        }
    }
} else {
    $report['class_send_result'] = [
        'attempted' => false,
        'reason'    => 'Add ?send_class=1 to test via Notification::send() class (tests full flow with DB + push)',
    ];
}

// -----------------------------------------------
// 9. Summary
// -----------------------------------------------
$hasErrors = !empty($report['errors']);
$report['summary'] = [
    'can_send_push' => !$hasErrors && !empty($tokens),
    'total_errors'   => count($report['errors']),
    'total_warnings' => count($report['warnings']),
    'fcm_tokens_found' => count($tokens),
    'instructions' => $hasErrors ? [
        '1. Download Service Account JSON from Firebase Console → Project Settings → Service accounts → Generate new private key',
        '2. Save as: api/shared/config/firebase-service-account.json',
        '3. Ensure FCM_PROJECT_ID is set in api/shared/config/.env (e.g., your Firebase project ID)',
        '4. Ensure users have registered FCM tokens (check user_devices table)',
        '5. Re-run this test: /api/test_fcm.php',
        '6. To send a real FCM test: /api/test_fcm.php?send=1&user_id=1',
        '7. To test full Notification::send() flow: /api/test_fcm.php?send_class=1&user_id=1',
    ] : [
        'All checks passed!',
        'Use ?send=1 to test direct FCM delivery.',
        'Use ?send_class=1 to test via Notification::send() class (full flow with DB + delivery tracking).',
    ],
];

echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

// -----------------------------------------------
// Helper
// -----------------------------------------------
function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
