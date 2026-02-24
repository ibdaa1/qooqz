<?php
declare(strict_types=1);
/**
 * frontend/includes/public_context.php
 *
 * Public context bootstrap for QOOQZ frontend pages.
 * - Loads theme colors/settings from API
 * - Auto-detects language from HTTP_ACCEPT_LANGUAGE (no visible language button)
 * - Loads translations from frontend/languages/{code}.json
 * - Supports all world languages; RTL auto-detected from translation file
 * - No auth required (guest-friendly)
 */

if (!defined('FRONTEND_PUBLIC_CONTEXT')) {
    define('FRONTEND_PUBLIC_CONTEXT', true);
}

/* -------------------------------------------------------
 * 1. Base path & environment
 * ----------------------------------------------------- */
defined('FRONTEND_BASE') || define('FRONTEND_BASE', dirname(__DIR__));

$envFile = FRONTEND_BASE . '/config/app.php';
$appConfig = is_readable($envFile) ? (require $envFile) : [];

$apiConfigFile = FRONTEND_BASE . '/config/api.php';
$apiConfig = is_readable($apiConfigFile) ? (require $apiConfigFile) : [];

/* -------------------------------------------------------
 * 2. Session (safe)
 * ----------------------------------------------------- */
if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    session_start([
        'cookie_httponly' => true,
        'cookie_secure'   => $isSecure,
        'cookie_samesite' => 'Lax',
    ]);
}

/* -------------------------------------------------------
 * 3. Auto-detect language (no visible language button)
 *    Priority: URL param → session → browser Accept-Language → app default
 * ----------------------------------------------------- */
if (!function_exists('pub_detect_lang')) {
    /**
     * Detect best language code from HTTP_ACCEPT_LANGUAGE.
     * Returns the 2-letter language code if a translation file exists.
     */
    function pub_detect_lang(string $default = 'ar'): string {
        $langDir = FRONTEND_BASE . '/languages';
        $avail   = [];

        // Collect available language codes from frontend/languages/*.json
        foreach (glob($langDir . '/*.json') ?: [] as $f) {
            $code = basename($f, '.json');
            if (preg_match('/^[a-z]{2,5}$/', $code)) {
                $avail[$code] = true;
            }
        }

        if (empty($avail)) {
            return $default;
        }

        $header = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        if (!$header) {
            return isset($avail[$default]) ? $default : array_key_first($avail);
        }

        // Parse quality-weighted list, e.g. "ar,en-US;q=0.9,en;q=0.8,fr;q=0.7"
        $candidates = [];
        foreach (explode(',', $header) as $part) {
            $part = trim($part);
            if (preg_match('/^([a-zA-Z\-]+)(?:;q=([0-9.]+))?$/', $part, $m)) {
                $q    = isset($m[2]) ? (float)$m[2] : 1.0;
                $code = strtolower(substr($m[1], 0, 2));
                $candidates[$code] = max($candidates[$code] ?? 0, $q);
            }
        }
        // Sort by quality descending
        arsort($candidates);

        foreach (array_keys($candidates) as $code) {
            if (isset($avail[$code])) {
                return $code;
            }
        }
        return isset($avail[$default]) ? $default : array_key_first($avail);
    }
}

/* -------------------------------------------------------
 * 4. Resolve active language
 * ----------------------------------------------------- */
// URL ?lang=xx overrides (useful for testing), then session, then browser
if (isset($_GET['lang']) && preg_match('/^[a-z]{2,5}$/', $_GET['lang'])) {
    $lang = $_GET['lang'];
    $_SESSION['pub_lang'] = $lang;
} elseif (!empty($_SESSION['pub_lang'])) {
    $lang = $_SESSION['pub_lang'];
} else {
    $lang = pub_detect_lang($appConfig['default_lang'] ?? 'ar');
    $_SESSION['pub_lang'] = $lang;
}

// Fallback to 'ar' if no translation file exists
$langFile = FRONTEND_BASE . '/languages/' . $lang . '.json';
if (!is_readable($langFile)) {
    $lang     = $appConfig['default_lang'] ?? 'ar';
    $langFile = FRONTEND_BASE . '/languages/' . $lang . '.json';
}

