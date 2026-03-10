<?php
// htdocs/api/shared/config/cors.php
// CORS configuration + applyCorsHeaders() helper

// 1. قائمة الأصول المسموح بها (يمكن تعديلها حسب الموقع)
$allowedOrigins = getenv('ALLOWED_ORIGINS')
    ? array_map('trim', explode(',', getenv('ALLOWED_ORIGINS')))
    : [
        'https://hcsfcs.top',
        'https://www.hcsfcs.top',
        'https://api.hcsfcs.top',
        'https://admin.hcsfcs.top',
    ];

// 2. تحقق من البيئة الحالية بأمان
$env   = getenv('APP_ENV') ?: (defined('ENVIRONMENT') ? ENVIRONMENT : 'production');
$isDev = $env === 'development';

// 3. إعدادات CORS (used by ConfigLoader if needed)
$corsConfig = [
    'allowed_origins'       => $allowedOrigins,
    'allow_credentials'     => true,
    'allow_methods'         => 'GET, POST, PUT, DELETE, OPTIONS',
    'allow_headers'         => 'Content-Type, Authorization, X-CSRF-Token, X-Requested-With',
    'expose_headers'        => 'X-CSRF-Token',
    'max_age'               => 86400,
    'development_allow_all' => $isDev,
];

// 4. Helper function — called by bootstrap.php
if (!function_exists('applyCorsHeaders')) {
    function applyCorsHeaders(): void
    {
        global $corsConfig, $allowedOrigins, $isDev;

        if (headers_sent()) return;

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if ($isDev ?? false) {
            header('Access-Control-Allow-Origin: *');
        } elseif ($origin && in_array($origin, $allowedOrigins ?? [], true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
        }

        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token, X-Requested-With');
        header('Access-Control-Expose-Headers: X-CSRF-Token');
        header('Access-Control-Max-Age: 86400');
    }
}

return $corsConfig;
