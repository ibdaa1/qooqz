<?php
declare(strict_types=1);

final class CertificatesProductsController
{
    private CertificatesProductsService $service;

    public function __construct(CertificatesProductsService $service)
    {
        $this->service = $service;
    }

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

    public function get(int $id): ?array
    {
        return $this->service->get($id);
    }

    /** @return int New record ID */
    public function create(array $data): int
    {
        return $this->service->create($data);
    }

    /** @return int Updated record ID */
    public function update(array $data): int
    {
        return $this->service->update($data);
    }

    public function delete(int $id): bool
    {
        return $this->service->delete($id);
    }
}