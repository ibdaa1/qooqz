<?php
/**
 * TORO — v1/modules/Images/Services/ImageTypesService.php
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\{ValidationException, NotFoundException};
use Shared\Helpers\AuditLogger;

final class ImageTypesService
{
    public function __construct(
        private readonly ImageTypesRepositoryInterface $repo,
    ) {}

    public function list(array $filters = []): array
    {
        return [
            'items'  => $this->repo->findAll($filters),
            'total'  => $this->repo->countAll($filters),
            'limit'  => max(1, min((int)($filters['limit'] ?? 100), 500)),
            'offset' => max(0, (int)($filters['offset'] ?? 0)),
        ];
    }

    public function getById(int $id): array
    {
        $row = $this->repo->findById($id);
        if (!$row) throw new NotFoundException("نوع الصورة #{$id} غير موجود");
        return $row;
    }

    public function create(CreateImageTypeDTO $dto, int $actorId): array
    {
        if ($this->repo->findByCode($dto->code)) {
            throw new ValidationException('هذا الـ code مستخدم مسبقاً', ['code' => 'يجب أن يكون فريداً']);
        }

        $id = $this->repo->create([
            'code'         => $dto->code,
            'name'         => $dto->name,
            'description'  => $dto->description,
            'width'        => $dto->width,
            'height'       => $dto->height,
            'crop'         => $dto->crop,
            'quality'      => $dto->quality,
            'format'       => $dto->format,
            'is_thumbnail' => (int)$dto->isThumbnail,
        ]);

        AuditLogger::log('image_type_created', $actorId, 'image_types', $id);
        return $this->repo->findById($id) ?? [];
    }

    public function update(int $id, UpdateImageTypeDTO $dto, int $actorId): array
    {
        $existing = $this->repo->findById($id);
        if (!$existing) throw new NotFoundException("نوع الصورة #{$id} غير موجود");

        if ($dto->code !== null && $dto->code !== $existing['code']) {
            $conflict = $this->repo->findByCode($dto->code);
            if ($conflict && (int)$conflict['id'] !== $id) {
                throw new ValidationException('هذا الـ code مستخدم مسبقاً', ['code' => 'يجب أن يكون فريداً']);
            }
        }

        $updateData = array_filter([
            'code'         => $dto->code,
            'name'         => $dto->name,
            'description'  => $dto->description,
            'width'        => $dto->width,
            'height'       => $dto->height,
            'crop'         => $dto->crop,
            'quality'      => $dto->quality,
            'format'       => $dto->format,
            'is_thumbnail' => $dto->isThumbnail !== null ? (int)$dto->isThumbnail : null,
        ], fn($v) => $v !== null);

        if ($updateData) $this->repo->update($id, $updateData);

        AuditLogger::log('image_type_updated', $actorId, 'image_types', $id);
        return $this->repo->findById($id) ?? [];
    }

    public function delete(int $id, int $actorId): void
    {
        if (!$this->repo->findById($id)) {
            throw new NotFoundException("نوع الصورة #{$id} غير موجود");
        }
        $this->repo->delete($id);
        AuditLogger::log('image_type_deleted', $actorId, 'image_types', $id);
    }
}
