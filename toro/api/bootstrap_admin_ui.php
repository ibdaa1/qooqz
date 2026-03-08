<?php
/**
 * TORO — bootstrap_admin_ui.php
 * /public_html/toro/api/bootstrap_admin_ui.php
 *
 * Loaded when the admin panel HTML/UI is being rendered (not JSON API calls).
 * Hydrates the AdminUiThemeLoader with colours from `theme_colors` DB table.
 */

declare(strict_types=1);

require_once BASE_PATH . '/bootstrap_admin_context.php';

// Load dynamic theme into a globally accessible singleton
\Shared\Ui\AdminUiThemeLoader::boot(
    \Shared\Core\DatabaseConnection::getInstance()
);