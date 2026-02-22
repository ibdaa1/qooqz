<?php
declare(strict_types=1);

final class CertificatesIssuedController
{
    private CertificatesIssuedService $service;
    private CertificatesIssuedValidator $validator;

    public function __construct(
        CertificatesIssuedService $service,
        CertificatesIssuedValidator $validator
    ) {
        $this->service = $service;
        $this->validator = $validator;
    }

    public function list(
        int $tenantId,
        ?int $limit,
        ?int $offset,
        array $filters,
        string $orderBy,
        string $orderDir
    ): array {
        return $this->service->list($tenantId, $limit, $offset, $filters, $orderBy, $orderDir);
    }

    public function get(int $tenantId, int $id): ?array
    {
        return $this->service->get($tenantId, $id);
    }

    public function create(int $tenantId, array $data): int
    {
        $this->validator->validate($data, false);
        return $this->service->create($tenantId, $data);
    }

    public function update(int $tenantId, array $data): int
    {
        $this->validator->validate($data, true);
        return $this->service->update($tenantId, $data);
    }

    public function delete(int $tenantId, int $id): bool
    {
        // يمكن إضافة تحقق إضافي قبل الحذف
        return $this->service->delete($tenantId, $id);
    }
}