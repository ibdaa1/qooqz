<?php
declare(strict_types=1);

final class CertificatesLogsService
{
    private CertificatesLogsRepository $repo;

    public function __construct(CertificatesLogsRepository $repo)
    {
        $this->repo = $repo;
    }

    public function list(
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC',
        ?int $limit = null,
        ?int $offset = null
    ): array {
        return $this->repo->all($filters, $orderBy, $orderDir, $limit, $offset);
    }

    public function count(array $filters = []): int
    {
        return $this->repo->count($filters);
    }

    public function get(int $id): ?array
    {
        return $this->repo->find($id);
    }

    /**
     * إنشاء سجل log — يُستخدم مباشرة من أي Service آخر أو من الـ route
     */
    public function create(int $requestId, int $userId, string $actionType, ?string $notes = null): int
    {
        if (empty($requestId) || empty($userId) || empty($actionType)) {
            throw new InvalidArgumentException('request_id, user_id, action_type are required.');
        }
        return $this->repo->insert($requestId, $userId, $actionType, $notes);
    }
}