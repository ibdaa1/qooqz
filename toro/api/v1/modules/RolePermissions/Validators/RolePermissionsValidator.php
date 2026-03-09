<?php
/**
 * TORO — v1/modules/RolePermissions/Validators/RolePermissionsValidator.php
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\ValidationException;

final class RolePermissionsValidator
{
    // ── Attach ───────────────────────────────────────────────
    public static function attach(array $data): void
    {
        $e = [];

        if (empty($data['role_id'])) {
            $e['role_id'] = 'معرف الدور مطلوب';
        }

        if (empty($data['permission_ids']) || !is_array($data['permission_ids'])) {
            $e['permission_ids'] = 'مصفوفة معرفات الصلاحيات مطلوبة';
        } elseif (empty($data['permission_ids'])) {
            $e['permission_ids'] = 'يجب إرسال صلاحية واحدة على الأقل';
        }

        if ($e) throw new ValidationException('بيانات الإرفاق غير صحيحة', $e);
    }

    // ── Detach ───────────────────────────────────────────────
    public static function detach(array $data): void
    {
        $e = [];

        if (empty($data['role_id'])) {
            $e['role_id'] = 'معرف الدور مطلوب';
        }

        if (empty($data['permission_ids']) || !is_array($data['permission_ids'])) {
            $e['permission_ids'] = 'مصفوفة معرفات الصلاحيات مطلوبة';
        }

        if ($e) throw new ValidationException('بيانات الفصل غير صحيحة', $e);
    }

    // ── Sync ─────────────────────────────────────────────────
    public static function sync(array $data): void
    {
        $e = [];

        if (empty($data['role_id'])) {
            $e['role_id'] = 'معرف الدور مطلوب';
        }

        if (!isset($data['permission_ids']) || !is_array($data['permission_ids'])) {
            $e['permission_ids'] = 'مصفوفة معرفات الصلاحيات مطلوبة';
        }

        if ($e) throw new ValidationException('بيانات المزامنة غير صحيحة', $e);
    }

    // ── Destroy ──────────────────────────────────────────────
    public static function destroy(array $data): void
    {
        $e = [];

        if (empty($data['role_id'])) {
            $e['role_id'] = 'معرف الدور مطلوب';
        }
        if (empty($data['permission_id'])) {
            $e['permission_id'] = 'معرف الصلاحية مطلوب';
        }

        if ($e) throw new ValidationException('بيانات الحذف غير صحيحة', $e);
    }

    // ── Exists ───────────────────────────────────────────────
    public static function exists(array $query): void
    {
        $e = [];

        if (empty($query['role_id'])) {
            $e['role_id'] = 'معرف الدور مطلوب';
        }
        if (empty($query['permission_id'])) {
            $e['permission_id'] = 'معرف الصلاحية مطلوب';
        }

        if ($e) throw new ValidationException('بيانات التحقق غير صحيحة', $e);
    }
}