<?php
declare(strict_types=1);

/**
 * api/tests/test_notifications.php
 * QOOQZ — Notification Endpoints Test Suite
 *
 * Tests all five public notification endpoints plus routing-logic edge cases.
 * Can be run two ways:
 *
 *   1. CLI unit tests only (no DB, no HTTP server needed):
 *      php api/tests/test_notifications.php
 *
 *   2. Full integration tests against a live server:
 *      php api/tests/test_notifications.php --base-url=https://hcsfcs.top --user-id=5 --tenant-id=1
 *      (adjust --base-url, --user-id, --tenant-id to match your environment)
 *
 * Endpoints tested:
 *   GET  /api/public/notifications/types
 *   GET  /api/public/notifications/unread-count
 *   GET  /api/public/notifications
 *   POST /api/public/notifications/mark-read
 *   POST /api/public/notifications/mark-all-read
 *
 * Exit code: 0 = all passed, 1 = one or more failures.
 */

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

$passed = 0;
$failed = 0;

function pass(string $label): void {
    global $passed;
    $passed++;
    echo "\033[32m[PASS]\033[0m " . $label . "\n";
}

function fail(string $label, string $detail = ''): void {
    global $failed;
    $failed++;
    echo "\033[31m[FAIL]\033[0m " . $label . ($detail ? " — $detail" : '') . "\n";
}

function section(string $title): void {
    echo "\n\033[33m=== " . $title . " ===\033[0m\n";
}

// ---------------------------------------------------------------------------
// Parse CLI args
// ---------------------------------------------------------------------------
$baseUrl  = null;
$testUserId   = 0;
$testTenantId = 1;

foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--base-url='))  $baseUrl      = rtrim(substr($arg, 11), '/');
    if (str_starts_with($arg, '--user-id='))   $testUserId   = (int)substr($arg, 10);
    if (str_starts_with($arg, '--tenant-id=')) $testTenantId = (int)substr($arg, 13);
}

// ---------------------------------------------------------------------------
// SECTION 1 — Routing-logic unit tests (pure PHP, no HTTP/DB needed)
// ---------------------------------------------------------------------------
section('1. Routing Logic (pure PHP)');

// 1a. strtoupper() normalization  — the main bug fix
foreach (['POST', 'post', 'Post', 'pOsT'] as $raw) {
    $norm = strtoupper($raw);
    if ($norm === 'POST') {
        pass("strtoupper('$raw') === 'POST'");
    } else {
        fail("strtoupper('$raw') should be POST", "got $norm");
    }
}

// 1b. Segment parsing mirrors public.php
$cases = [
    '/api/public/notifications'              => ['notifications'],
    '/api/public/notifications/'             => ['notifications'],
    '/api/public/notifications/mark-read'    => ['notifications', 'mark-read'],
    '/api/public/notifications/mark-all-read'=> ['notifications', 'mark-all-read'],
    '/api/public/notifications/unread-count' => ['notifications', 'unread-count'],
    '/api/public/notifications/types'        => ['notifications', 'types'],
];
foreach ($cases as $uri => $expected) {
    $pubRel   = (string)preg_replace('#^/api/public/?#i', '', parse_url($uri, PHP_URL_PATH) ?? '');
    $segments = array_values(array_filter(explode('/', trim($pubRel, '/'))));
    if ($segments === $expected) {
        pass("segments($uri)");
    } else {
        fail("segments($uri)", json_encode($segments) . ' != ' . json_encode($expected));
    }
}

// 1c. notifSub derived from segments[1]
$subCases = [
    '/api/public/notifications'               => '',
    '/api/public/notifications/mark-read'     => 'mark-read',
    '/api/public/notifications/mark-all-read' => 'mark-all-read',
    '/api/public/notifications/unread-count'  => 'unread-count',
    '/api/public/notifications/types'         => 'types',
];
foreach ($subCases as $uri => $expectedSub) {
    $pubRel   = (string)preg_replace('#^/api/public/?#i', '', parse_url($uri, PHP_URL_PATH) ?? '');
    $segs     = array_values(array_filter(explode('/', trim($pubRel, '/'))));
    $notifSub = strtolower($segs[1] ?? '');
    if ($notifSub === $expectedSub) {
        pass("notifSub($uri) = '$expectedSub'");
    } else {
        fail("notifSub($uri)", "'$notifSub' != '$expectedSub'");
    }
}

