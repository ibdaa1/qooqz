<?php
declare(strict_types=1);

final class CertificatesRequestsTranslationsService
{
    private PdoCertificatesRequestsTranslationsRepository $repo;
    private CertificatesRequestsTranslationsValidator $validator;

    public function __construct(
        PdoCertificatesRequestsTranslationsRepository $repo,
        CertificatesRequestsTranslationsValidator $validator
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

        return [
            'items' => $items,
            'total' => $total
        ];
    }

    public function get(int $tenantId, int $id): ?array
    {
        return $this->repo->find($tenantId, $id);
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

    public function delete(int $tenantId, int $id): bool
    {
        return $this->repo->delete($tenantId, $id);
    }
}