<?php
// htdocs/toro/api/shared/config/db.php
//
// Fallback constants used when no .env file and no server env vars are present.
// ⚠️  Real credentials must be set via environment variables or /shared/config/.env
//     on the production server.  Never commit real passwords to source control.

if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'db_user');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') ?: '');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: 'db_name');
if (!defined('DB_CHARSET')) define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');
if (!defined('DB_PORT')) define('DB_PORT', getenv('DB_PORT') ?: 3306);

return [
    'host' => DB_HOST,
    'user' => DB_USER,
    'pass' => DB_PASS,
    'name' => DB_NAME,
    'charset' => DB_CHARSET,
    'port' => DB_PORT,
];