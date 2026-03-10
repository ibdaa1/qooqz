<?php
declare(strict_types=1);

use Shared\Domain\Exceptions\NotFoundException;
use Shared\Domain\Exceptions\ValidationException;
use Shared\Helpers\AuditLogger;

class SettingsService
{
    private PdoSettingsRepository $repository;

    public function __construct(PdoSettingsRepository $repository)
    {
        $this->repository = $repository;
    }

    // ── Public ─────────────────────────────────────────────────
    public function getPublicSettings(): array
    {
        return $this->repository->getPublicSettings();
    }

    // ── List (admin) ───────────────────────────────────────────
    public function list(array $filters = []): array
    {
        $items = $this->repository->getAllSettings($filters);
        $total = $this->repository->countAll($filters);

        return [
            'items'  => $items,
            'total'  => $total,
            'limit'  => max(1, min((int)($filters['limit']  ?? 100), 500)),
            'offset' => max(0, (int)($filters['offset'] ?? 0)),
        ];
    }

    // ── Get by ID ──────────────────────────────────────────────
    public function getById(int $id): array
    {
        $setting = $this->repository->findById($id);
        if (!$setting) throw new NotFoundException("الإعداد #{$id} غير موجود");
        return $setting;
    }

    // ── Get by Group ───────────────────────────────────────────
    public function getByGroup(string $group): array
    {
        return $this->repository->findByGroup($group);
    }

    // ── Create ─────────────────────────────────────────────────
    public function create(array $data, int $actorId): array
    {
        // تحقق من عدم تكرار الـ key
        if ($this->repository->findByKey($data['key'] ?? '')) {
            throw new ValidationException(
                'هذا المفتاح (key) موجود مسبقاً',
                ['key' => 'يجب أن يكون فريداً']
            );
        }

        $id = $this->repository->create($data);
        AuditLogger::log('setting_created', $actorId, 'settings', $id);

        return $this->repository->findById($id) ?? [];
    }

    // ── Update ─────────────────────────────────────────────────
    public function update(int $id, string $value, int $actorId): array
    {
        $setting = $this->repository->findById($id);
        if (!$setting) throw new NotFoundException("الإعداد #{$id} غير موجود");

        $this->repository->update($id, $value);
        AuditLogger::log('setting_updated', $actorId, 'settings', $id);

        return $this->repository->findById($id) ?? [];
    }

    // ── Delete ─────────────────────────────────────────────────
    public function delete(int $id, int $actorId): void
    {
        $setting = $this->repository->findById($id);
        if (!$setting) throw new NotFoundException("الإعداد #{$id} غير موجود");

        $this->repository->delete($id);
        AuditLogger::log('setting_deleted', $actorId, 'settings', $id);
    }
}
