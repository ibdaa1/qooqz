<?php
declare(strict_types=1);

if (!function_exists('app_env')) {
    function app_env(): string
    {
        return $_ENV['APP_ENV']
            ?? getenv('APP_ENV')
            ?? 'production';
    }
}

if (!function_exists('loadEnv')) {
    /**
     * Parse a .env file and populate getenv() / $_ENV / $_SERVER.
     * Already-set env vars are NOT overridden (server/system env takes priority).
     * Missing file is silently ignored — credentials may come from server env vars.
     */
    function loadEnv(string $path): void
    {
        if (!is_readable($path)) {
            return;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);

            // Skip comments and lines without '='
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) < 2) {
                continue;
            }
            [$key, $value] = $parts;
            $key   = trim($key);
            $value = trim($value);

            // Strip optional inline comments (e.g.  VALUE # comment)
            $value = trim((string)preg_replace('/\s+#.*$/', '', $value));

            // Strip surrounding single or double quotes
            if (
                strlen($value) >= 2
                && (
                    ($value[0] === '"'  && $value[-1] === '"')
                    || ($value[0] === "'" && $value[-1] === "'")
                )
            ) {
                $value = substr($value, 1, -1);
            }

            // Don't override values already present in the environment
            if (getenv($key) === false) {
                putenv("{$key}={$value}");
                $_ENV[$key]    = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}