/* -------------------------------------------------------
 * 5. Load translations
 * ----------------------------------------------------- */
if (!function_exists('pub_load_translations')) {
    /**
     * Load translation file and return array.
     * Falls back to English then empty array.
     */
    function pub_load_translations(string $langCode): array {
        $base = FRONTEND_BASE . '/languages/';
        $f    = $base . $langCode . '.json';
        if (!is_readable($f)) {
            $f = $base . 'en.json';
        }
        if (!is_readable($f)) {
            return [];
        }
        $data = json_decode(file_get_contents($f), true);
        return is_array($data) ? $data : [];
    }
}

if (!function_exists('t')) {
    /**
     * Translate a dot-separated key, e.g. t('nav.home') or t('hero.title').
     * Falls back to the key itself.
     */
    function t(string $key, array $replace = []): string {
        $strings = $GLOBALS['PUB_STRINGS'] ?? [];
        $parts   = explode('.', $key, 2);
        $group   = $parts[0] ?? '';
        $sub     = $parts[1] ?? '';

        $val = $sub !== ''
            ? ($strings[$group][$sub] ?? $key)
            : ($strings[$group] ?? $key);

        if (!is_string($val)) {
            $val = $key;
        }

        // Simple placeholder replacement {key} => value
        foreach ($replace as $k => $v) {
            $val = str_replace('{' . $k . '}', (string)$v, $val);
        }
        return $val;
    }
}

$_translations = pub_load_translations($lang);
$dir = $_translations['dir'] ?? (in_array($lang, ['ar','fa','ur','he']) ? 'rtl' : 'ltr');
$GLOBALS['PUB_STRINGS'] = $_translations;

/* -------------------------------------------------------
 * 4. API base URL (used for server-side fetch)
 * ----------------------------------------------------- */
if (!function_exists('pub_api_url')) {
    function pub_api_url(string $path = ''): string {
        // Detect scheme + host for self-referencing API calls
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base   = rtrim($scheme . '://' . $host . '/api', '/');
        return $base . '/' . ltrim($path, '/');
    }
}

/* -------------------------------------------------------
 * 5. Lightweight HTTP fetch (curl/file_get_contents)
 * ----------------------------------------------------- */
if (!function_exists('pub_fetch')) {
    /**
     * Fetch JSON from the internal API.
     * Returns decoded array or [] on failure.
     */
    function pub_fetch(string $url, int $timeout = 4): array {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_HTTPHEADER     => ['Accept: application/json'],
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $body = curl_exec($ch);
            curl_close($ch);
        } else {
            $ctx  = stream_context_create(['http' => ['timeout' => $timeout]]);
            $body = @file_get_contents($url, false, $ctx);
        }
        if (!$body) return [];
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : [];
    }
}

/* -------------------------------------------------------
 * 6. Theme / color settings (from DB via API)
 * ----------------------------------------------------- */
if (!function_exists('pub_load_theme')) {
    function pub_load_theme(int $tenantId = 1): array {
        $defaults = [
            'primary'    => '#2d8cf0',
            'secondary'  => '#6c757d',
            'accent'     => '#f39c12',
            'background' => '#ffffff',
            'surface'    => '#f8f9fb',
            'text'       => '#222831',
        ];

        // Try to get from session cache (TTL: 5 min)
        $cacheKey = 'pub_theme_' . $tenantId;
        if (!empty($_SESSION[$cacheKey]) && !empty($_SESSION[$cacheKey . '_ts'])
            && (time() - $_SESSION[$cacheKey . '_ts']) < 300) {
            return $_SESSION[$cacheKey];
        }

        // Fetch from API
        $url  = pub_api_url('color_settings/active') . '?tenant_id=' . $tenantId;
        $resp = pub_fetch($url);

        if (!empty($resp['data']) && is_array($resp['data'])) {
            $colors = $defaults;
            foreach ($resp['data'] as $item) {
                $key = strtolower($item['key'] ?? '');
                $val = $item['value'] ?? '';
                if ($key && $val) {
                    $colors[$key] = $val;
                }
            }
            $_SESSION[$cacheKey]        = $colors;
            $_SESSION[$cacheKey . '_ts'] = time();
            return $colors;
        }

        return $defaults;
    }
}

