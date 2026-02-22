<?php
declare(strict_types=1);

final class CertificatesLogsController
{
    private CertificatesLogsService $service;

    public function __construct(CertificatesLogsService $service)
    {
        $this->service = $service;
    }

    /**
     * List logs with filters + pagination.
     */
    public function list(
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC',
        ?int $limit = null,
        ?int $offset = null
    ): array {
        $items = $this->service->list($filters, $orderBy, $orderDir, $limit, $offset);
        $total = $this->service->count($filters);
        return ['items' => $items, 'total' => $total];
    }

    /**
     * Get a single log entry by ID.
     */
    public function get(int $id): ?array
    {
        return $this->service->get($id);
    }

    /**
     * Create a log entry.
     *
     * @return int New log ID
     */
    public function create(int $requestId, int $userId, string $actionType, ?string $notes = null): int
    {
        return $this->service->create($requestId, $userId, $actionType, $notes);
    }
}