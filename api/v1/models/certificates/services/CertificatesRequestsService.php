<?php
declare(strict_types=1);

final class CertificatesRequestsService
{
    private PdoCertificatesRequestsRepository $repo;

    public function __construct(PdoCertificatesRequestsRepository $repo)
    {
        $this->repo = $repo;
    }

    public function list(
        int $tenantId,
        ?int $limit = null,
        ?int $offset = null,
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC'
    ): array {
        $items = $this->repo->all($tenantId, $limit, $offset, $filters, $orderBy, $orderDir);
        $total = $this->repo->count($tenantId, $filters);
        return ['items' => $items, 'total' => $total];
    }

    public function get(int $tenantId, int $id): array
    {
        $row = $this->repo->find($tenantId, $id);
        if (!$row) {
            throw new RuntimeException('Certificate request not found.');
        }
        return $row;
    }

    public function create(int $tenantId, array $data): array
    {
        $errors = CertificatesRequestsValidator::validate($data, false);
        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        }
        $id = $this->repo->save($tenantId, $data);
        return $this->get($tenantId, $id);
    }

    public function update(int $tenantId, array $data): array
    {
        $errors = CertificatesRequestsValidator::validate($data, true);
        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        }
        $this->repo->save($tenantId, $data);
        return $this->get($tenantId, (int)$data['id']);
    }

    public function delete(int $tenantId, int $id): void
    {
        if (!$this->repo->delete($tenantId, $id)) {
            throw new RuntimeException('Failed to delete certificate request.');
        }
    }
}