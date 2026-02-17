<?php
declare(strict_types=1);

final class CartsController
{
    private CartsService $service;

    public function __construct(CartsService $service)
    {
        $this->service = $service;
    }

    /**
     * List carts with filters, ordering, and pagination
     */
    public function list(
        int $tenantId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC',
        string $lang = 'ar'
    ): array {
        $items = $this->service->list($tenantId, $limit, $offset, $filters, $orderBy, $orderDir, $lang);
        $total = $this->service->count($tenantId, $filters);

        return [
            'items' => $items,
            'total' => $total
        ];
    }

    /**
     * Get a single cart by ID
     */
    public function get(int $tenantId, int $id, string $lang = 'ar'): ?array
    {
        return $this->service->get($tenantId, $id, $lang);
    }

    /**
     * Get active cart by session_id and entity_id
     */
    public function getBySession(int $tenantId, string $sessionId, int $entityId): ?array
    {
        return $this->service->getBySession($tenantId, $sessionId, $entityId);
    }

    /**
     * Get active cart by user_id and entity_id
     */
    public function getByUser(int $tenantId, int $userId, int $entityId): ?array
    {
        return $this->service->getByUser($tenantId, $userId, $entityId);
    }

    /**
     * Create a new cart
     */
    public function create(int $tenantId, array $data): int
    {
        return $this->service->create($tenantId, $data);
    }

    /**
     * Update an existing cart
     */
    public function update(int $tenantId, array $data): int
    {
        return $this->service->update($tenantId, $data);
    }

    /**
     * Delete a cart by ID
     */
    public function delete(int $tenantId, int $id): bool
    {
        return $this->service->delete($tenantId, $id);
    }
}
