<?php
declare(strict_types=1);

namespace Shared\Helpers;

final class AuditLogger
{
    private static ?\PDO $pdo = null;

    public static function init(\PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    /**
     * Log an auditable action.
     *
     * @param string   $action    Action identifier (e.g. 'login_success', 'category_created')
     * @param int|null $actorId   ID of the user performing the action
     * @param string   $entity    Entity/table name (e.g. 'users', 'categories')
     * @param int|null $entityId  ID of the affected entity
     * @param array    $extra     Additional context (stored as new_values JSON)
     */
    public static function log(
        string $action,
        ?int   $actorId,
        string $entity,
        ?int   $entityId = null,
        array  $extra    = []
    ): void {
        try {
            if (self::$pdo === null) {
                self::$pdo = \Shared\Core\DatabaseConnection::getInstance();
            }
            $pdo = self::$pdo;

            $stmt = $pdo->prepare(
                'INSERT INTO audit_logs (user_id, action, entity, entity_id, new_values, ip_address, user_agent)
                 VALUES (:user_id, :action, :entity, :entity_id, :new_values, :ip, :ua)'
            );
            $stmt->execute([
                ':user_id'    => $actorId,
                ':action'     => $action,
                ':entity'     => $entity,
                ':entity_id'  => $entityId,
                ':new_values' => $extra ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null,
                ':ip'         => $_SERVER['REMOTE_ADDR'] ?? null,
                ':ua'         => isset($_SERVER['HTTP_USER_AGENT'])
                                    ? substr($_SERVER['HTTP_USER_AGENT'], 0, 300)
                                    : null,
            ]);
        } catch (\Throwable) {
            // Audit must never break main operations — fail silently
        }
    }
}