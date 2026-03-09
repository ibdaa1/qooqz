<?php
/**
 * TORO — v1/modules/ThemeSizes/Services/ThemeSizesService.php
 */
declare(strict_types=1);

use Shared\Domain\Exceptions\{ValidationException, NotFoundException};

final class ThemeSizesService
{
    public function __construct(private readonly ThemeSizesRepositoryInterface $repo) {}

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
        $size = $this->repo->findById($id);
        if (!$size) throw new NotFoundException("المقاس #{$id} غير موجود");
        return $size;
    }

    public function create(array $data): array
    {
        $name = trim($data['name'] ?? '');
        if ($name === '') throw new ValidationException(['name' => 'الاسم مطلوب']);

        if ($this->repo->findByName($name)) {
            throw new ValidationException(['name' => 'هذا الاسم مستخدم مسبقاً']);
        }

        $id = $this->repo->create([
            'name'       => $name,
            'sort_order' => (int)($data['sort_order'] ?? 0),
            'is_active'  => isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1,
        ]);

        return $this->getById($id);
    }

    public function update(int $id, array $data): array
    {
        $this->getById($id);

        $payload = [];
        if (array_key_exists('name', $data)) {
            $name = trim($data['name']);
            if ($name === '') throw new ValidationException(['name' => 'الاسم لا يمكن أن يكون فارغاً']);
            $existing = $this->repo->findByName($name);
            if ($existing && (int)$existing['id'] !== $id) {
                throw new ValidationException(['name' => 'هذا الاسم مستخدم مسبقاً']);
            }
            $payload['name'] = $name;
        }
        if (array_key_exists('sort_order', $data)) $payload['sort_order'] = (int)$data['sort_order'];
        if (array_key_exists('is_active',  $data)) $payload['is_active']  = (int)(bool)$data['is_active'];

        $this->repo->update($id, $payload);
        return $this->getById($id);
    }

    public function delete(int $id): bool
    {
        $this->getById($id);
        return $this->repo->delete($id);
    }
}
