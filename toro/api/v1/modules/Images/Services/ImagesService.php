<?php
/**
 * TORO — v1/modules/Images/Services/ImagesService.php
 * خدمة الصور الموحدة — تدعم الرفع المباشر وتخزين الروابط
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\{ValidationException, NotFoundException};
use Shared\Helpers\AuditLogger;

final class ImagesService
{
    public function __construct(
        private readonly ImagesRepositoryInterface $repo,
    ) {}

    // ══════════════════════════════════════════════════════════
    // LIST
    // ══════════════════════════════════════════════════════════
    public function list(array $filters = []): array
    {
        return [
            'items'  => $this->repo->findAll($filters),
            'total'  => $this->repo->countAll($filters),
            'limit'  => max(1, min((int)($filters['limit'] ?? 50), 200)),
            'offset' => max(0, (int)($filters['offset'] ?? 0)),
        ];
    }

    public function getById(int $id): array
    {
        $row = $this->repo->findById($id);
        if (!$row) throw new NotFoundException("الصورة #{$id} غير موجودة");
        return $row;
    }

    public function getByOwner(int $ownerId, ?int $imageTypeId = null): array
    {
        return $this->repo->findByOwner($ownerId, $imageTypeId);
    }

    // ══════════════════════════════════════════════════════════
    // UPLOAD (multipart/form-data)
    // ══════════════════════════════════════════════════════════
    public function upload(array $file, array $meta, int $actorId): array
    {
        ImagesValidator::upload($file);

        $uploadDir = defined('UPLOAD_PATH') ? UPLOAD_PATH : (dirname(__DIR__, 6) . '/uploads/images');
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                throw new \RuntimeException("فشل إنشاء مجلد الرفع: {$uploadDir}");
            }
        }

        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = uniqid('img_', true) . '.' . $ext;
        $dest     = rtrim($uploadDir, '/') . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new \RuntimeException('فشل حفظ الملف على الخادم');
        }

        $baseUrl  = defined('UPLOAD_BASE_URL') ? UPLOAD_BASE_URL : '/uploads/images';
        $url      = rtrim($baseUrl, '/') . '/' . $filename;

        $imageId = $this->repo->create([
            'owner_id'     => isset($meta['owner_id'])     ? (int)$meta['owner_id']     : null,
            'image_type_id'=> isset($meta['image_type_id']) ? (int)$meta['image_type_id'] : null,
            'user_id'      => $actorId,
            'filename'     => $filename,
            'url'          => $url,
            'thumb_url'    => null,
            'mime_type'    => mime_content_type($dest) ?: ($file['type'] ?? null),
            'size'         => $file['size'],
            'visibility'   => $meta['visibility'] ?? 'public',
            'is_main'      => (int)(bool)($meta['is_main'] ?? 0),
            'sort_order'   => (int)($meta['sort_order'] ?? 0),
        ]);

        if (!empty($meta['is_main']) && !empty($meta['owner_id'])) {
            $this->repo->setMain($imageId, (int)$meta['owner_id']);
        }

        AuditLogger::log('image_uploaded', $actorId, 'images', $imageId);
        return $this->repo->findById($imageId) ?? [];
    }

    // ══════════════════════════════════════════════════════════
    // CREATE (URL-based — no file upload)
    // ══════════════════════════════════════════════════════════
    public function create(CreateImageDTO $dto, int $actorId): array
    {
        $imageId = $this->repo->create([
            'owner_id'     => $dto->ownerId,
            'image_type_id'=> $dto->imageTypeId,
            'user_id'      => $actorId,
            'filename'     => $dto->filename,
            'url'          => $dto->url,
            'thumb_url'    => $dto->thumbUrl,
            'mime_type'    => $dto->mimeType,
            'size'         => $dto->size,
            'visibility'   => $dto->visibility,
            'is_main'      => (int)$dto->isMain,
            'sort_order'   => $dto->sortOrder,
        ]);

        if ($dto->isMain && $dto->ownerId) {
            $this->repo->setMain($imageId, $dto->ownerId);
        }

        AuditLogger::log('image_created', $actorId, 'images', $imageId);
        return $this->repo->findById($imageId) ?? [];
    }

    // ══════════════════════════════════════════════════════════
    // UPDATE
    // ══════════════════════════════════════════════════════════
    public function update(int $id, UpdateImageDTO $dto, int $actorId): array
    {
        $existing = $this->repo->findById($id);
        if (!$existing) throw new NotFoundException("الصورة #{$id} غير موجودة");

        $data = array_filter([
            'owner_id'     => $dto->ownerId,
            'image_type_id'=> $dto->imageTypeId,
            'filename'     => $dto->filename,
            'url'          => $dto->url,
            'thumb_url'    => $dto->thumbUrl,
            'mime_type'    => $dto->mimeType,
            'size'         => $dto->size,
            'visibility'   => $dto->visibility,
            'is_main'      => $dto->isMain !== null ? (int)$dto->isMain : null,
            'sort_order'   => $dto->sortOrder,
        ], fn($v) => $v !== null);

        if ($data) $this->repo->update($id, $data);

        if ($dto->isMain === true) {
            $ownerId = $dto->ownerId ?? ($existing['owner_id'] ?? null);
            if ($ownerId) $this->repo->setMain($id, (int)$ownerId);
        }

        AuditLogger::log('image_updated', $actorId, 'images', $id);
        return $this->repo->findById($id) ?? [];
    }

    // ══════════════════════════════════════════════════════════
    // DELETE
    // ══════════════════════════════════════════════════════════
    public function delete(int $id, int $actorId): void
    {
        $existing = $this->repo->findById($id);
        if (!$existing) throw new NotFoundException("الصورة #{$id} غير موجودة");

        $this->repo->delete($id);
        AuditLogger::log('image_deleted', $actorId, 'images', $id);
    }

    // ══════════════════════════════════════════════════════════
    // SET MAIN
    // ══════════════════════════════════════════════════════════
    public function setMain(int $id, int $actorId): array
    {
        $existing = $this->repo->findById($id);
        if (!$existing) throw new NotFoundException("الصورة #{$id} غير موجودة");

        $ownerId = (int)($existing['owner_id'] ?? 0);
        if (!$ownerId) {
            throw new ValidationException('لا يمكن تعيين صورة رئيسية بدون مالك', ['owner_id' => 'مطلوب']);
        }

        $this->repo->setMain($id, $ownerId);
        AuditLogger::log('image_set_main', $actorId, 'images', $id);
        return $this->repo->findById($id) ?? [];
    }
}
