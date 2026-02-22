<?php
declare(strict_types=1);

final class CertificatesProductsTranslationsController
{
    private CertificatesProductsTranslationsService $service;

    public function __construct(CertificatesProductsTranslationsService $service)
    {
        $this->service = $service;
    }

    public function list(
        array $filters = [],
        string $orderBy = 'id',
        string $orderDir = 'DESC',
        ?int $limit = null,
        ?int $offset = null
    ): array {
        $items = $this->service->list($filters, $orderBy, $orderDir, $limit, $offset);
        $total = $this->service->count($filters);
        return ['items' => $items, 'total' => $total];
    }

    public function get(int $id): ?array
    {
        return $this->service->get($id);
    }

    /** Upsert on (product_id, language_code) */
    public function create(array $data): int
    {
        return $this->service->create($data);
    }

    public function update(array $data): int
    {
        return $this->service->update($data);
    }

    public function delete(int $id): bool
    {
        return $this->service->delete($id);
    }

    /** حذف بـ product_id + language_code */
    public function deleteByProductAndLang(int $productId, string $languageCode): bool
    {
        return $this->service->deleteByProductAndLang($productId, $languageCode);
    }
}