<?php
declare(strict_types=1);

final class CertificatesIssuedService
{
    private PdoCertificatesIssuedRepository $repo;

    public function __construct(PdoCertificatesIssuedRepository $repo)
    {
        $this->repo = $repo;
    }

    public function list(
        int $tenantId,
        ?int $limit,
        ?int $offset,
        array $filters,
        string $orderBy,
        string $orderDir
    ): array {
        return [
            'items' => $this->repo->all($tenantId, $limit, $offset, $filters, $orderBy, $orderDir),
            'total' => $this->repo->count($tenantId, $filters)
        ];
    }

    public function get(int $tenantId, int $id): ?array
    {
        return $this->repo->find($tenantId, $id);
    }

    public function create(int $tenantId, array $data): int
    {
        // التحقق من أن version_id ينتمي إلى tenant معين (اختياري، لكن يمكن تركه للمستودع)
        // نترك المستودع يقوم بالإدراج المباشر
        return $this->repo->save($tenantId, $data);
    }

    public function update(int $tenantId, array $data): int
    {
        // يجب أن يحتوي data على id
        return $this->repo->save($tenantId, $data);
    }

    public function delete(int $tenantId, int $id): bool
    {
        return $this->repo->delete($tenantId, $id);
    }
}