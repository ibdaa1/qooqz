<?php
/**
 * TORO — v1/modules/RolePermissions/DTO/SyncPermissionsDTO.php
 */
declare(strict_types=1);

final class SyncPermissionsDTO
{
    public function __construct(
        public readonly int $roleId,
        /** @var int[] */
        public readonly array $permissionIds,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            roleId: (int)($data['role_id'] ?? 0),
            permissionIds: isset($data['permission_ids']) && is_array($data['permission_ids'])
                ? array_map('intval', $data['permission_ids'])
                : [],
        );
    }
}