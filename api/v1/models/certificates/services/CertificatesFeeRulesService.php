<?php
declare(strict_types=1);

final class CertificatesFeeRulesService
{
    private PdoCertificatesFeeRulesRepository $repo;
    private CertificatesFeeRulesValidator $validator;

    public function __construct(
        PdoCertificatesFeeRulesRepository $repo,
        CertificatesFeeRulesValidator $validator
    ) {
        $this->repo = $repo;
        $this->validator = $validator;
    }

    public function list(
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC',
        ?int $limit = null,
        ?int $offset = null
    ): array {
        $items = $this->repo->all($filters, $orderBy, $orderDir, $limit, $offset);
        $total = $this->repo->count($filters);
        return ['items' => $items, 'total' => $total];
    }

    public function get(int $id): array
    {
        $row = $this->repo->find($id);
        if (!$row) {
            throw new RuntimeException('Fee rule not found.');
        }
        return $row;
    }

    public function create(array $data): int
    {
        $this->validator->validate($data, false);
        // Optional: check for duplicate rule (fee_type + max_items uniqueness?) 
        // Not implemented for simplicity.
        return $this->repo->save($data);
    }

    public function update(array $data): int
    {
        $this->validator->validate($data, true);
        // Ensure record exists before update
        $this->get((int)$data['id']);
        return $this->repo->save($data);
    }

    public function delete(int $id): void
    {
        $this->get($id); // throws if not found
        $this->repo->delete($id);
    }
}