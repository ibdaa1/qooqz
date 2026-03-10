<?php
/**
 * TORO — v1/modules/RolePermissions/Repositories/PdoRolePermissionsRepository.php
 */
declare(strict_types=1);

final class PdoRolePermissionsRepository implements RolePermissionsRepositoryInterface
{
    public function __construct(private readonly PDO $pdo) {}

    // ── Read ───────────────────────────────────────────────────
    public function getPermissionIdsByRole(int $roleId): array
    {
        $stmt = $this->pdo->prepare("SELECT permission_id FROM role_permissions WHERE role_id = :role_id");
        $stmt->execute([':role_id' => $roleId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getRoleIdsByPermission(int $permissionId): array
    {
        $stmt = $this->pdo->prepare("SELECT role_id FROM role_permissions WHERE permission_id = :permission_id");
        $stmt->execute([':permission_id' => $permissionId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function exists(int $roleId, int $permissionId): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM role_permissions WHERE role_id = :role_id AND permission_id = :perm_id LIMIT 1");
        $stmt->execute([':role_id' => $roleId, ':perm_id' => $permissionId]);
        return (bool)$stmt->fetchColumn();
    }

    // ── Write ──────────────────────────────────────────────────
    public function attach(int $roleId, array $permissionIds): int
    {
        if (empty($permissionIds)) return 0;

        $values = [];
        $placeholders = [];
        foreach ($permissionIds as $i => $permId) {
            $placeholders[] = "(:role_id, :perm_{$i})";
            $values[":perm_{$i}"] = $permId;
        }

        $sql = "INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES " . implode(',', $placeholders);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge([':role_id' => $roleId], $values));
        return $stmt->rowCount();
    }

    public function detach(int $roleId, array $permissionIds): int
    {
        if (empty($permissionIds)) return 0;

        $placeholders = implode(',', array_fill(0, count($permissionIds), '?'));
        $sql = "DELETE FROM role_permissions WHERE role_id = ? AND permission_id IN ({$placeholders})";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge([$roleId], $permissionIds));
        return $stmt->rowCount();
    }

    public function sync(int $roleId, array $permissionIds): void
    {
        $this->pdo->beginTransaction();
        try {
            // حذف القديم
            $this->pdo->prepare("DELETE FROM role_permissions WHERE role_id = :role_id")
                ->execute([':role_id' => $roleId]);

            // إدراج الجديد
            if (!empty($permissionIds)) {
                $values = [];
                $placeholders = [];
                foreach ($permissionIds as $i => $permId) {
                    $placeholders[] = "(:role_id, :perm_{$i})";
                    $values[":perm_{$i}"] = $permId;
                }
                $sql = "INSERT INTO role_permissions (role_id, permission_id) VALUES " . implode(',', $placeholders);
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute(array_merge([':role_id' => $roleId], $values));
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function delete(int $roleId, int $permissionId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM role_permissions WHERE role_id = :role_id AND permission_id = :perm_id");
        return $stmt->execute([':role_id' => $roleId, ':perm_id' => $permissionId]);
    }
}