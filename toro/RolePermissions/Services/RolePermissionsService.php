<?php
/**
 * TORO — v1/modules/RolePermissions/Services/RolePermissionsService.php
 * كل منطق صلاحيات الأدوار — Controller لا يعرف PDO أبداً
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\{ValidationException, NotFoundException};
use Shared\Helpers\AuditLogger;

final class RolePermissionsService
{
    public function __construct(
        private readonly RolePermissionsRepositoryInterface $repo,
        // نضيف repositories للأدوار والصلاحيات للتحقق من وجودها
        private readonly RolesRepositoryInterface $rolesRepo,
        private readonly PermissionsRepositoryInterface $permsRepo,
    ) {}

    // ══════════════════════════════════════════════════════════
    // READ
    // ══════════════════════════════════════════════════════════
    public function getPermissionsByRole(int $roleId): array
    {
        $role = $this->rolesRepo->findById($roleId);
        if (!$role) throw new NotFoundException("الدور #{$roleId} غير موجود");

        $permIds = $this->repo->getPermissionIdsByRole($roleId);
        if (empty($permIds)) return [];

        // نجلب تفاصيل الصلاحيات من خلال PDO مباشرة (أو نضيف دالة findAllByIds في repository)
        $placeholders = implode(',', array_fill(0, count($permIds), '?'));
        $stmt = $this->permsRepo->pdo->prepare("SELECT * FROM permissions WHERE id IN ($placeholders)");
        $stmt->execute($permIds);
        return $stmt->fetchAll();
    }

    public function getRolesByPermission(int $permissionId): array
    {
        $perm = $this->permsRepo->findById($permissionId);
        if (!$perm) throw new NotFoundException("الصلاحية #{$permissionId} غير موجودة");

        $roleIds = $this->repo->getRoleIdsByPermission($permissionId);
        if (empty($roleIds)) return [];

        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        $stmt = $this->rolesRepo->pdo->prepare("SELECT * FROM roles WHERE id IN ($placeholders)");
        $stmt->execute($roleIds);
        return $stmt->fetchAll();
    }

    public function checkExists(int $roleId, int $permissionId): bool
    {
        // تحقق من وجود الدور والصلاحية (اختياري)
        $role = $this->rolesRepo->findById($roleId);
        if (!$role) throw new NotFoundException("الدور #{$roleId} غير موجود");
        $perm = $this->permsRepo->findById($permissionId);
        if (!$perm) throw new NotFoundException("الصلاحية #{$permissionId} غير موجودة");

        return $this->repo->exists($roleId, $permissionId);
    }

    // ══════════════════════════════════════════════════════════
    // WRITE
    // ══════════════════════════════════════════════════════════
    public function attach(AttachPermissionsDTO $dto, int $actorId): array
    {
        // تحقق من وجود الدور
        $role = $this->rolesRepo->findById($dto->roleId);
        if (!$role) throw new NotFoundException("الدور #{$dto->roleId} غير موجود");

        // تحقق من وجود كل الصلاحيات
        $this->validatePermissionsExist($dto->permissionIds);

        $attached = $this->repo->attach($dto->roleId, $dto->permissionIds);

        AuditLogger::log('role_permissions_attached', $actorId, 'role_permissions', $dto->roleId, [
            'permission_ids' => $dto->permissionIds,
            'attached_count' => $attached,
        ]);

        return [
            'role_id' => $dto->roleId,
            'permission_ids' => $dto->permissionIds,
            'attached' => $attached,
        ];
    }

    public function detach(AttachPermissionsDTO $dto, int $actorId): array
    {
        $role = $this->rolesRepo->findById($dto->roleId);
        if (!$role) throw new NotFoundException("الدور #{$dto->roleId} غير موجود");

        $detached = $this->repo->detach($dto->roleId, $dto->permissionIds);

        AuditLogger::log('role_permissions_detached', $actorId, 'role_permissions', $dto->roleId, [
            'permission_ids' => $dto->permissionIds,
            'detached_count' => $detached,
        ]);

        return [
            'role_id' => $dto->roleId,
            'permission_ids' => $dto->permissionIds,
            'detached' => $detached,
        ];
    }

    public function sync(SyncPermissionsDTO $dto, int $actorId): array
    {
        $role = $this->rolesRepo->findById($dto->roleId);
        if (!$role) throw new NotFoundException("الدور #{$dto->roleId} غير موجود");

        // تحقق من وجود الصلاحيات (إذا لم تكن فارغة)
        if (!empty($dto->permissionIds)) {
            $this->validatePermissionsExist($dto->permissionIds);
        }

        $this->repo->sync($dto->roleId, $dto->permissionIds);

        AuditLogger::log('role_permissions_synced', $actorId, 'role_permissions', $dto->roleId, [
            'permission_ids' => $dto->permissionIds,
        ]);

        return [
            'role_id' => $dto->roleId,
            'permission_ids' => $dto->permissionIds,
        ];
    }

    public function deleteRelation(int $roleId, int $permissionId, int $actorId): void
    {
        // تحقق من وجود العلاقة
        if (!$this->repo->exists($roleId, $permissionId)) {
            throw new NotFoundException('العلاقة غير موجودة');
        }

        $this->repo->delete($roleId, $permissionId);

        AuditLogger::log('role_permission_deleted', $actorId, 'role_permissions', null, [
            'role_id' => $roleId,
            'permission_id' => $permissionId,
        ]);
    }

    // ── Private helper ─────────────────────────────────────────
    private function validatePermissionsExist(array $permissionIds): void
    {
        if (empty($permissionIds)) return;

        $existingIds = [];
        // نستخدم طريقة بسيطة: نحضر كل الصلاحيات بهذه المعرفات ونتحقق من العدد
        $placeholders = implode(',', array_fill(0, count($permissionIds), '?'));
        $stmt = $this->permsRepo->pdo->prepare("SELECT id FROM permissions WHERE id IN ($placeholders)");
        $stmt->execute($permissionIds);
        $existingIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (count($existingIds) !== count($permissionIds)) {
            $missing = array_diff($permissionIds, $existingIds);
            throw new ValidationException('بعض الصلاحيات غير موجودة', ['permission_ids' => 'المعرفات غير صالحة: ' . implode(',', $missing)]);
        }
    }
}