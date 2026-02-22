<?php
declare(strict_types=1);

final class CertificatesRequestItemsController
{
    private CertificatesRequestItemsService $service;

    public function __construct(CertificatesRequestItemsService $service)
    {
        $this->service = $service;
    }

    /**
     * List request items with filters, ordering, and pagination.
     */
    public function list(
        int $tenantId,
        array $filters = [],
        ?int $limit = null,
        ?int $offset = null,
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        return $this->service->list($tenantId, $filters, $limit, $offset, $orderBy, $orderDir);
    }

    /**
     * Get a single request item by ID.
     */
    public function get(int $tenantId, int $id): array
    {
        return $this->service->get($tenantId, $id);
    }

    /**
     * Create a new request item.
     *
     * @param int $userId للتسجيل في certificates_logs
     */
    public function create(int $tenantId, array $data, int $userId): array
    {
        return $this->service->create($tenantId, $data, $userId);
    }

    /**
     * Update an existing request item.
     *
     * @param int $userId للتسجيل في certificates_logs
     */
    public function update(int $tenantId, array $data, int $userId): array
    {
        return $this->service->update($tenantId, $data, $userId);
    }

    /**
     * Delete a request item.
     *
     * @param int $userId للتسجيل في certificates_logs
     */
    public function delete(int $tenantId, int $id, int $userId): void
    {
        $this->service->delete($tenantId, $id, $userId);
    }
}