/* -------------------------------------------------------
 * 7. XSS escape helper
 * ----------------------------------------------------- */
if (!function_exists('e')) {
    function e($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

/* -------------------------------------------------------
 * 8. Pagination helper
 * ----------------------------------------------------- */
if (!function_exists('pub_paginate')) {
    /**
     * Returns pagination info array.
     */
    function pub_paginate(int $total, int $page, int $perPage): array {
        $totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;
        return [
            'total'       => $total,
            'page'        => max(1, $page),
            'per_page'    => $perPage,
            'total_pages' => $totalPages,
            'has_prev'    => $page > 1,
            'has_next'    => $page < $totalPages,
        ];
    }
}

/* -------------------------------------------------------
 * 9. Image URL helper
 *    Resolves uploaded image URLs for products, categories,
 *    entities, brands, etc.
 *    image_types reference:
 *      category / product / product_thumb / entity_logo /
 *      entity_cover / banner / gallery / brand / avatar …
 * ----------------------------------------------------- */
if (!function_exists('pub_img')) {
    /**
     * Build an absolute-path image URL for items stored in /uploads.
     *
     * @param string|null $path   Raw path from DB (may be absolute URL, relative path or null)
     * @param string      $type   image_types.code  e.g. 'product_thumb', 'category', 'entity_logo'
     * @param string      $fallback Emoji / text shown when no image available
     */
    function pub_img(?string $path, string $type = 'product', string $fallback = ''): string {
        if (empty($path)) {
            return $fallback;
        }

        // Already a full URL → return as-is
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '//')) {
            return $path;
        }

        // Strip leading slashes for normalisation
        $clean = ltrim($path, '/');

        // If it already starts with uploads/ → prepend /
        if (str_starts_with($clean, 'uploads/')) {
            return '/' . $clean;
        }

        // Otherwise build under /uploads/images/
        return '/uploads/images/' . $clean;
    }
}

/* -------------------------------------------------------
 * 10. Image HTML tag helper
 * ----------------------------------------------------- */
if (!function_exists('pub_img_tag')) {
    /**
     * Render an <img> tag or a placeholder div.
     *
     * @param string|null $path
     * @param string      $alt
     * @param string      $type  image_types.code
     * @param string      $cssClass
     * @param string      $placeholderIcon  Emoji used as placeholder
     */
    function pub_img_tag(?string $path, string $alt = '', string $type = 'product',
                          string $cssClass = '', string $placeholderIcon = '🖼️'): string {
        $url = empty($path) ? '' : pub_img($path, $type);
        if ($url) {
            return '<img data-src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"'
                 . ' alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '"'
                 . ' loading="lazy"'
                 . ($cssClass ? ' class="' . htmlspecialchars($cssClass, ENT_QUOTES, 'UTF-8') . '"' : '')
                 . ' onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'">'
                 . '<span class="pub-img-placeholder" style="display:none;" aria-hidden="true">' . $placeholderIcon . '</span>';
        }
        return '<span class="pub-img-placeholder" aria-hidden="true">' . $placeholderIcon . '</span>';
    }
}

/* -------------------------------------------------------
 * 11. Compose the shared context globals
 * ----------------------------------------------------- */
$tenantId = (int)($_GET['tenant_id'] ?? $_SESSION['pub_tenant_id'] ?? 1);
$_SESSION['pub_tenant_id'] = $tenantId;

$theme = pub_load_theme($tenantId);

$GLOBALS['PUB_CONTEXT'] = [
    'lang'      => $lang,
    'dir'       => $dir,
    'tenant_id' => $tenantId,
    'theme'     => $theme,
    'app'       => $appConfig,
    'user'      => $_SESSION['current_user'] ?? null,
];
