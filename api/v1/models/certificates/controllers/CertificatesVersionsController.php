<?php
declare(strict_types=1);

final class CertificatesVersionsController
{
    private CertificatesVersionsService $service;

    public function __construct(CertificatesVersionsService $service)
    {
        $this->service = $service;
    }

    public function list(
        int $tenantId,
        ?int $limit,
        ?int $offset,
        array $filters,
        string $orderBy,
        string $orderDir
    ): array {
        return $this->service->list(
            $tenantId,$limit,$offset,$filters,$orderBy,$orderDir
        );
    }

    public function get(int $tenantId,int $id): ?array
    {
        return $this->service->get($tenantId,$id);
    }

    public function create(int $tenantId,int $userId,array $data): int
    {
        return $this->service->create($tenantId,$userId,$data);
    }

    public function update(int $tenantId,int $userId,array $data): int
    {
        return $this->service->update($tenantId,$userId,$data);
    }

    public function delete(int $tenantId,int $userId,int $id): bool
    {
        return $this->service->delete($tenantId,$userId,$id);
    }
}