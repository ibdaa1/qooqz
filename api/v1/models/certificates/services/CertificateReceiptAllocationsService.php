<?php
declare(strict_types=1);

final class CertificateReceiptAllocationsService
{
    private PdoCertificateReceiptAllocationsRepository $repo;
    private CertificateReceiptAllocationsValidator $validator;

    public function __construct(
        PdoCertificateReceiptAllocationsRepository $repo,
        CertificateReceiptAllocationsValidator $validator
    ) {
        $this->repo = $repo;
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
        $items = $this->repo->all($tenantId, $limit, $offset, $filters, $orderBy, $orderDir);
        $total = $this->repo->count($tenantId, $filters);
        return ['items' => $items, 'total' => $total];
    }

    public function get(int $tenantId, int $id): array
    {
        $row = $this->repo->find($tenantId, $id);
        if (!$row) {
            throw new RuntimeException('Allocation record not found.');
        }
        return $row;
    }

    public function create(int $tenantId, array $data): int
    {
        $this->validator->validate($data, false);
        return $this->repo->save($tenantId, $data);
    }

    public function update(int $tenantId, array $data): int
    {
        $this->validator->validate($data, true);
        return $this->repo->save($tenantId, $data);
    }

    public function delete(int $tenantId, int $id): void
    {
        if (!$this->repo->delete($tenantId, $id)) {
            throw new RuntimeException('Failed to delete allocation record.');
        }
    }
}