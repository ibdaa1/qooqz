<?php
/**
 * TORO — v1/modules/Pages/Services/PagesService.php
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\{ValidationException, NotFoundException};
use Shared\Helpers\AuditLogger;

final class PagesService
{
    public function __construct(private readonly PagesRepositoryInterface $repo) {}

    public function list(array $filters = []): array
    {
        return [
            'items'  => $this->repo->findAll($filters),
            'total'  => $this->repo->countAll($filters),
            'limit'  => max(1, min((int)($filters['limit'] ?? 50), 200)),
            'offset' => max(0, (int)($filters['offset'] ?? 0)),
        ];
    }

    public function getById(int $id, ?string $lang = null): array
    {
        $page = $this->repo->findById($id, $lang);
        if (!$page) throw new NotFoundException("الصفحة #{$id} غير موجودة");
        return $page;
    }

    public function getBySlug(string $slug, ?string $lang = null): array
    {
        $page = $this->repo->findBySlug($slug, $lang);
        if (!$page) throw new NotFoundException("الصفحة '{$slug}' غير موجودة");
        return $page;
    }

    public function create(CreatePageDTO $dto, int $actorId): array
    {
        if ($this->repo->findBySlug($dto->slug)) {
            throw new ValidationException('هذا الـ slug مستخدم مسبقاً', ['slug' => 'يجب أن يكون فريداً']);
        }

        $pageId = $this->repo->create([
            'slug'      => $dto->slug,
            'template'  => $dto->template,
            'is_active' => $dto->isActive,
        ]);

        foreach ($dto->translations as $t) {
            $langId = $this->repo->resolveLanguageId($t['lang']);
            if ($langId === null) continue;
            $this->repo->upsertTranslation($pageId, $langId, $t);
        }

        AuditLogger::log('page_created', $actorId, 'pages', $pageId);

        return array_merge(
            $this->repo->findById($pageId) ?? [],
            ['translations' => $this->repo->getTranslations($pageId)]
        );
    }

    public function update(int $id, UpdatePageDTO $dto, int $actorId): array
    {
        $existing = $this->repo->findById($id);
        if (!$existing) throw new NotFoundException("الصفحة #{$id} غير موجودة");

        if ($dto->slug !== null && $dto->slug !== $existing['slug']) {
            $conflict = $this->repo->findBySlug($dto->slug);
            if ($conflict && (int)$conflict['id'] !== $id) {
                throw new ValidationException('هذا الـ slug مستخدم مسبقاً', ['slug' => 'يجب أن يكون فريداً']);
            }
        }

        $updateData = array_filter([
            'slug'      => $dto->slug,
            'template'  => $dto->template,
            'is_active' => $dto->isActive !== null ? (int)$dto->isActive : null,
        ], fn($v) => $v !== null);

        if ($updateData) $this->repo->update($id, $updateData);

        if ($dto->translations !== null) {
            foreach ($dto->translations as $t) {
                $langId = $this->repo->resolveLanguageId($t['lang']);
                if ($langId === null) continue;
                $this->repo->upsertTranslation($id, $langId, $t);
            }
        }

        AuditLogger::log('page_updated', $actorId, 'pages', $id);

        return array_merge(
            $this->repo->findById($id) ?? [],
            ['translations' => $this->repo->getTranslations($id)]
        );
    }

    public function delete(int $id, int $actorId): void
    {
        if (!$this->repo->findById($id)) throw new NotFoundException("الصفحة #{$id} غير موجودة");
        $this->repo->delete($id);
        AuditLogger::log('page_deleted', $actorId, 'pages', $id);
    }

    public function getTranslations(int $id): array
    {
        if (!$this->repo->findById($id)) throw new NotFoundException("الصفحة #{$id} غير موجودة");
        return $this->repo->getTranslations($id);
    }
}
