<?php
/**
 * TORO — v1/modules/Permissions/Services/PermissionsService.php
 * كل منطق الصلاحيات — Controller لا يعرف PDO أبداً
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\{ValidationException, NotFoundException};
use Shared\Helpers\AuditLogger;

final class PermissionsService
{
    public function __construct(
        private readonly PermissionsRepositoryInterface $repo,
    ) {}

    // ══════════════════════════════════════════════════════════
    // LIST
    // ══════════════════════════════════════════════════════════
    public function list(array $filters = []): array
    {
        $items = $this->repo->findAll($filters);
        $total = $this->repo->countAll($filters);

        return [
            'items' => $items,
            'total' => $total,
            'limit' => max(1, min((int)($filters['limit'] ?? 100), 200)),
            'offset'=> max(0, (int)($filters['offset'] ?? 0)),
        ];
    }

    // ══════════════════════════════════════════════════════════
    // GET ONE BY ID
    // ══════════════════════════════════════════════════════════
    public function getById(int $id): array
    {
        $perm = $this->repo->findById($id);
        if (!$perm) throw new NotFoundException("الصلاحية #{$id} غير موجودة");
        return $perm;
    }

    // ══════════════════════════════════════════════════════════
    // GET ONE BY SLUG
    // ══════════════════════════════════════════════════════════
    public function getBySlug(string $slug): array
    {
        $perm = $this->repo->findBySlug($slug);
        if (!$perm) throw new NotFoundException("الصلاحية '{$slug}' غير موجودة");
        return $perm;
    }

    // ══════════════════════════════════════════════════════════
    // GET GROUPED
    // ══════════════════════════════════════════════════════════
    public function getGrouped(): array
    {
        return $this->repo->getAllGrouped();
    }

    // ══════════════════════════════════════════════════════════
    // CREATE
    // ══════════════════════════════════════════════════════════
    public function create(CreatePermissionDTO $dto, int $actorId): array
    {
        // تحقق من عدم تكرار الـ slug
        if ($this->repo->findBySlug($dto->slug)) {
            throw new ValidationException(
                'هذا الـ slug مستخدم مسبقاً',
                ['slug' => 'يجب أن يكون فريداً']
            );
        }

        $permId = $this->repo->create([
            'name'  => $dto->name,
            'slug'  => $dto->slug,
            'group' => $dto->group,
        ]);

        AuditLogger::log('permission_created', $actorId, 'permissions', $permId);

        return $this->repo->findById($permId) ?? [];
    }

    // ══════════════════════════════════════════════════════════
    // UPDATE
    // ══════════════════════════════════════════════════════════
    public function update(int $id, UpdatePermissionDTO $dto, int $actorId): array
    {
        $existing = $this->repo->findById($id);
        if (!$existing) throw new NotFoundException("الصلاحية #{$id} غير موجودة");

        // تحقق من uniqueness إذا تغيّر الـ slug
        if ($dto->slug !== null && $dto->slug !== $existing['slug']) {
            $conflict = $this->repo->findBySlug($dto->slug);
            if ($conflict && (int)$conflict['id'] !== $id) {
                throw new ValidationException(
                    'هذا الـ slug مستخدم مسبقاً',
                    ['slug' => 'يجب أن يكون فريداً']
                );
            }
        }

        $updateData = array_filter([
            'name'  => $dto->name,
            'slug'  => $dto->slug,
            'group' => $dto->group,
        ], fn($v) => $v !== null);

        if (!empty($updateData)) {
            $this->repo->update($id, $updateData);
        }

        AuditLogger::log('permission_updated', $actorId, 'permissions', $id);

        return $this->repo->findById($id) ?? [];
    }

    // ══════════════════════════════════════════════════════════
    // DELETE
    // ══════════════════════════════════════════════════════════
    public function delete(int $id, int $actorId): void
    {
        $existing = $this->repo->findById($id);
        if (!$existing) throw new NotFoundException("الصلاحية #{$id} غير موجودة");

        $this->repo->delete($id);
        AuditLogger::log('permission_deleted', $actorId, 'permissions', $id);
    }
}