<?php
/**
 * TORO — v1/modules/RolePermissions/Contracts/RolePermissionsRepositoryInterface.php
 */
declare(strict_types=1);

interface RolePermissionsRepositoryInterface
{
    // ── Read ───────────────────────────────────────────────────
    /**
     * @return int[] معرفات الصلاحيات المرتبطة بالدور
     */
    public function getPermissionIdsByRole(int $roleId): array;

    /**
     * @return int[] معرفات الأدوار المرتبطة بالصلاحية
     */
    public function getRoleIdsByPermission(int $permissionId): array;

    /**
     * التحقق من وجود علاقة محددة
     */
    public function exists(int $roleId, int $permissionId): bool;

    // ── Write ──────────────────────────────────────────────────
    /**
     * إرفاق صلاحيات بدور (تجاهل المكرر)
     * @return int عدد العلاقات المضافة فعلياً
     */
    public function attach(int $roleId, array $permissionIds): int;

    /**
     * فصل صلاحيات عن دور
     * @return int عدد العلاقات المحذوفة
     */
    public function detach(int $roleId, array $permissionIds): int;

    /**
     * مزامنة صلاحيات دور (استبدال كامل)
     */
    public function sync(int $roleId, array $permissionIds): void;

    /**
     * حذف علاقة محددة
     */
    public function delete(int $roleId, int $permissionId): bool;
}