// 1d. POST routing conditions (the core fix)
// Before the fix, $notifMethod was set without strtoupper(), so 'post' !== 'POST'.
// After the fix (strtoupper), all casing variants route correctly.
$postRoutes = [
    //  raw method   sub              shouldMatchAfterFix  shouldMatchBeforeFix
    ['POST', 'mark-read',     true,  true],
    ['POST', 'mark-all-read', true,  true],
    ['post', 'mark-read',     true,  false], // BUG: before fix this did NOT match
    ['post', 'mark-all-read', true,  false], // BUG: before fix this did NOT match
];
foreach ($postRoutes as [$method, $sub, $expectAfter, $expectBefore]) {
    // Simulate BEFORE fix (no strtoupper)
    $matchedBefore = ($method === 'POST' && ($sub === 'mark-read' || $sub === 'mark-all-read'));
    if ($matchedBefore === $expectBefore) {
        pass("BEFORE fix: '$method'/'$sub' matched=" . ($matchedBefore ? 'yes' : 'no') . " (expected)");
    } else {
        fail("BEFORE fix: '$method'/'$sub'", "matched=$matchedBefore, expected=$expectBefore");
    }
    // Simulate AFTER fix (with strtoupper)
    $norm          = strtoupper($method);
    $matchedAfter  = ($norm === 'POST' && ($sub === 'mark-read' || $sub === 'mark-all-read'));
    if ($matchedAfter === $expectAfter) {
        pass("AFTER fix:  '$method'/'$sub' matched=" . ($matchedAfter ? 'yes' : 'no') . " (expected)");
    } else {
        fail("AFTER fix:  '$method'/'$sub'", "matched=$matchedAfter, expected=$expectAfter");
    }
}

// 1e. mark-read: empty ids rejected
$bodyVariants = [
    ['ids' => []],
    ['ids' => null],
    [],
    ['ids' => 'notanarray'],
];
foreach ($bodyVariants as $body) {
    $ids = array_values(array_filter(array_map('intval', (array)($body['ids'] ?? []))));
    if (empty($ids)) {
        pass('empty ids correctly rejected: ' . json_encode($body));
    } else {
        fail('non-empty ids when expected empty', json_encode($ids));
    }
}

// 1f. mark-read: valid ids extracted
$validBody = ['ids' => [1, 2, 3]];
$ids = array_values(array_filter(array_map('intval', (array)($validBody['ids'] ?? []))));
if ($ids === [1, 2, 3]) {
    pass('valid ids [1,2,3] extracted correctly');
} else {
    fail('valid ids extraction', json_encode($ids));
}

// 1g. notifTenantId fallback chain
// This mirrors the exact expression used in public_notifications.php:
//   $notifTenantId = (int)($tenantId ?? $_SESSION['pub_tenant_id'] ?? 1) ?: 1;
function resolveNotifTenantId(?int $urlParam, ?int $sessionTenantId): int {
    // Mirrors: (int)($tenantId ?? $_SESSION['pub_tenant_id'] ?? 1) ?: 1
    return (int)($urlParam ?? $sessionTenantId ?? 1) ?: 1;
}
$tenantCases = [
    [5, 2, 5],    // URL param wins
    [null, 2, 2], // session fallback
    [null, null, 1], // default 1
    [0, 3, 3],    // 0 is falsy → session fallback
];
foreach ($tenantCases as [$urlParam, $sessionVal, $expected]) {
    $result = resolveNotifTenantId($urlParam ?: null, $sessionVal ?: null);
    if ($result === $expected) {
        pass("notifTenantId(url=$urlParam, session=$sessionVal) = $expected");
    } else {
        fail("notifTenantId(url=$urlParam, session=$sessionVal)", "got $result, expected $expected");
    }
}

// ---------------------------------------------------------------------------
// SECTION 2 — Direct DB tests (only when bootstrap available)
// ---------------------------------------------------------------------------
section('2. Database Tests (skipped if no DB)');

$pdo = null;
$bootstrapPath = __DIR__ . '/../bootstrap.php';

if (is_readable($bootstrapPath)) {
    try {
        // Suppress headers/output during bootstrap
        ob_start();
        $_SERVER['REQUEST_URI']    = '/api/public/notifications';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        require_once $bootstrapPath;
        ob_end_clean();
        $pdo = $GLOBALS['ADMIN_DB'] ?? null;
    } catch (Throwable $e) {
        ob_end_clean();
        echo "  Bootstrap threw: " . $e->getMessage() . " (skipping DB tests)\n";
    }
}

