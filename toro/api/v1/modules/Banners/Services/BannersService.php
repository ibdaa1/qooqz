<?php
/**
 * TORO — v1/modules/Banners/Services/BannersService.php
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\{ValidationException, NotFoundException};
use Shared\Helpers\AuditLogger;

final class BannersService
{
    public function __construct(
        private readonly BannersRepositoryInterface $repo,
    ) {}

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
        $banner = $this->repo->findById($id, $lang);
        if (!$banner) throw new NotFoundException("البانر #{$id} غير موجود");
        return $banner;
    }

    public function create(CreateBannerDTO $dto, int $actorId): array
    {
        $bannerId = $this->repo->create([
            'position'   => $dto->position,
            'link_url'   => $dto->linkUrl,
            'sort_order' => $dto->sortOrder,
            'starts_at'  => $dto->startsAt,
            'ends_at'    => $dto->endsAt,
            'is_active'  => $dto->isActive,
        ]);

        foreach ($dto->translations as $t) {
            $langId = $this->repo->resolveLanguageId($t['lang']);
            if ($langId === null) continue;
            $this->repo->upsertTranslation($bannerId, $langId, $t);
        }

        AuditLogger::log('banner_created', $actorId, 'banners', $bannerId);

        return array_merge(
            $this->repo->findById($bannerId) ?? [],
            ['translations' => $this->repo->getTranslations($bannerId)]
        );
    }

    public function update(int $id, UpdateBannerDTO $dto, int $actorId): array
    {
        $existing = $this->repo->findById($id);
        if (!$existing) throw new NotFoundException("البانر #{$id} غير موجود");

        $updateData = array_filter([
            'position'   => $dto->position,
            'link_url'   => $dto->linkUrl,
            'sort_order' => $dto->sortOrder,
            'starts_at'  => $dto->startsAt,
            'ends_at'    => $dto->endsAt,
            'is_active'  => $dto->isActive !== null ? (int)$dto->isActive : null,
        ], fn($v) => $v !== null);

        if ($updateData) $this->repo->update($id, $updateData);

        if ($dto->translations !== null) {
            foreach ($dto->translations as $t) {
                $langId = $this->repo->resolveLanguageId($t['lang']);
                if ($langId === null) continue;
                $this->repo->upsertTranslation($id, $langId, $t);
            }
        }

        AuditLogger::log('banner_updated', $actorId, 'banners', $id);

        return array_merge(
            $this->repo->findById($id) ?? [],
            ['translations' => $this->repo->getTranslations($id)]
        );
    }

    public function delete(int $id, int $actorId): void
    {
        if (!$this->repo->findById($id)) throw new NotFoundException("البانر #{$id} غير موجود");
        $this->repo->delete($id);
        AuditLogger::log('banner_deleted', $actorId, 'banners', $id);
    }

    public function getTranslations(int $id): array
    {
        if (!$this->repo->findById($id)) throw new NotFoundException("البانر #{$id} غير موجود");
        return $this->repo->getTranslations($id);
    }
}
