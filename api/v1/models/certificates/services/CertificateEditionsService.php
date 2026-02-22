<?php
declare(strict_types=1);

final class CertificateEditionsService
{
    private PdoCertificateEditionsRepository $repo;
    private CertificateEditionsValidator $validator;

    public function __construct(
        PdoCertificateEditionsRepository $repo,
        CertificateEditionsValidator $validator
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
        return [
            'items' => $this->repo->all($filters, $orderBy, $orderDir, $limit, $offset),
            'total' => $this->repo->count($filters)
        ];
    }

    public function get(int $id): array
    {
        $row = $this->repo->find($id);
        if (!$row) {
            throw new RuntimeException('Certificate edition not found.');
        }
        return $row;
    }

    public function create(array $data): int
    {
        $this->validator->validate($data, false);

        // Check code uniqueness if code provided
        if (!empty($data['code'])) {
            $existing = $this->repo->findByCode($data['code']);
            if ($existing !== null) {
                throw new InvalidArgumentException('Code already exists.');
            }
        }

        return $this->repo->save($data);
    }

    public function update(array $data): int
    {
        $this->validator->validate($data, true);

        // Fetch current record to check code change
        $current = $this->repo->find((int)$data['id']);
        if (!$current) {
            throw new RuntimeException('Certificate edition not found.');
        }

        // If code is being changed and new code is not empty, check uniqueness
        if (isset($data['code']) && $data['code'] !== $current['code']) {
            if (!empty($data['code'])) {
                $existing = $this->repo->findByCode($data['code']);
                if ($existing !== null && (int)$existing['id'] !== (int)$data['id']) {
                    throw new InvalidArgumentException('Code already exists.');
                }
            }
        }

        return $this->repo->save($data);
    }

    public function delete(int $id): void
    {
        if (!$this->repo->delete($id)) {
            throw new RuntimeException('Failed to delete certificate edition.');
        }
    }
}