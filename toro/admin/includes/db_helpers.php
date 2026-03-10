<?php
/**
 * TORO Admin — includes/db_helpers.php
 *
 * Lightweight PDO helpers that fetch live data from the database for the admin UI.
 * Loaded by header.php only — not by the API bootstrap.
 *
 * Functions provided
 * ──────────────────
 *   adminGetPdo()         → \PDO|null
 *   adminGetThemeCss()    → string   (CSS :root block from theme_colors)
 *   adminGetThemeSizes()  → string   (CSS :root block from theme_sizes)
 *   adminGetSettings()    → array    (key=>value of public settings)
 *   adminGetMenuItems()   → array    (flat menu_items rows for the sidebar)
 */
declare(strict_types=1);

// ── PDO singleton (admin-only, separate from API singleton) ──────────────────
function adminGetPdo(): ?\PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    // Try env vars first (Docker / cpanel) then fall back to the shared db.php file
    $host    = getenv('DB_HOST') ?: null;
    $db      = getenv('DB_NAME') ?: null;
    $user    = getenv('DB_USER') ?: null;
    $pass    = getenv('DB_PASS') ?: '';
    $port    = (int)(getenv('DB_PORT') ?: 3306);
    $charset = 'utf8mb4';

    if (!$host || !$db || !$user) {
        $cfgFile = __DIR__ . '/../../api/shared/config/db.php';
        if (is_readable($cfgFile)) {
            $cfg  = require $cfgFile;
            $host = $cfg['host'] ?? 'localhost';
            $db   = $cfg['name'] ?? '';
            $user = $cfg['user'] ?? '';
            $pass = $cfg['pass'] ?? '';
            $port = (int)($cfg['port'] ?? 3306);
        }
    }

    if (!$host || !$db || !$user) {
        return null;
    }

    try {
        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
        $pdo = new \PDO($dsn, $user, $pass, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (\Throwable $e) {
        $pdo = null;
    }
    return $pdo;
}

// ── Theme colors → CSS :root block ─────────────────────────────────────────
function adminGetThemeCss(): string
{
    $pdo = adminGetPdo();
    if (!$pdo) {
        return '';
    }
    try {
        $stmt = $pdo->query(
            'SELECT `variable`, `value` FROM theme_colors WHERE is_active = 1 ORDER BY id ASC'
        );
        $rows = $stmt->fetchAll();
        if (empty($rows)) {
            return '';
        }
        $lines = array_map(
            fn(array $r) => '  ' . htmlspecialchars($r['variable'], ENT_QUOTES, 'UTF-8') . ': '
                          . htmlspecialchars($r['value'], ENT_QUOTES, 'UTF-8') . ';',
            $rows
        );
        return ":root {\n" . implode("\n", $lines) . "\n}";
    } catch (\Throwable $e) {
        return '';
    }
}

// ── Theme sizes → CSS :root block ──────────────────────────────────────────
function adminGetThemeSizes(): string
{
    $pdo = adminGetPdo();
    if (!$pdo) {
        return '';
    }
    try {
        $stmt = $pdo->query(
            'SELECT `name`, `value` FROM theme_sizes WHERE is_active = 1 ORDER BY sort_order ASC, id ASC'
        );
        $rows = $stmt->fetchAll();
        if (empty($rows)) {
            return '';
        }
        $lines = array_map(
            function (array $r): string {
                // Only allow safe CSS identifier characters in the variable name
                $safeName = preg_replace('/[^a-zA-Z0-9\-_]/', '-', $r['name']);
                $safeVal  = htmlspecialchars($r['value'], ENT_QUOTES, 'UTF-8');
                return '  --' . $safeName . ': ' . $safeVal . ';';
            },
            $rows
        );
        return ":root {\n" . implode("\n", $lines) . "\n}";
    } catch (\Throwable $e) {
        return '';
    }
}

// ── Public settings → associative array ────────────────────────────────────
function adminGetSettings(): array
{
    $pdo = adminGetPdo();
    if (!$pdo) {
        return [];
    }
    try {
        $stmt = $pdo->query(
            'SELECT `key`, `value`, `type` FROM settings WHERE is_public = 1'
        );
        $rows  = $stmt->fetchAll();
        $out   = [];
        foreach ($rows as $r) {
            $val = $r['value'];
            switch ($r['type'] ?? 'string') {
                case 'boolean':
                    $val = filter_var($val, FILTER_VALIDATE_BOOLEAN);
                    break;
                case 'integer':
                    $val = (int)$val;
                    break;
                case 'float':
                    $val = (float)$val;
                    break;
                case 'json':
                    $val = json_decode((string)$val, true) ?? $val;
                    break;
            }
            $out[$r['key']] = $val;
        }
        return $out;
    } catch (\Throwable $e) {
        return [];
    }
}

// ── Menu items for a given menu slug ───────────────────────────────────────
/**
 * Returns flat list of active menu_items for the menu identified by $slug.
 * Each row has: id, parent_id, type, url, icon, target, sort_order, label.
 * $lang defaults to the current admin language stored in $_adminLang global.
 */
function adminGetMenuItems(string $slug, ?string $lang = null): array
{
    $pdo = adminGetPdo();
    if (!$pdo) {
        return [];
    }
    try {
        // Resolve menu id by slug
        $stmt = $pdo->prepare(
            'SELECT id FROM menus WHERE slug = :slug AND is_active = 1 LIMIT 1'
        );
        $stmt->execute([':slug' => $slug]);
        $menu = $stmt->fetch();
        if (!$menu) {
            return [];
        }
        $menuId = (int)$menu['id'];

        // Resolve language id (fallback to default language)
        $langId = null;
        if ($lang) {
            $ls = $pdo->prepare(
                'SELECT id FROM languages WHERE code = :code LIMIT 1'
            );
            $ls->execute([':code' => $lang]);
            $lr = $ls->fetch();
            $langId = $lr ? (int)$lr['id'] : null;
        }

        if (!$langId) {
            // Use default language
            $ls = $pdo->query(
                'SELECT id FROM languages WHERE is_default = 1 LIMIT 1'
            );
            $lr = $ls ? $ls->fetch() : null;
            $langId = $lr ? (int)$lr['id'] : null;
        }

        $stmt = $pdo->prepare("
            SELECT
                mi.id, mi.parent_id, mi.type, mi.reference_id,
                mi.url, mi.icon, mi.target, mi.sort_order, mi.is_active,
                COALESCE(mit.label, mi.url, '') AS label,
                COALESCE(mit.tooltip, '') AS tooltip
            FROM menu_items mi
            LEFT JOIN menu_item_translations mit
                ON mit.menu_item_id = mi.id
                AND mit.language_id = :lang_id
            WHERE mi.menu_id = :menu_id
              AND mi.is_active = 1
            ORDER BY mi.sort_order ASC, mi.id ASC
        ");
        $stmt->bindValue(':lang_id', $langId, $langId ? \PDO::PARAM_INT : \PDO::PARAM_NULL);
        $stmt->bindValue(':menu_id', $menuId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (\Throwable $e) {
        return [];
    }
}
