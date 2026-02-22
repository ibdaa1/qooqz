<?php
declare(strict_types=1);

final class CertificatesService
{
    private PdoCertificatesRepository $repo;
    private CertificatesValidator $validator;

    public function __construct(
        PdoCertificatesRepository $repo,
        CertificatesValidator $validator
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
        return $this->repo->all($filters, $orderBy, $orderDir, $limit, $offset);
    }

    public function count(array $filters = []): int
    {
        return $this->repo->count($filters);
    }

    public function get(int $id): ?array
    {
        return $this->repo->find($id);
    }

    /**
     * Create a new certificate.
     */
    public function create(array $data): int
    {
        $this->validator->validate($data, false);

        // Check uniqueness of code
        if ($this->repo->findByCode($data['code']) !== null) {
            throw new InvalidArgumentException('Code already exists.');
        }

        return $this->repo->save($data);
    }

    /**
     * Update an existing certificate.
     */
    public function update(array $data): int
    {
        $this->validator->validate($data, true);

        $existing = $this->repo->find((int)$data['id']);
        if (!$existing) {
            throw new RuntimeException('Certificate not found.');
        }

        // If code is being changed, check uniqueness
        if (isset($data['code']) && $data['code'] !== $existing['code']) {
            if ($this->repo->findByCode($data['code']) !== null) {
                throw new InvalidArgumentException('Code already exists.');
            }
        }

        return $this->repo->save($data);
    }

    /**
     * Delete a certificate.
     */
    public function delete(int $id): void
    {
        $existing = $this->repo->find($id);
        if (!$existing) {
            throw new RuntimeException('Certificate not found.');
        }
        // Optional: check if certificate is used elsewhere before deletion
        // For simplicity, we delete directly.
        $this->repo->delete($id);
    }
}