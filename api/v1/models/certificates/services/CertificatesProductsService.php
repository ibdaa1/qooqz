<?php
declare(strict_types=1);

final class CertificatesProductsService
{
    private CertificatesProductsRepository $repo;
    private CertificatesProductsValidator  $validator;

    public function __construct(
        CertificatesProductsRepository $repo,
        CertificatesProductsValidator  $validator
    ) {
        $this->repo      = $repo;
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
     * إنشاء منتج — بدون تسجيل في certificates_logs
     * لأن certificates_products ليس له علاقة مباشرة بـ certificates_requests
     * التسجيل يحدث فقط عند استخدام المنتج داخل certificates_request_items
     */
    public function create(array $data): int
    {
        $this->validator->validate($data, false);
        return $this->repo->save($data);
    }

    public function update(array $data): int
    {
        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID is required for update.');
        }
        $this->validator->validate($data, true);
        return $this->repo->save($data);
    }

    public function delete(int $id): bool
    {
        return $this->repo->delete($id);
    }
}