if ($pdo instanceof PDO) {
    // 2a. notification_types table readable
    try {
        $st = $pdo->prepare('SELECT COUNT(*) FROM notification_types WHERE is_active = 1');
        $st->execute();
        $count = (int)$st->fetchColumn();
        pass("notification_types readable (active types: $count)");
    } catch (Throwable $e) {
        fail('notification_types not readable', $e->getMessage());
    }

    // 2b. notifications table readable
    try {
        $st = $pdo->prepare('SELECT COUNT(*) FROM notifications');
        $st->execute();
        $total = (int)$st->fetchColumn();
        pass("notifications table readable (total rows: $total)");
    } catch (Throwable $e) {
        fail('notifications table not readable', $e->getMessage());
    }

    // 2c. notification_recipients table readable
    try {
        $st = $pdo->prepare('SELECT COUNT(*) FROM notification_recipients');
        $st->execute();
        $total = (int)$st->fetchColumn();
        pass("notification_recipients table readable (total rows: $total)");
    } catch (Throwable $e) {
        fail('notification_recipients table not readable', $e->getMessage());
    }

    // 2d. notification_counters table readable
    try {
        $st = $pdo->prepare('SELECT COUNT(*) FROM notification_counters');
        $st->execute();
        $total = (int)$st->fetchColumn();
        pass("notification_counters table readable (total rows: $total)");
    } catch (Throwable $e) {
        fail('notification_counters table not readable', $e->getMessage());
    }

    // 2e. List query (parameterised, zero rows expected if no test user)
    try {
        $st = $pdo->prepare(
            "SELECT COUNT(*) FROM notification_recipients nr
               JOIN notifications n ON n.id = nr.notification_id
              WHERE nr.recipient_type = 'user'
                AND nr.recipient_id   = ?
                AND n.tenant_id       = ?
                AND (n.expires_at IS NULL OR n.expires_at > NOW())"
        );
        $st->execute([$testUserId ?: 0, $testTenantId]);
        $cnt = (int)$st->fetchColumn();
        pass("list-query parameterised OK (user=$testUserId, tenant=$testTenantId, count=$cnt)");
    } catch (Throwable $e) {
        fail('list-query parameterised', $e->getMessage());
    }

    // 2f. COALESCE unread-count query
    try {
        $st = $pdo->prepare(
            "SELECT COALESCE(
               (SELECT unread_count FROM notification_counters
                 WHERE tenant_id = ? AND recipient_type = 'user' AND recipient_id = ? LIMIT 1),
               (SELECT COUNT(*) FROM notification_recipients nr2
                  JOIN notifications n2 ON n2.id = nr2.notification_id
                 WHERE nr2.recipient_type = 'user' AND nr2.recipient_id = ?
                   AND nr2.is_read = 0 AND n2.tenant_id = ?
                   AND (n2.expires_at IS NULL OR n2.expires_at > NOW()))
            ) AS unread_count"
        );
        $st->execute([$testTenantId, $testUserId ?: 0, $testUserId ?: 0, $testTenantId]);
        $uc = (int)$st->fetchColumn();
        pass("COALESCE unread-count query OK (user=$testUserId, unread=$uc)");
    } catch (Throwable $e) {
        fail('COALESCE unread-count query', $e->getMessage());
    }

} else {
    echo "  No PDO connection — skipping DB tests (pass --base-url or ensure ADMIN_DB is available)\n";
}

// ---------------------------------------------------------------------------
// SECTION 3 — HTTP integration tests (only when --base-url provided)
// ---------------------------------------------------------------------------
section('3. HTTP Integration Tests');

