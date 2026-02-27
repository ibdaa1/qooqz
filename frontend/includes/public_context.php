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
    // Use the same session name as the API auth (api/routes/auth.php uses APP_SESSID)
    // so that login cookies are shared and the user's session data is visible here.
    if (session_name() === 'PHPSESSID' || session_name() === '') {
        session_name('APP_SESSID');
    }
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    session_start([
        'cookie_httponly' => true,
        'cookie_secure'   => $isSecure,
        'cookie_samesite' => 'Lax',
    ]);
}

/* -------------------------------------------------------
 * 3. Language resolution (no visible language button)
 *    Priority: URL ?lang=xx → session → app default_lang
 *    Browser Accept-Language is NOT used (platform default takes precedence).
 * ----------------------------------------------------- */
if (!function_exists('pub_detect_lang')) {
    /**
     * Detect best language code from HTTP_ACCEPT_LANGUAGE.
     * Returns the 2-letter language code if a translation file exists.
     * (Kept for potential future use but not called in the main flow.)
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
// Priority: URL ?lang=xx (explicit override) → HTTP_ACCEPT_LANGUAGE → app default_lang
// Session is used ONLY when user explicitly chose a language via URL param.
// This prevents stale language being "stuck" from a previous visit.
if (isset($_GET['lang']) && preg_match('/^[a-z]{2,5}$/', $_GET['lang'])) {
    $lang = $_GET['lang'];
    $_SESSION['pub_lang'] = $lang;   // save explicit user choice
} elseif (!empty($_SESSION['pub_lang'])) {
    $lang = $_SESSION['pub_lang'];   // honour previous explicit choice
} else {
    // Auto-detect from browser Accept-Language (no session save — fresh each request)
    $lang = pub_detect_lang($appConfig['default_lang'] ?? 'en');
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
 * 6. Theme / color settings — loaded directly from DB via PDO
 *    Also loads: design_settings, font_settings, button_styles, card_styles
 *    Generates complete CSS string stored in $theme['generated_css']
 * ----------------------------------------------------- */
if (!function_exists('pub_load_theme')) {
    function pub_load_theme(int $tenantId = 1): array {
        // Defaults (fallback when DB is unreachable)
        $defaults = [
            'primary'    => '#03874e',
            'secondary'  => '#10B981',
            'accent'     => '#F59E0B',
            'background' => '#0d0d0d',
            'surface'    => '#4f4f4f',
            'text'       => '#FFFFFF',
            'text_muted' => '#B0B0B0',
            'border'     => '#333333',
            'header_bg'  => '#03874e',
            'footer_bg'  => '#1e2a38',
            'generated_css' => '',
            'fonts'      => [],
            'design'     => [],
            'buttons'    => [],
            'cards'      => [],
        ];

        // Session cache is checked after theme_id lookup (inside PDO block below)
        // to ensure cache key includes theme_id and stale entries are not returned.

        // Try direct PDO connection (same DB as API)
        $dbConf = null;
        $dbFile = FRONTEND_BASE . '/../api/shared/config/db.php';
        if (is_readable($dbFile)) {
            $dbConf = require $dbFile;
        }

        if ($dbConf && is_array($dbConf)) {
            try {
                $dsn = sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                    $dbConf['host'] ?? 'localhost',
                    (int)($dbConf['port'] ?? 3306),
                    $dbConf['name'],
                    $dbConf['charset'] ?? 'utf8mb4'
                );
                $pdo = new PDO($dsn, $dbConf['user'], $dbConf['pass'], [
                    PDO::ATTR_TIMEOUT => 3,
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);

                $colors  = [];
                $fonts   = [];
                $designs = [];
                $buttons = [];
                $cards   = [];

                // Look up active theme_id (mirrors AdminUiThemeLoader::getActiveThemeId)
                $thSt = $pdo->prepare('SELECT id FROM themes WHERE tenant_id = ? AND is_active = 1 LIMIT 1');
                $thSt->execute([$tenantId]);
                $thRow = $thSt->fetch(PDO::FETCH_ASSOC);
                if (!$thRow) {
                    $thSt = $pdo->prepare('SELECT id FROM themes WHERE tenant_id = ? AND is_default = 1 LIMIT 1');
                    $thSt->execute([$tenantId]);
                    $thRow = $thSt->fetch(PDO::FETCH_ASSOC);
                }
                $themeDbId  = $thRow ? (int)$thRow['id'] : null;
                $thIdCond   = $themeDbId ? ' AND theme_id = ?' : '';
                $thP = static function(array $base) use ($themeDbId): array {
                    return $themeDbId ? array_merge($base, [$themeDbId]) : $base;
                };

                // color_settings: setting_key, color_value
                $st = $pdo->prepare('SELECT setting_key, color_value FROM color_settings WHERE tenant_id = ? AND is_active = 1' . $thIdCond . ' ORDER BY sort_order, id');
                $st->execute($thP([$tenantId]));
                $colorRows = $st->fetchAll(PDO::FETCH_ASSOC);

                // font_settings: setting_key, font_family, font_size, font_weight, line_height
                $st = $pdo->prepare('SELECT setting_key, font_family, font_size, font_weight, line_height FROM font_settings WHERE tenant_id = ? AND is_active = 1' . $thIdCond . ' ORDER BY sort_order');
                $st->execute($thP([$tenantId]));
                $fonts = $st->fetchAll(PDO::FETCH_ASSOC);

                // design_settings: setting_key, setting_value
                $st = $pdo->prepare('SELECT setting_key, setting_value, setting_type FROM design_settings WHERE tenant_id = ? AND is_active = 1' . $thIdCond . ' ORDER BY sort_order');
                $st->execute($thP([$tenantId]));
                $designs = $st->fetchAll(PDO::FETCH_ASSOC);

                // button_styles
                $st = $pdo->prepare('SELECT slug, button_type, background_color, text_color, border_color, border_width, border_radius, padding, font_size, font_weight, hover_background_color, hover_text_color FROM button_styles WHERE tenant_id = ? AND is_active = 1' . $thIdCond . ' ORDER BY button_type');
                $st->execute($thP([$tenantId]));
                $buttons = $st->fetchAll(PDO::FETCH_ASSOC);

                // card_styles
                $st = $pdo->prepare('SELECT slug, card_type, background_color, border_color, border_width, border_radius, shadow_style, padding FROM card_styles WHERE tenant_id = ? AND is_active = 1' . $thIdCond . ' ORDER BY card_type');
                $st->execute($thP([$tenantId]));
                $cards = $st->fetchAll(PDO::FETCH_ASSOC);

                if ($colorRows || $fonts || $designs || $buttons || $cards) {
                    $theme = $defaults;

                    // Map color_settings keys to theme keys
                    // Covers both possible naming conventions (noun_adjective vs adjective_noun)
                    $colorMap = [
                        'primary_color'        => 'primary',
                        'secondary_color'      => 'secondary',
                        'accent_color'         => 'accent',
                        // "Main Background" — try both naming variants
                        'background_main'      => 'background',
                        'main_background'      => 'background',
                        'background_color_main'=> 'background',
                        // "Secondary Background"
                        'background_secondary' => 'surface',
                        'secondary_background' => 'surface',
                        // "Primary Text"
                        'text_primary'         => 'text',
                        'primary_text'         => 'text',
                        'text_color_primary'   => 'text',
                        // "Secondary Text"
                        'text_secondary'       => 'text_muted',
                        'secondary_text'       => 'text_muted',
                        // Border
                        'border_color'         => 'border',
                        // Header/Footer background
                        'header_bg_color'      => 'header_bg',
                        'header_background'    => 'header_bg',
                        'footer_bg_color'      => 'footer_bg',
                        'footer_background'    => 'footer_bg',
                    ];
                    foreach ($colorRows as $row) {
                        $k = $row['setting_key'] ?? '';
                        $v = $row['color_value'] ?? '';
                        if (!$v) continue;
                        $mapped = $colorMap[$k] ?? null;
                        if ($mapped) $theme[$mapped] = $v;
                        $colors[$k] = $v;
                    }
                    // header_bg defaults to primary if not explicitly set
                    if (empty($colors['header_bg_color'])) {
                        $theme['header_bg'] = $theme['primary'];
                    }

                    $theme['fonts']   = $fonts;
                    $theme['design']  = $designs;
                    $theme['buttons'] = $buttons;
                    $theme['cards']   = $cards;

                    // Generate complete CSS string (mirrors AdminUiThemeLoader::generateCss)
                    // Escape values to prevent CSS/HTML injection (</style> breakout)
                    $cssEsc = function(string $v): string { return str_replace('</style', '<\\/style', htmlspecialchars($v, ENT_QUOTES, 'UTF-8')); };
                    $css = ":root {\n";
                    foreach ($colors as $k => $v) {
                        $css .= '  --' . preg_replace('/[^a-z0-9_\-]/', '-', strtolower($k)) . ': ' . $cssEsc($v) . ";\n";
                    }
                    // CSS variable aliases: bridge DB setting_key names (underscore) to
                    // --pub-* and --color-* names used in public.css / variables.css so
                    // DB colours render correctly without relying solely on the PHP bridge.
                    $pubAliases = [
                        'primary_color'        => ['color-primary',  'pub-primary'],
                        'secondary_color'      => ['color-secondary', 'pub-secondary'],
                        'accent_color'         => ['color-accent',    'pub-accent'],
                        'background_main'      => ['pub-bg'],
                        'background_secondary' => ['pub-surface'],
                        'text_primary'         => ['pub-text'],
                        'text_secondary'       => ['pub-muted'],
                        'border_color'         => ['pub-border'],
                    ];
                    foreach ($pubAliases as $srcKey => $aliases) {
                        if (empty($colors[$srcKey])) continue;
                        $val = $cssEsc($colors[$srcKey]);
                        foreach ($aliases as $alias) {
                            $css .= '  --' . $alias . ': ' . $val . ";\n";
                        }
                    }
                    foreach ($fonts as $f) {
                        if (empty($f['setting_key'])) continue;
                        $sk = preg_replace('/[^a-z0-9_\-]/', '-', strtolower($f['setting_key']));
                        if (!empty($f['font_family'])) $css .= '  --' . $sk . '-family: ' . $cssEsc((string)$f['font_family']) . ";\n";
                        if (!empty($f['font_size']))   $css .= '  --' . $sk . '-size: '   . $cssEsc((string)$f['font_size'])   . ";\n";
                        if (!empty($f['font_weight'])) $css .= '  --' . $sk . '-weight: ' . $cssEsc((string)$f['font_weight']) . ";\n";
                    }
                    foreach ($designs as $d) {
                        if (empty($d['setting_key']) || empty($d['setting_value'])) continue;
                        $css .= '  --' . preg_replace('/[^a-z0-9_\-]/', '-', strtolower($d['setting_key'])) . ': ' . $cssEsc((string)$d['setting_value']) . ";\n";
                    }
                    $css .= "}\n";
                    foreach ($buttons as $b) {
                        if (empty($b['slug'])) continue;
                        $slugB = preg_replace('/[^a-z0-9_\-]/', '-', (string)$b['slug']);
                        $css .= ".btn-{$slugB} {\n";
                        if (!empty($b['background_color'])) $css .= '  background-color: ' . $cssEsc((string)$b['background_color']) . ";\n";
                        if (!empty($b['text_color']))       $css .= '  color: '            . $cssEsc((string)$b['text_color'])       . ";\n";
                        if (!empty($b['border_color']))     $css .= '  border: '           . (int)$b['border_width'] . 'px solid ' . $cssEsc((string)$b['border_color']) . ";\n";
                        if (isset($b['border_radius']))     $css .= '  border-radius: '    . (int)$b['border_radius'] . "px;\n";
                        if (!empty($b['padding']))          $css .= '  padding: '          . $cssEsc((string)$b['padding'])          . ";\n";
                        if (!empty($b['font_size']))        $css .= '  font-size: '        . $cssEsc((string)$b['font_size'])        . ";\n";
                        if (!empty($b['font_weight']))      $css .= '  font-weight: '      . $cssEsc((string)$b['font_weight'])      . ";\n";
                        $css .= "}\n";
                        if (!empty($b['hover_background_color'])) {
                            $css .= ".btn-{$slugB}:hover {\n  background-color: " . $cssEsc((string)$b['hover_background_color']) . ";\n";
                            if (!empty($b['hover_text_color'])) $css .= '  color: ' . $cssEsc((string)$b['hover_text_color']) . ";\n";
                            $css .= "}\n";
                        }
                    }
                    foreach ($cards as $c) {
                        if (empty($c['slug'])) continue;
                        $slugC = preg_replace('/[^a-z0-9_\-]/', '-', (string)$c['slug']);
                        $css .= ".card-{$slugC} {\n";
                        if (!empty($c['background_color'])) $css .= '  background-color: ' . $cssEsc((string)$c['background_color']) . ";\n";
                        if (!empty($c['border_color']))     $css .= '  border: '           . (int)$c['border_width'] . 'px solid ' . $cssEsc((string)$c['border_color']) . ";\n";
                        if (isset($c['border_radius']))     $css .= '  border-radius: '    . (int)$c['border_radius'] . "px;\n";
                        if (!empty($c['shadow_style']))     $css .= '  box-shadow: '       . $cssEsc((string)$c['shadow_style'])     . ";\n";
                        if (!empty($c['padding']))          $css .= '  padding: '          . $cssEsc((string)$c['padding'])          . ";\n";
                        $css .= "}\n";
                    }
                    $theme['generated_css'] = $css;

                    return $theme;
                }
            } catch (Throwable $_) {
                // Silently fall through to HTTP fallback
            }
        }

        // Fallback: try HTTP call to /api/public/ui
        $url  = pub_api_url('public/ui') . '?tenant_id=' . $tenantId;
        $resp = pub_fetch($url, 3);
        if (!empty($resp['data']['generated_css'])) {
            $theme = $defaults;
            $theme['generated_css'] = $resp['data']['generated_css'];
            // Also apply color map from response
            $httpColorMap = [
                'primary_color'        => 'primary',
                'secondary_color'      => 'secondary',
                'accent_color'         => 'accent',
                'background_main'      => 'background',
                'background_secondary' => 'surface',
                'text_primary'         => 'text',
                'text_secondary'       => 'text_muted',
                'border_color'         => 'border',
                'header_bg_color'      => 'header_bg',
                'footer_bg_color'      => 'footer_bg',
            ];
            foreach ($resp['data']['colors'] ?? [] as $item) {
                $k = $item['key'] ?? '';
                $v = $item['value'] ?? '';
                if ($k && $v && isset($httpColorMap[$k])) $theme[$httpColorMap[$k]] = $v;
            }
            if (empty($theme['header_bg']) || $theme['header_bg'] === $defaults['header_bg']) {
                $theme['header_bg'] = $theme['primary'];
            }
            return $theme;
        }

        return $defaults;
    }
}

/* -------------------------------------------------------
 * 7. Direct PDO helper — reuse the same DB connection as the API
 *    Returns PDO instance or null on failure.
 *    Used by product.php and other pages to avoid HTTP loopback
 *    self-referencing requests that may fail on shared hosting.
 * ----------------------------------------------------- */
if (!function_exists('pub_get_pdo')) {
    function pub_get_pdo(): ?PDO {
        $dbFile = FRONTEND_BASE . '/../api/shared/config/db.php';
        if (!is_readable($dbFile)) return null;
        $dbConf = require $dbFile;
        if (!is_array($dbConf)) return null;
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $dbConf['host'] ?? 'localhost',
                (int)($dbConf['port'] ?? 3306),
                $dbConf['name'],
                $dbConf['charset'] ?? 'utf8mb4'
            );
            return new PDO($dsn, $dbConf['user'], $dbConf['pass'], [
                PDO::ATTR_TIMEOUT        => 3,
                PDO::ATTR_ERRMODE        => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (Throwable $_) {
            return null;
        }
    }
}

/* -------------------------------------------------------
 * 8. XSS escape helper
 * ----------------------------------------------------- */
if (!function_exists('e')) {
    function e($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

/* -------------------------------------------------------
 * 9. Pagination helper
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
     * Handles all path formats from the DB:
     *   /admin/uploads/images/general/2026/02/02/img_xxx.webp  → as-is (absolute)
     *   /uploads/images/img_xxx.webp                           → as-is
     *   uploads/images/img_xxx.webp                            → /uploads/images/img_xxx.webp
     *   img_xxx.webp                                           → /uploads/images/img_xxx.webp
     *   https://cdn.example.com/x.jpg                          → passthrough
     *
     * @param string|null $path   Raw path from DB
     * @param string      $type   image_types.code  (category / product / entity_logo …)
     * @param string      $fallback Returned when no image available
     */
    function pub_img(?string $path, string $type = 'product', string $fallback = ''): string {
        if (empty($path)) {
            return $fallback;
        }

        // Already a full URL → return as-is
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '//')) {
            return $path;
        }

        // Absolute path starting with / → return as-is (covers /admin/uploads/... and /uploads/...)
        if (str_starts_with($path, '/')) {
            return $path;
        }

        // Relative path already starting with uploads/
        $clean = ltrim($path, '/');
        if (str_starts_with($clean, 'uploads/')) {
            return '/' . $clean;
        }
        if (str_starts_with($clean, 'admin/uploads/')) {
            return '/' . $clean;
        }

        // Bare filename — place under /uploads/images/
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
    'user'      => $_SESSION['user'] ?? $_SESSION['current_user'] ?? null,
];
