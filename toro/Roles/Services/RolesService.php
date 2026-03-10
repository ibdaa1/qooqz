<?php
/**
 * TORO — v1/modules/Roles/Services/RolesService.php
 * كل منطق الأدوار — Controller لا يعرف PDO أبداً
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\{ValidationException, NotFoundException};
use Shared\Helpers\AuditLogger;

final class RolesService
{
    public function __construct(
        private readonly RolesRepositoryInterface $repo,
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
            'limit' => max(1, min((int)($filters['limit'] ?? 50), 200)),
            'offset'=> max(0, (int)($filters['offset'] ?? 0)),
        ];
    }

    // ══════════════════════════════════════════════════════════
    // GET ONE BY ID
    // ══════════════════════════════════════════════════════════
    public function getById(int $id): array
    {
        $role = $this->repo->findById($id);
        if (!$role) throw new NotFoundException("الدور #{$id} غير موجود");
        return $role;
    }

    // ══════════════════════════════════════════════════════════
    // GET ONE BY SLUG
    // ══════════════════════════════════════════════════════════
    public function getBySlug(string $slug): array
    {
        $role = $this->repo->findBySlug($slug);
        if (!$role) throw new NotFoundException("الدور '{$slug}' غير موجود");
        return $role;
    }

    // ══════════════════════════════════════════════════════════
    // CREATE
    // ══════════════════════════════════════════════════════════
    public function create(CreateRoleDTO $dto, int $actorId): array
    {
        // تحقق من عدم تكرار الـ slug
        if ($this->repo->findBySlug($dto->slug)) {
            throw new ValidationException(
                'هذا الـ slug مستخدم مسبقاً',
                ['slug' => 'يجب أن يكون فريداً']
            );
        }

        $roleId = $this->repo->create([
            'name'        => $dto->name,
            'slug'        => $dto->slug,
            'description' => $dto->description,
        ]);

        AuditLogger::log('role_created', $actorId, 'roles', $roleId);

        return $this->repo->findById($roleId) ?? [];
    }

    // ══════════════════════════════════════════════════════════
    // UPDATE
    // ══════════════════════════════════════════════════════════
    public function update(int $id, UpdateRoleDTO $dto, int $actorId): array
    {
        $existing = $this->repo->findById($id);
        if (!$existing) throw new NotFoundException("الدور #{$id} غير موجود");

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
            'name'        => $dto->name,
            'slug'        => $dto->slug,
            'description' => $dto->description,
        ], fn($v) => $v !== null);

        if (!empty($updateData)) {
            $this->repo->update($id, $updateData);
        }

        AuditLogger::log('role_updated', $actorId, 'roles', $id);

        return $this->repo->findById($id) ?? [];
    }

    // ══════════════════════════════════════════════════════════
    // DELETE
    // ══════════════════════════════════════════════════════════
    public function delete(int $id, int $actorId): void
    {
        $existing = $this->repo->findById($id);
        if (!$existing) throw new NotFoundException("الدور #{$id} غير موجود");

        // منع حذف أدوار معينة إذا أردت (مثلاً super_admin)
        // يمكن إضافة منطق هنا

        $this->repo->delete($id);
        AuditLogger::log('role_deleted', $actorId, 'roles', $id);
    }
}