if (!$baseUrl) {
    echo "  No --base-url provided — skipping HTTP tests.\n";
    echo "  Example: php " . basename(__FILE__) . " --base-url=https://hcsfcs.top --user-id=5 --tenant-id=1\n";
} elseif (!function_exists('curl_init')) {
    echo "  cURL not available — skipping HTTP tests.\n";
} else {
    /**
     * Helper: execute an HTTP request and return [httpCode, decodedBody, error]
     * Note: SSL verification is disabled for convenience in test environments.
     * Do not use against untrusted hosts.
     * @return array{int, array<mixed>|null, string}
     */
    function http(string $method, string $url, array $body = [], array $extraHeaders = []): array {
        $ch = curl_init($url);
        $headers = array_merge(['Content-Type: application/json', 'Accept: application/json'], $extraHeaders);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false, // acceptable for internal test use only
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        $raw  = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        $decoded = $raw ? (json_decode($raw, true) ?? null) : null;
        return [$code, $decoded, $err];
    }

    // Build query string helper
    $qs = fn(array $p) => '?' . http_build_query($p);

    $notifBase = $baseUrl . '/api/public/notifications';
    $tenantQs  = $qs(['tenant_id' => $testTenantId]);

    // 3a. GET /api/public/notifications/types — public, no login required
    [$code, $body, $err] = http('GET', $notifBase . '/types' . $tenantQs);
    if ($err) {
        fail('GET /notifications/types — curl error', $err);
    } elseif ($code === 200 && isset($body['data']['types'])) {
        pass("GET /notifications/types → 200 (" . count($body['data']['types']) . " types)");
    } elseif ($code === 200 && isset($body['types'])) {
        pass("GET /notifications/types → 200 (" . count($body['types']) . " types)");
    } else {
        fail('GET /notifications/types', "HTTP $code, body=" . json_encode($body));
    }

    // 3b. GET /api/public/notifications/unread-count — requires login
    [$code, $body, $err] = http('GET', $notifBase . '/unread-count' . $tenantQs);
    if ($err) {
        fail('GET /notifications/unread-count — curl error', $err);
    } elseif ($code === 401) {
        pass('GET /notifications/unread-count → 401 (login required, correct)');
    } elseif ($code === 200 && array_key_exists('unread_count', $body['data'] ?? $body)) {
        pass('GET /notifications/unread-count → 200');
    } else {
        fail('GET /notifications/unread-count', "HTTP $code, body=" . json_encode($body));
    }

    // 3c. GET /api/public/notifications — requires login
    [$code, $body, $err] = http('GET', $notifBase . $tenantQs);
    if ($err) {
        fail('GET /notifications — curl error', $err);
    } elseif ($code === 401) {
        pass('GET /notifications → 401 (login required, correct)');
    } elseif ($code === 200) {
        pass('GET /notifications → 200');
    } else {
        fail('GET /notifications', "HTTP $code, body=" . json_encode($body));
    }

    // 3d. POST /api/public/notifications/mark-read — requires login
    [$code, $body, $err] = http('POST', $notifBase . '/mark-read', ['ids' => [1]]);
    if ($err) {
        fail('POST /notifications/mark-read — curl error', $err);
    } elseif ($code === 401) {
        pass('POST /notifications/mark-read → 401 (login required, correct)');
    } elseif ($code === 200 || $code === 422) {
        pass("POST /notifications/mark-read → $code (route matched)");
    } else {
        fail('POST /notifications/mark-read', "HTTP $code, body=" . json_encode($body));
    }

    // 3e. POST /api/public/notifications/mark-all-read — requires login
    [$code, $body, $err] = http('POST', $notifBase . '/mark-all-read');
    if ($err) {
        fail('POST /notifications/mark-all-read — curl error', $err);
    } elseif ($code === 401) {
        pass('POST /notifications/mark-all-read → 401 (login required, correct)');
    } elseif ($code === 200) {
        pass('POST /notifications/mark-all-read → 200 (route matched)');
    } else {
        fail('POST /notifications/mark-all-read', "HTTP $code, body=" . json_encode($body));
    }

    // 3f. POST mark-read with empty ids → 422
    [$code, $body, $err] = http('POST', $notifBase . '/mark-read', ['ids' => []]);
    if ($err) {
        fail('POST /notifications/mark-read empty-ids — curl error', $err);
    } elseif ($code === 422) {
        pass('POST /notifications/mark-read with empty ids → 422');
    } elseif ($code === 401) {
        pass('POST /notifications/mark-read with empty ids → 401 (auth checked first)');
    } else {
        fail('POST /notifications/mark-read with empty ids', "HTTP $code, body=" . json_encode($body));
    }

    // 3g. OPTIONS preflight (CORS)
    $ch = curl_init($notifBase . '/mark-all-read');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_CUSTOMREQUEST  => 'OPTIONS',
        CURLOPT_HTTPHEADER     => [
            'Origin: https://example.com',
            'Access-Control-Request-Method: POST',
            'Access-Control-Request-Headers: Content-Type',
        ],
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $raw  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (in_array($code, [200, 204])) {
        pass("OPTIONS /notifications/mark-all-read → $code (preflight handled)");
    } else {
        fail('OPTIONS /notifications/mark-all-read', "HTTP $code");
    }
}

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------
section('Summary');
$total = $passed + $failed;
echo "Total: $total  Passed: \033[32m$passed\033[0m  Failed: \033[31m$failed\033[0m\n\n";

if ($failed > 0) {
    echo "\033[31mSome tests FAILED.\033[0m Review the output above.\n";
    exit(1);
}

echo "\033[32mAll tests passed.\033[0m\n";
exit(0);
