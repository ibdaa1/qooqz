<?php
declare(strict_types=1);
/**
 * Public API sub-route: notifications
 * Loaded by api/v1/routes/public.php dispatcher.
 * Variables available: $pdo, $pdoList, $pdoOne, $pdoCount,
 *   $first, $segments, $lang, $page, $per, $offset, $tenantId
 */

if ($first === 'notifications') {
    require __DIR__ . '/public_notifications.php';
    exit;
}

/* -------------------------------------------------------
 * /api/public/cart — DB-backed user cart
 * Requires authenticated session.
 * Sub-paths: (none)=GET, add=POST, update=POST, remove=POST, clear=POST
 * ----------------------------------------------------- */
