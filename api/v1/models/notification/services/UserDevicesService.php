<?php
declare(strict_types=1);

final class UserDevicesService
{
    private PdoUserDevicesRepository $repo;
    private UserDevicesValidator $validator;

    public function __construct(PdoUserDevicesRepository $repo)
    {
        $this->repo = $repo;
        $this->validator = new UserDevicesValidator();
    }

    /**
     * List devices with pagination, filters, sorting
     */
    public function list(
        ?int $limit,
        ?int $offset,
        array $filters,
        string $orderBy,
        string $orderDir
    ): array {
        return $this->repo->all($limit, $offset, $filters, $orderBy, $orderDir);
    }

    public function count(array $filters = []): int
    {
        return $this->repo->count($filters);
    }

    public function get(int $id): ?array
    {
        return $this->repo->find($id);
    }

    public function getByToken(string $token): ?array
    {
        return $this->repo->findByToken($token);
    }

    public function getByUser(int $userId): array
    {
        return $this->repo->findByUserId($userId);
    }

    public function create(array $data): int
    {
        $this->validator->validate($data, false);

        // Ensure token uniqueness (optional: update existing instead of duplicate)
        $existing = $this->repo->findByToken($data['fcm_token']);
        if ($existing) {
            // If token exists, just update its metadata and return existing id
            $data['id'] = $existing['id'];
            return $this->update($data);
        }

        return $this->repo->save($data);
    }

    public function update(array $data): int
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException("ID is required for update.");
        }
        $this->validator->validate($data, true);
        return $this->repo->save($data);
    }

    public function delete(int $id): bool
    {
        return $this->repo->delete($id);
    }

    public function deleteByUser(int $userId): bool
    {
        return $this->repo->deleteByUserId($userId);
    }

    public function touch(int $id): bool
    {
        return $this->repo->touch($id);
    }
}