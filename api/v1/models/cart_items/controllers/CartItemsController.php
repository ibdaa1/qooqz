<?php
declare(strict_types=1);

final class CartItemsController
{
    private CartItemsService $service;

    public function __construct(CartItemsService $service)
    {
        $this->service = $service;
    }

    /**
     * List cart items with filters, ordering, and pagination
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
     * Get a single cart item by ID
     */
    public function get(int $tenantId, int $id, string $lang = 'ar'): ?array
    {
        return $this->service->get($tenantId, $id, $lang);
    }

    /**
     * Get all items for a specific cart
     */
    public function getByCart(int $tenantId, int $cartId): array
    {
        return $this->service->getByCart($tenantId, $cartId);
    }

    /**
     * Create a new cart item
     */
    public function create(int $tenantId, array $data): int
    {
        return $this->service->create($tenantId, $data);
    }

    /**
     * Update an existing cart item
     */
    public function update(int $tenantId, array $data): int
    {
        return $this->service->update($tenantId, $data);
    }

    /**
     * Delete a cart item by ID
     */
    public function delete(int $tenantId, int $id): bool
    {
        return $this->service->delete($tenantId, $id);
    }
}
