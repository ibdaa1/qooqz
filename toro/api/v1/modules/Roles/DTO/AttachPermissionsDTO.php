<?php
/**
 * TORO — v1/modules/Roles/DTO/AttachPermissionsDTO.php
 */
declare(strict_types=1);

final class AttachPermissionsDTO
{
    /** @param int[] $permissionIds */
    public function __construct(
        public readonly array $permissionIds,
    ) {}

    public static function fromArray(array $data): self
    {
        $ids = isset($data['permission_ids']) && is_array($data['permission_ids'])
            ? array_map('intval', $data['permission_ids'])
            : [];
        return new self($ids);
    }
}