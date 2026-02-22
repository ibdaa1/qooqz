<?php
declare(strict_types=1);

final class CertificatesTemplatesController
{
    private CertificatesTemplatesService $service;

    public function __construct(CertificatesTemplatesService $service)
    {
        $this->service = $service;
    }

    public function list(array $filters = [], ?int $limit = null, ?int $offset = null, string $orderBy = 'id', string $orderDir = 'DESC'): array
    {
        return $this->service->list($filters, $limit, $offset, $orderBy, $orderDir);
    }

    public function get(int $id): array
    {
        return $this->service->get($id);
    }

    public function create(array $data): array
    {
        return $this->service->create($data);
    }

    public function update(array $data): array
    {
        return $this->service->update($data);
    }

    public function delete(int $id): void
    {
        $this->service->delete($id);
    }
}